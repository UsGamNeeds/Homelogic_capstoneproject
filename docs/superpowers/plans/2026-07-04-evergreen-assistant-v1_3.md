# Evergreen Assistant v1.3 Conversational Context Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Give the assistant a sticky active-resident memory (client-held id), page-context seeding, and an `all_medications` intent, so name-less follow-ups work naturally.

**Architecture:** The reply gains a `resident: {id,name}|null`; the client stores it and sends it back as `context_resident_id` on the next query. `AssistantService::handle()` falls back to the context resident (re-checked via FacilityScope) when a message names nobody. A small React context lets resident pages publish their resident to seed the widget. A new `all_medications` intent lists the full active med list.

**Tech Stack:** Laravel 11, PHPUnit 11, Sanctum, React 19, TanStack Query, axios, Tailwind, lucide-react.

## Global Constraints

- Read-only. No writes. No server-side conversation history.
- No PHI to the LLM: `context_resident_id` is an integer, never a name. When a message names a resident, the existing `ResidentResolver` redaction runs before any LLM call. When context is used, the message carries no resident name.
- `context_resident_id` is re-resolved via a FacilityScope-active `Resident` query every request; a foreign/stale id resolves to nothing and is ignored (no cross-tenant access). Never use `withoutGlobalScopes()`.
- Intent enum becomes exactly: `todays_meds | prn_status | all_medications | unknown`.
- Chip `message` phrases keep a fast-path keyword: "today's meds for …", "PRN for …", "all medications for …".
- Every `handle()` return includes a `resident` key (`{id,name}` or `null`) AND a `suggestions` key.
- PHP: `declare(strict_types=1);`, typed signatures. Do NOT run pint manually. Tests: PHPUnit class style, `RefreshDatabase`, `Sanctum::actingAs`, `Http::fake()`.
- Frontend: no JS test runner — verify with `npm run build`. No `console.log`, no `dangerouslySetInnerHTML`. New context lives in `resources/js/contexts/` (repo convention, plural).

---

## File Structure

**Backend (modify):**
- `app/Services/IntentParsingService.php` — add `all_medications` (enum, fast-path, prompt).
- `app/Services/AssistantService.php` — `handle()` gains `?int $contextResidentId`; resolution precedence; `resident` in every reply; `answerAllMedications()`; `All meds` chip.
- `app/Http/Controllers/Api/AssistantController.php` — validate + forward `context_resident_id`.

**Frontend (create):**
- `resources/js/contexts/AssistantResidentContext.jsx` — provider + `useAssistantResident` + `useAssistantPageResident`.
- `resources/js/components/assistant/AllMedsResult.jsx` — full-list card.

**Frontend (modify):**
- `resources/js/app.jsx` — wrap app in `AssistantResidentProvider`.
- `resources/js/pages/caregiver/ResidentDetailPage.jsx` — publish resident.
- `resources/js/pages/AssessmentDetail.jsx` — publish `assessment.resident`.
- `resources/js/hooks/useAssistant.js` — `mutate({message, contextResidentId})`.
- `resources/js/components/assistant/ChatWidget.jsx` — active-resident state, send context id, store reply `resident`, seed from page, header indicator + clear, dispatch `all_medications`.

---

## Task 1: `all_medications` intent

**Files:**
- Modify: `app/Services/IntentParsingService.php`
- Test: `tests/Unit/IntentParsingServiceTest.php`

**Interfaces:**
- Produces: `fastPath`/`parse` may now return `'all_medications'`; enum includes it.

- [ ] **Step 1: Add failing tests** (append inside the class in `tests/Unit/IntentParsingServiceTest.php`)

```php
    public function test_fastpath_detects_all_medications(): void
    {
        $this->assertSame('all_medications', $this->service()->fastPath('all medications for {RESIDENT}'));
        $this->assertSame('all_medications', $this->service()->fastPath('show all his meds'));
    }

    public function test_fastpath_all_meds_takes_priority_over_todays(): void
    {
        // "all ... medications" must NOT collapse to todays_meds
        $this->assertSame('all_medications', $this->service()->fastPath('list all medications'));
    }

    public function test_parse_accepts_all_medications_from_llm(): void
    {
        \Illuminate\Support\Facades\Http::fake([
            'api.anthropic.com/*' => \Illuminate\Support\Facades\Http::response([
                'content' => [['type' => 'text', 'text' => 'all_medications']],
            ], 200),
        ]);
        $this->assertSame('all_medications', $this->service()->parse('give me the full drug rundown for {RESIDENT}'));
    }
```

- [ ] **Step 2: Run to verify failure**

Run: `vendor/bin/phpunit tests/Unit/IntentParsingServiceTest.php`
Expected: FAIL — `fastPath` returns `todays_meds` (or null); `all_medications` not in enum so parse maps it to `unknown`.

- [ ] **Step 3: Implement**

In `app/Services/IntentParsingService.php`, update the enum constant:

```php
    private const INTENTS = ['todays_meds', 'prn_status', 'all_medications', 'unknown'];
```

Update the system prompt:

```php
    private const SYSTEM_PROMPT =
        'You classify a care-home staff request into exactly one label: '
        ."todays_meds (they want a resident's medications scheduled for today), "
        ."all_medications (they want the resident's full/complete list of all medications), "
        ."prn_status (they want a resident's as-needed/PRN medications), or "
        ."unknown (anything else). The resident's name is redacted as {RESIDENT}. "
        .'Reply with ONLY the label, nothing else.';
```

Replace the `fastPath` body so the "all" rule is checked before `todays_meds`:

```php
    public function fastPath(string $text): ?string
    {
        $t = strtolower($text);

        if (preg_match('/\bprn\b|as needed/', $t) === 1) {
            return 'prn_status';
        }

        $hasMedWord = preg_match('/\b(med|meds|medication|medications|tablet|tablets|drug|drugs|dose|doses)\b/', $t) === 1;

        if ($hasMedWord && preg_match('/\ball\b/', $t) === 1) {
            return 'all_medications';
        }

        if ($hasMedWord) {
            return 'todays_meds';
        }

        return null;
    }
```

- [ ] **Step 4: Run to verify pass**

Run: `vendor/bin/phpunit tests/Unit/IntentParsingServiceTest.php`
Expected: PASS (existing + 3 new).

- [ ] **Step 5: Commit**

```bash
git add app/Services/IntentParsingService.php tests/Unit/IntentParsingServiceTest.php
git commit -m "feat(assistant): add all_medications intent to parser"
```

---

## Task 2: AssistantService — context memory + all-meds + resident in reply

**Files:**
- Modify: `app/Services/AssistantService.php`
- Test: `tests/Feature/AssistantServiceTest.php`

**Interfaces:**
- Consumes: `IntentParsingService` (Task 1), `ResidentResolver`, `MedicationService`, `Resident`.
- Produces: `handle(string $message, User $user, ?int $contextResidentId = null): array` returning `{intent, answer, data, suggestions, resident}` where `resident` is `{id:int, name:string}|null`.

- [ ] **Step 1: Add failing tests** (append inside the class in `tests/Feature/AssistantServiceTest.php`; reuse existing `seedFixtures()`/`makeMedication()`)

```php
    public function test_uses_context_resident_when_message_has_no_name(): void
    {
        Http::fake();
        [, $branch, $user, $resident] = $this->seedFixtures();
        $this->makeMedication($resident, $branch, ['name' => 'Aspirin 75mg', 'instructions' => 'o.d']);

        // message names nobody; context id supplies the resident
        $result = app(AssistantService::class)->handle('show all his meds', $user, $resident->id);

        $this->assertSame('all_medications', $result['intent']);
        $this->assertSame($resident->id, $result['resident']['id']);
        $this->assertStringContainsString('Aspirin', $result['answer']);
        Http::assertNothingSent();
    }

    public function test_named_resident_overrides_context(): void
    {
        Http::fake();
        [, $branch, $user, $resident] = $this->seedFixtures(); // Mary Smith
        $other = \App\Models\Resident::factory()->create([
            'branch_id' => $branch->id, 'first_name' => 'Nora', 'last_name' => 'Quinn',
        ]);

        // context says $other, but message names Mary Smith -> Mary wins
        $result = app(AssistantService::class)->handle("today's meds for Mary Smith", $user, $other->id);

        $this->assertSame($resident->id, $result['resident']['id']);
    }

    public function test_foreign_context_id_is_ignored(): void
    {
        Http::fake();
        [, , $user] = $this->seedFixtures();
        $otherFacility = \App\Models\Facility::factory()->create();
        $otherBranch = \App\Models\Branch::factory()->create(['facility_id' => $otherFacility->id]);
        $foreign = \App\Models\Resident::factory()->create([
            'branch_id' => $otherBranch->id, 'first_name' => 'Zed', 'last_name' => 'Cross',
        ]);

        $result = app(AssistantService::class)->handle('all his meds', $user, $foreign->id);

        $this->assertNull($result['resident']);
        $this->assertStringContainsStringIgnoringCase('which resident', $result['answer']);
    }

    public function test_all_medications_lists_active_meds(): void
    {
        Http::fake();
        [, $branch, $user, $resident] = $this->seedFixtures();
        $this->makeMedication($resident, $branch, ['name' => 'Metformin 500mg', 'instructions' => 'b.d']);

        $result = app(AssistantService::class)->handle('all medications for Mary Smith', $user);

        $this->assertSame('all_medications', $result['intent']);
        $this->assertStringContainsString('Metformin', $result['answer']);
        $this->assertSame($resident->id, $result['resident']['id']);
    }

    public function test_reply_always_has_resident_key(): void
    {
        Http::fake();
        [, , $user] = $this->seedFixtures();
        $result = app(AssistantService::class)->handle('what meds are due', $user);
        $this->assertArrayHasKey('resident', $result);
        $this->assertNull($result['resident']);
    }
```

- [ ] **Step 2: Run to verify failure**

Run: `vendor/bin/phpunit tests/Feature/AssistantServiceTest.php`
Expected: FAIL — `handle()` has no 3rd param / no `resident` key / no `all_medications`.

- [ ] **Step 3: Replace `handle()`**

```php
    /**
     * @return array{intent: string, answer: string, data: array, suggestions: array, resident: ?array}
     */
    public function handle(string $message, User $user, ?int $contextResidentId = null): array
    {
        $resolution = $this->resolver->resolve($message, $user);

        if ($resolution->status === 'ambiguous') {
            $names = collect($resolution->matches)->map(fn ($r) => $this->label($r))->implode(', ');
            $suggestions = collect($resolution->matches)->map(fn ($r) => [
                'label' => $this->label($r),
                'message' => "today's meds for {$this->label($r)}",
            ])->all();

            return $this->reply('unknown', "More than one resident matches that name. Which one did you mean: {$names}?", [], $suggestions, null);
        }

        $resident = null;
        if ($resolution->status === 'one') {
            $resident = $resolution->matches[0];
        } elseif ($contextResidentId !== null) {
            // FacilityScope active: a foreign/stale id resolves to null and is ignored.
            $resident = Resident::query()->find($contextResidentId);
        }

        if ($resident === null) {
            $intent = $this->intents->fastPath($message);
            if (in_array($intent, ['todays_meds', 'prn_status', 'all_medications'], true)) {
                return $this->reply($intent, "Which resident? I couldn't find that name in your facility.", [], [], null);
            }

            return $this->reply('unknown', "I can tell you a resident's medications for today, all their medications, or their PRN (as-needed) meds. Try: \"today's meds for Jane Doe\".", [], [], null);
        }

        $intent = $this->intents->parse($resolution->redacted);

        return match ($intent) {
            'todays_meds' => $this->answerTodaysMeds($resident),
            'prn_status' => $this->answerPrn($resident),
            'all_medications' => $this->answerAllMedications($resident),
            default => $this->reply(
                'unknown',
                "I can help with {$this->label($resident)}'s meds for today, all their meds, or their PRN meds. Which would you like?",
                [],
                $this->choiceSuggestions($resident),
                $resident,
            ),
        };
    }
```

- [ ] **Step 4: Add `resident` to the two existing answer methods**

In `answerTodaysMeds()`, both return points must carry the resident. Change the early empty return and the final return:

```php
        if ($meds->isEmpty()) {
            return $this->reply('todays_meds', "No active medications on record for {$this->label($resident)}.", [], [], $resident);
        }
```

and the final `return [...]` — add the `resident` key:

```php
        return [
            'intent' => 'todays_meds',
            'answer' => $answer,
            'data' => $withStatus->map(fn ($m) => [
                'id' => $m->id,
                'name' => $m->name,
                'is_fully_administered_today' => (bool) $m->is_fully_administered_today,
                'is_overdue' => (bool) $m->is_overdue,
                'next_dose_at' => $m->next_dose_at,
            ])->values()->all(),
            'suggestions' => [],
            'resident' => $this->residentPayload($resident),
        ];
```

In `answerPrn()`, likewise — the empty return:

```php
        if ($prn->isEmpty()) {
            return $this->reply('prn_status', "No PRN (as-needed) medications on record for {$this->label($resident)}.", [], [], $resident);
        }
```

and the final `return [...]` add the key:

```php
        return [
            'intent' => 'prn_status',
            'answer' => $answer,
            'data' => $data->all(),
            'suggestions' => [],
            'resident' => $this->residentPayload($resident),
        ];
```

- [ ] **Step 5: Add `answerAllMedications()`, `residentPayload()`, update `reply()` + `choiceSuggestions()`**

Add the new answer method (e.g. after `answerPrn()`):

```php
    private function answerAllMedications(Resident $resident): array
    {
        $meds = $resident->medicationOrders()->where('is_active', true)->get();

        if ($meds->isEmpty()) {
            return $this->reply('all_medications', "No active medications on record for {$this->label($resident)}.", [], [], $resident);
        }

        $data = $meds->map(fn ($m) => [
            'id' => $m->id,
            'name' => $m->name,
            'instructions' => (string) $m->instructions,
        ])->values();

        $lines = $data->map(function ($row) {
            return $row['instructions'] !== ''
                ? "- {$row['name']} ({$row['instructions']})"
                : "- {$row['name']}";
        })->implode("\n");

        return [
            'intent' => 'all_medications',
            'answer' => "All medications for {$this->label($resident)}:\n".$lines,
            'data' => $data->all(),
            'suggestions' => [],
            'resident' => $this->residentPayload($resident),
        ];
    }
```

Replace `reply()` to accept + emit the resident:

```php
    /**
     * @param  array<int, array{label: string, message: string}>  $suggestions
     * @return array{intent: string, answer: string, data: array, suggestions: array, resident: ?array}
     */
    private function reply(string $intent, string $answer, array $data = [], array $suggestions = [], ?Resident $resident = null): array
    {
        return [
            'intent' => $intent,
            'answer' => $answer,
            'data' => $data,
            'suggestions' => $suggestions,
            'resident' => $this->residentPayload($resident),
        ];
    }

    /**
     * @return array{id: int, name: string}|null
     */
    private function residentPayload(?Resident $resident): ?array
    {
        return $resident ? ['id' => $resident->id, 'name' => $this->label($resident)] : null;
    }
```

Add the `All meds` chip in `choiceSuggestions()`:

```php
    private function choiceSuggestions(Resident $resident): array
    {
        $name = $this->label($resident);

        return [
            ['label' => "Today's meds", 'message' => "today's meds for {$name}"],
            ['label' => 'All meds', 'message' => "all medications for {$name}"],
            ['label' => 'PRN', 'message' => "PRN for {$name}"],
        ];
    }
```

- [ ] **Step 6: Run to verify pass**

Run: `vendor/bin/phpunit tests/Feature/AssistantServiceTest.php`
Expected: PASS. If a v1.1 test asserted an exact array shape without `resident`, update it to expect the new key.

- [ ] **Step 7: Commit**

```bash
git add app/Services/AssistantService.php tests/Feature/AssistantServiceTest.php
git commit -m "feat(assistant): context-resident memory, all-meds answer, resident in reply"
```

---

## Task 3: Endpoint forwards `context_resident_id`

**Files:**
- Modify: `app/Http/Controllers/Api/AssistantController.php`
- Test: `tests/Feature/AssistantEndpointTest.php`

**Interfaces:**
- Consumes: `AssistantService::handle(string, User, ?int)` (Task 2).
- Produces: `POST /api/v1/assistant/query` accepts optional `context_resident_id` (nullable integer).

- [ ] **Step 1: Add failing test** (append inside the class in `tests/Feature/AssistantEndpointTest.php`)

```php
    public function test_forwards_context_resident_id(): void
    {
        \Illuminate\Support\Facades\Http::fake();
        $facility = \App\Models\Facility::factory()->create();
        $branch = \App\Models\Branch::factory()->create(['facility_id' => $facility->id]);
        $user = \App\Models\User::factory()->create(['facility_id' => $facility->id, 'role' => 'admin']);
        $resident = \App\Models\Resident::factory()->create([
            'branch_id' => $branch->id, 'first_name' => 'Mary', 'last_name' => 'Smith',
        ]);
        \Laravel\Sanctum\Sanctum::actingAs($user);

        // message names nobody; context id resolves the resident
        $this->postJson('/api/v1/assistant/query', [
            'message' => 'all his meds',
            'context_resident_id' => $resident->id,
        ])->assertOk()->assertJsonPath('data.resident.id', $resident->id);
    }

    public function test_rejects_non_integer_context_id(): void
    {
        $facility = \App\Models\Facility::factory()->create();
        $user = \App\Models\User::factory()->create(['facility_id' => $facility->id, 'role' => 'admin']);
        \Laravel\Sanctum\Sanctum::actingAs($user);

        $this->postJson('/api/v1/assistant/query', [
            'message' => 'hi',
            'context_resident_id' => 'not-an-int',
        ])->assertStatus(422);
    }
```

- [ ] **Step 2: Run to verify failure**

Run: `vendor/bin/phpunit tests/Feature/AssistantEndpointTest.php`
Expected: FAIL — context id not forwarded (resident null); non-integer not rejected.

- [ ] **Step 3: Implement** — replace the `query()` method body

```php
    public function query(Request $request, AssistantService $assistant): JsonResponse
    {
        if ($error = $this->requireModuleAccess(Modules::MEDICATIONS)) {
            return $error;
        }

        if ($error = $this->requirePermission('view_medications')) {
            return $error;
        }

        $validated = $request->validate([
            'message' => 'required|string|max:500',
            'context_resident_id' => 'nullable|integer',
        ]);

        $result = $assistant->handle(
            $validated['message'],
            $request->user(),
            $validated['context_resident_id'] ?? null,
        );

        return $this->success($result);
    }
```

- [ ] **Step 4: Run to verify pass**

Run: `vendor/bin/phpunit --filter Assistant`
Expected: whole assistant suite green.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Api/AssistantController.php tests/Feature/AssistantEndpointTest.php
git commit -m "feat(assistant): accept context_resident_id on query endpoint"
```

---

## Task 4: Page-resident context provider + page wiring

**Files:**
- Create: `resources/js/contexts/AssistantResidentContext.jsx`
- Modify: `resources/js/app.jsx`
- Modify: `resources/js/pages/caregiver/ResidentDetailPage.jsx`
- Modify: `resources/js/pages/AssessmentDetail.jsx`
- Verify: `npm run build`

**Interfaces:**
- Produces:
  - `AssistantResidentProvider({children})`.
  - `useAssistantResident()` → `{ pageResident: {id,name}|null, setPageResident }`.
  - `useAssistantPageResident(resident)` — `resident` is `{id, name}|null`; publishes it while mounted, clears on unmount.

- [ ] **Step 1: Create the context**

Create `resources/js/contexts/AssistantResidentContext.jsx`:

```jsx
import { createContext, useContext, useEffect, useMemo, useState } from 'react';

const AssistantResidentContext = createContext(null);

export function AssistantResidentProvider({ children }) {
    const [pageResident, setPageResident] = useState(null); // { id, name } | null
    const value = useMemo(() => ({ pageResident, setPageResident }), [pageResident]);
    return <AssistantResidentContext.Provider value={value}>{children}</AssistantResidentContext.Provider>;
}

export function useAssistantResident() {
    const ctx = useContext(AssistantResidentContext);
    if (!ctx) {
        throw new Error('useAssistantResident must be used within AssistantResidentProvider');
    }
    return ctx;
}

// Resident-scoped pages call this to publish the resident they display.
// Pass null when no resident is loaded yet; clears on unmount.
export function useAssistantPageResident(resident) {
    const { setPageResident } = useAssistantResident();
    const id = resident?.id ?? null;
    const name = resident?.name ?? null;
    useEffect(() => {
        if (id && name) {
            setPageResident({ id, name });
        }
        return () => setPageResident(null);
    }, [id, name, setPageResident]);
}
```

- [ ] **Step 2: Wrap the app**

In `resources/js/app.jsx`, add the import near the other context imports:

```javascript
import { AssistantResidentProvider } from './contexts/AssistantResidentContext';
```

Then wrap `<App />` with the provider inside `<BrowserRouter>` (provider tree becomes `… → BrowserRouter → AssistantResidentProvider → App`):

```jsx
                    <BrowserRouter>
                        <AssistantResidentProvider>
                            <App />
                        </AssistantResidentProvider>
                    </BrowserRouter>
```

(Match the existing indentation/JSX in app.jsx around lines 119–120; only add the provider wrapper.)

- [ ] **Step 3: Publish resident from the resident detail page**

In `resources/js/pages/caregiver/ResidentDetailPage.jsx`, add the import:

```javascript
import { useAssistantPageResident } from '../../contexts/AssistantResidentContext';
```

After the resident is available (the existing `const resident = data ?? null;` around line 165), add — at the top level of the component, not inside any condition:

```javascript
    useAssistantPageResident(
        resident
            ? { id: resident.id, name: `${resident.first_name ?? ''} ${resident.last_name ?? ''}`.trim() }
            : null,
    );
```

- [ ] **Step 4: Publish resident from the assessment detail page**

In `resources/js/pages/AssessmentDetail.jsx`, add the import:

```javascript
import { useAssistantPageResident } from '../contexts/AssistantResidentContext';
```

After the assessment is available (the existing `const assessment = data;` around line 307), add at the top level of the component:

```javascript
    const assessmentResident = assessment?.resident ?? null;
    useAssistantPageResident(
        assessmentResident
            ? { id: assessmentResident.id, name: `${assessmentResident.first_name ?? ''} ${assessmentResident.last_name ?? ''}`.trim() }
            : null,
    );
```

Note: `useAssistantPageResident` must be called unconditionally on every render (hooks rule) — it is, because it's called with a possibly-null argument rather than inside an `if`. If `assessment` is only defined after an early `return` (loading/error guard), move this call ABOVE that early return, passing `data?.resident` instead of `assessment.resident`. Read the component's guard structure and place the hook call before any early return.

- [ ] **Step 5: Build to verify**

Run: `npm run build`
Expected: exit 0. Quote the tail.

- [ ] **Step 6: Commit**

```bash
git add resources/js/contexts/AssistantResidentContext.jsx resources/js/app.jsx resources/js/pages/caregiver/ResidentDetailPage.jsx resources/js/pages/AssessmentDetail.jsx
git commit -m "feat(assistant): page-resident context provider + page wiring"
```

---

## Task 5: Widget active-resident + all-meds card

**Files:**
- Modify: `resources/js/hooks/useAssistant.js`
- Create: `resources/js/components/assistant/AllMedsResult.jsx`
- Modify: `resources/js/components/assistant/ChatWidget.jsx`
- Verify: `npm run build`

**Interfaces:**
- Consumes: `useAssistantResident()` (Task 4); reply now includes `resident` + may have `intent: 'all_medications'` with `data: [{id,name,instructions}]`.
- Produces: widget that carries an active resident, sends `context_resident_id`, and renders the all-meds card.

- [ ] **Step 1: Update `useAssistant` to send the context id**

Replace the `useAssistant` mutation in `resources/js/hooks/useAssistant.js` (leave `useStarterSuggestions` unchanged):

```javascript
export function useAssistant() {
    return useMutation({
        mutationFn: async ({ message, contextResidentId }) => {
            const res = await api.post('/assistant/query', {
                message,
                context_resident_id: contextResidentId ?? null,
            });
            // BaseApiController::success() wraps the payload as { data: ... }
            return res.data.data;
        },
    });
}
```

- [ ] **Step 2: Create `AllMedsResult.jsx`**

Create `resources/js/components/assistant/AllMedsResult.jsx`:

```jsx
function titleFromAnswer(answer) {
    const first = (answer || '').split('\n')[0] || 'Medications';
    return first.replace(/:\s*$/, '');
}

export default function AllMedsResult({ answer, meds }) {
    return (
        <div className="mr-auto w-full max-w-[92%] rounded-xl border border-gray-200 bg-white p-3 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <div className="mb-2 text-sm font-semibold text-gray-800 dark:text-gray-100">
                {titleFromAnswer(answer)}
            </div>
            <div className="divide-y divide-gray-100 dark:divide-gray-700">
                {meds.map((m) => (
                    <div key={m.id} className="py-1.5">
                        <div className="text-sm text-gray-800 dark:text-gray-100">{m.name}</div>
                        {m.instructions ? (
                            <div className="text-xs text-gray-400 dark:text-gray-500">{m.instructions}</div>
                        ) : null}
                    </div>
                ))}
            </div>
        </div>
    );
}
```

- [ ] **Step 3: Update `ChatWidget.jsx`** — active resident, context id, seeding, header, all-meds dispatch

Replace `resources/js/components/assistant/ChatWidget.jsx` with:

```jsx
import { useEffect, useState } from 'react';
import { Bot, X, Send, MessageCircle } from 'lucide-react';
import { useAssistant, useStarterSuggestions } from '../../hooks/useAssistant';
import { useAssistantResident } from '../../contexts/AssistantResidentContext';
import Chip from './Chip';
import MedResult from './MedResult';
import PrnResult from './PrnResult';
import AllMedsResult from './AllMedsResult';

function BotContent({ msg }) {
    const hasData = Array.isArray(msg.data) && msg.data.length > 0;
    if (msg.intent === 'todays_meds' && hasData) {
        return <MedResult answer={msg.answer} meds={msg.data} />;
    }
    if (msg.intent === 'all_medications' && hasData) {
        return <AllMedsResult answer={msg.answer} meds={msg.data} />;
    }
    if (msg.intent === 'prn_status' && hasData) {
        return <PrnResult answer={msg.answer} meds={msg.data} />;
    }
    return (
        <div className="mr-auto max-w-[85%] whitespace-pre-line rounded-lg bg-gray-100 px-3 py-2 text-sm text-gray-800 dark:bg-gray-800 dark:text-gray-100">
            {msg.answer}
        </div>
    );
}

function TypingDots() {
    return (
        <div className="mr-auto flex items-center gap-1 px-1 py-2" aria-label="Assistant is typing">
            <span className="h-2 w-2 animate-bounce rounded-full bg-gray-400 [animation-delay:-0.3s]" />
            <span className="h-2 w-2 animate-bounce rounded-full bg-gray-400 [animation-delay:-0.15s]" />
            <span className="h-2 w-2 animate-bounce rounded-full bg-gray-400" />
        </div>
    );
}

export default function ChatWidget() {
    const [open, setOpen] = useState(false);
    const [input, setInput] = useState('');
    const [messages, setMessages] = useState([]);
    const [activeResident, setActiveResident] = useState(null); // { id, name } | null
    const assistant = useAssistant();
    const starter = useStarterSuggestions(open && messages.length === 0);
    const { pageResident } = useAssistantResident();

    // Seed the active resident from the page when opening a fresh chat.
    useEffect(() => {
        if (open && messages.length === 0 && pageResident && !activeResident) {
            setActiveResident(pageResident);
        }
    }, [open, pageResident, messages.length, activeResident]);

    const sendMessage = (text) => {
        const t = (text || '').trim();
        if (!t || assistant.isPending) return;

        setMessages((m) => [...m, { role: 'user', text: t }]);
        setInput('');

        assistant.mutate(
            { message: t, contextResidentId: activeResident?.id ?? null },
            {
                onSuccess: (result) => {
                    if (result.resident) {
                        setActiveResident(result.resident);
                    }
                    setMessages((m) => [
                        ...m,
                        {
                            role: 'bot',
                            intent: result.intent,
                            answer: result.answer,
                            data: result.data ?? [],
                            suggestions: result.suggestions ?? [],
                        },
                    ]);
                },
                onError: () => {
                    setMessages((m) => [
                        ...m,
                        {
                            role: 'bot',
                            intent: 'unknown',
                            answer: "Sorry, I couldn't answer that right now.",
                            data: [],
                            suggestions: [],
                        },
                    ]);
                },
            },
        );
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        sendMessage(input);
    };

    const starterChips = starter.data ?? [];

    return (
        <>
            {open && (
                <div className="fixed inset-y-0 right-0 z-[100] flex w-full flex-col border-l border-gray-200 bg-white shadow-2xl sm:w-[400px] dark:border-gray-700 dark:bg-gray-900">
                    <div className="flex items-center justify-between border-b border-gray-200 px-4 py-3 dark:border-gray-700">
                        <div className="flex items-center gap-2">
                            <span className="flex h-8 w-8 items-center justify-center rounded-full bg-[var(--theme-primary,#2563eb)] text-white">
                                <Bot className="h-5 w-5" />
                            </span>
                            <div className="leading-tight">
                                <div className="text-sm font-semibold text-gray-800 dark:text-gray-100">Assistant</div>
                                {activeResident ? (
                                    <div className="flex items-center gap-1 text-xs text-gray-400">
                                        <span>About: {activeResident.name}</span>
                                        <button
                                            type="button"
                                            onClick={() => setActiveResident(null)}
                                            className="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200"
                                            aria-label="Clear active resident"
                                        >
                                            <X className="h-3 w-3" />
                                        </button>
                                    </div>
                                ) : (
                                    <div className="text-xs text-gray-400">Meds &amp; PRN helper</div>
                                )}
                            </div>
                        </div>
                        <button onClick={() => setOpen(false)} aria-label="Close assistant">
                            <X className="h-5 w-5 text-gray-500" />
                        </button>
                    </div>

                    <div className="flex-1 space-y-3 overflow-y-auto px-4 py-3">
                        {messages.length === 0 && (
                            <div className="space-y-3">
                                <p className="text-sm text-gray-400">
                                    Ask me a resident's meds for today, all their meds, or their PRN meds.
                                </p>
                                {starterChips.length > 0 && (
                                    <div className="flex flex-wrap gap-2">
                                        {starterChips.map((c, i) => (
                                            <Chip
                                                key={i}
                                                label={c.label}
                                                disabled={assistant.isPending}
                                                onClick={() => sendMessage(c.message)}
                                            />
                                        ))}
                                    </div>
                                )}
                            </div>
                        )}

                        {messages.map((m, i) => (
                            <div key={i} className="space-y-2">
                                {m.role === 'user' ? (
                                    <div className="ml-auto max-w-[85%] whitespace-pre-line rounded-lg bg-[var(--theme-primary,#2563eb)] px-3 py-2 text-sm text-white">
                                        {m.text}
                                    </div>
                                ) : (
                                    <BotContent msg={m} />
                                )}
                                {m.role === 'bot' && m.suggestions && m.suggestions.length > 0 && (
                                    <div className="mr-auto flex max-w-[85%] flex-wrap gap-2">
                                        {m.suggestions.map((c, j) => (
                                            <Chip
                                                key={j}
                                                label={c.label}
                                                disabled={assistant.isPending}
                                                onClick={() => sendMessage(c.message)}
                                            />
                                        ))}
                                    </div>
                                )}
                            </div>
                        ))}

                        {assistant.isPending && <TypingDots />}
                    </div>

                    <form onSubmit={handleSubmit} className="flex items-center gap-2 border-t border-gray-200 p-3 dark:border-gray-700">
                        <input
                            value={input}
                            onChange={(e) => setInput(e.target.value)}
                            placeholder="Ask about a resident…"
                            aria-label="Message"
                            className="flex-1 rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"
                        />
                        <button type="submit" disabled={assistant.isPending} aria-label="Send">
                            <Send className="h-5 w-5 text-[var(--theme-primary,#2563eb)]" />
                        </button>
                    </form>
                </div>
            )}

            <button
                onClick={() => setOpen((o) => !o)}
                aria-label="Open assistant"
                className="fixed bottom-6 right-6 z-[100] flex h-14 w-14 items-center justify-center rounded-full bg-[var(--theme-primary,#2563eb)] text-white shadow-lg hover:opacity-90"
            >
                {open ? <X className="h-6 w-6" /> : <MessageCircle className="h-6 w-6" />}
            </button>
        </>
    );
}
```

- [ ] **Step 4: Build to verify**

Run: `npm run build`
Expected: exit 0, no errors referencing `AllMedsResult`, `useAssistantResident`, or `useAssistant`. Quote the tail.

- [ ] **Step 5: Self-review**

Confirm: no `console.log`; `assistant.mutate` is called with an object `{message, contextResidentId}` (matches the hook); active resident set from `result.resident` and from page seed; header shows "About: {name}" with a working clear; `all_medications` renders `AllMedsResult`; single `<ChatWidget />` mount unchanged.

- [ ] **Step 6: Commit**

```bash
git add resources/js/hooks/useAssistant.js resources/js/components/assistant/AllMedsResult.jsx resources/js/components/assistant/ChatWidget.jsx
git commit -m "feat(assistant): active-resident memory, page seeding, all-meds card"
```

---

## Self-Review

- **Spec coverage:** active-resident memory (Task 2 context fallback + `resident` in reply; Task 3 endpoint param; Task 5 client store + send), page seeding (Task 4 context + pages; Task 5 seed effect), `all_medications` (Task 1 parser; Task 2 answer; Task 5 card + chip via Task 2), header indicator + clear (Task 5), tenant re-check of context id (Task 2 `Resident::query()->find` under FacilityScope; test in Task 2), no-PHI (context id is int; named messages redact — unchanged). All spec sections mapped.
- **Type consistency:** `handle(string,User,?int)` matches controller call (Task 3) and tests; reply shape `{intent,answer,data,suggestions,resident}` consistent across service, endpoint (`data.resident.id`), and client (`result.resident`); `useAssistant().mutate({message,contextResidentId})` matches `sendMessage` call in Task 5; `useAssistantPageResident({id,name})` signature matches page callers (Task 4) and provider; `AllMedsResult({answer,meds})` matches `BotContent` usage; med item `{id,name,instructions}` consistent between `answerAllMedications` and `AllMedsResult`.
- **Placeholder scan:** every code step has complete code; the AssessmentDetail early-return note references the concrete existing guard, not a placeholder.
