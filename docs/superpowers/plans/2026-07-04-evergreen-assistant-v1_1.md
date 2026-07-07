# Evergreen Assistant v1.1 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add clickable suggestion chips + a resident-aware starter-chip endpoint to the assistant, and enlarge the widget to a full-height right drawer — fixing the "yes"/free-text dead-end without adding conversation memory.

**Architecture:** `AssistantService::handle()` reply shape gains a `suggestions: [{label, message}]` field; chips carry a ready-to-send phrase that the existing pipeline already classifies correctly (clicking a chip just POSTs that phrase to the unchanged `/assistant/query`). A new `GET /assistant/suggestions` returns starter chips built from real, tenant-scoped residents. The React widget becomes a right-edge drawer that renders chips under bot bubbles and shows starter chips when empty.

**Tech Stack:** Laravel 11, PHPUnit 11, Sanctum, React 19, TanStack Query, axios, Tailwind, lucide-react.

## Global Constraints

- No conversation memory; no new intents (still `todays_meds`, `prn_status`, `unknown`).
- Read-only. No writes anywhere.
- "No PHI to LLM" unchanged: chip messages are sent to `/assistant/query`, which redacts resident names via `ResidentResolver` before any LLM call. Do not bypass that path.
- Chip `message` phrases MUST contain a fast-path keyword so follow-ups never need the LLM: "today's meds for …" (contains `meds`) and "PRN for …" (contains `prn`).
- `GET /assistant/suggestions` enforces the same gates as the query endpoint: `requireModuleAccess(\App\Constants\Modules::MEDICATIONS)` then `requirePermission('view_medications')`, and reads residents through the active `FacilityScope` (no `withoutGlobalScopes()`).
- Response envelope: `BaseApiController::success($data)` returns `{ data: $data }`. Frontend unwraps `res.data.data`.
- PHP: `declare(strict_types=1);`, PSR-12, typed signatures. Do NOT run pint manually (auto-runs on save).
- Tests: PHPUnit class style, `RefreshDatabase`, `Laravel\Sanctum\Sanctum::actingAs`. Frontend has no JS test runner — verify with `npm run build`.

---

## File Structure

**Modify (backend):**
- `app/Services/AssistantService.php` — `reply()` gains a `suggestions` param; `handle()` attaches chips on the unclear-single-resident and ambiguous branches; add `starterSuggestions()` + a private `choiceSuggestions()` helper.
- `app/Http/Controllers/Api/AssistantController.php` — add `suggestions()` action.
- `routes/api.php` — add `GET /assistant/suggestions` in the v1 group.

**Modify (frontend):**
- `resources/js/hooks/useAssistant.js` — add `useStarterSuggestions`.
- `resources/js/components/ChatWidget.jsx` — right-edge drawer; render chips + starter chips.

**Test files:**
- `tests/Feature/AssistantServiceTest.php` (extend)
- `tests/Feature/AssistantSuggestionsEndpointTest.php` (new)

---

## Task 1: Backend suggestions in AssistantService

**Files:**
- Modify: `app/Services/AssistantService.php`
- Test: `tests/Feature/AssistantServiceTest.php`

**Interfaces:**
- Consumes: existing `ResidentResolver`, `IntentParsingService`, `MedicationService`; `Resident` (FacilityScope), `App\Models\User`.
- Produces:
  - `handle(string $message, User $user): array` now returns `['intent'=>string,'answer'=>string,'data'=>array,'suggestions'=>array]` where each suggestion is `['label'=>string,'message'=>string]`.
  - `starterSuggestions(User $user, int $limit = 3): array` — list of `['label'=>string,'message'=>string]` built from tenant-scoped residents (may be empty).

- [ ] **Step 1: Write the failing tests** (append to `tests/Feature/AssistantServiceTest.php`, inside the class)

```php
    public function test_unclear_intent_returns_meds_and_prn_chips(): void
    {
        Http::fake(); // faked LLM returns empty -> parse() => 'unknown' -> chips
        [, $branch, $user, $resident] = $this->seedFixtures();

        $result = app(AssistantService::class)->handle('mary smith', $user);

        $this->assertSame('unknown', $result['intent']);
        $this->assertCount(2, $result['suggestions']);
        $labels = array_column($result['suggestions'], 'label');
        $this->assertContains("Today's meds", $labels);
        $this->assertContains('PRN', $labels);

        // Each chip message must round-trip to the right intent via the pipeline.
        $byLabel = collect($result['suggestions'])->keyBy('label');
        $meds = app(AssistantService::class)->handle($byLabel["Today's meds"]['message'], $user);
        $this->assertSame('todays_meds', $meds['intent']);
        $prn = app(AssistantService::class)->handle($byLabel['PRN']['message'], $user);
        $this->assertSame('prn_status', $prn['intent']);
    }

    public function test_ambiguous_returns_one_chip_per_resident(): void
    {
        Http::fake();
        [, $branch, $user] = $this->seedFixtures();
        \App\Models\Resident::factory()->create([
            'branch_id' => $branch->id, 'first_name' => 'Mary', 'last_name' => 'Taylor',
        ]);

        $result = app(AssistantService::class)->handle('meds for Mary', $user);

        // Two residents named Mary -> ambiguous -> one chip each.
        $this->assertCount(2, $result['suggestions']);
        foreach ($result['suggestions'] as $s) {
            $this->assertStringContainsString("today's meds for", strtolower($s['message']));
        }
    }

    public function test_normal_answer_has_empty_suggestions(): void
    {
        Http::fake();
        [, $branch, $user, $resident] = $this->seedFixtures();
        $this->makeMedication($resident, $branch);

        $result = app(AssistantService::class)->handle('what meds is Mary Smith on today', $user);

        $this->assertSame('todays_meds', $result['intent']);
        $this->assertSame([], $result['suggestions']);
    }

    public function test_starter_suggestions_builds_resident_prompts(): void
    {
        [, $branch, $user] = $this->seedFixtures(); // seedFixtures creates resident "Mary Smith"
        \App\Models\Resident::factory()->create([
            'branch_id' => $branch->id, 'first_name' => 'Anna', 'last_name' => 'Bell',
        ]);

        $chips = app(AssistantService::class)->starterSuggestions($user, 3);

        $this->assertNotEmpty($chips);
        $this->assertLessThanOrEqual(3, count($chips));
        foreach ($chips as $c) {
            $this->assertArrayHasKey('label', $c);
            $this->assertArrayHasKey('message', $c);
            $this->assertMatchesRegularExpression("/^(today's meds for|PRN for) /i", $c['message']);
        }
    }
```

Note: `seedFixtures()` and `makeMedication()` already exist in this test file from v1 — reuse them. `seedFixtures()` returns `[$facility, $branch, $user, $resident]` with a resident named "Mary Smith" and calls `Sanctum::actingAs($user)`. If the exact tuple/name differs, adapt these tests to the existing helper's actual shape (read the top of the file first).

- [ ] **Step 2: Run tests to verify they fail**

Run: `vendor/bin/phpunit tests/Feature/AssistantServiceTest.php`
Expected: FAIL — `suggestions` key missing / `starterSuggestions` not defined.

- [ ] **Step 3: Update `reply()` to carry suggestions**

In `app/Services/AssistantService.php`, replace the `reply()` method:

```php
    /**
     * @param  array<int, array{label: string, message: string}>  $suggestions
     * @return array{intent: string, answer: string, data: array, suggestions: array}
     */
    private function reply(string $intent, string $answer, array $data = [], array $suggestions = []): array
    {
        return ['intent' => $intent, 'answer' => $answer, 'data' => $data, 'suggestions' => $suggestions];
    }
```

- [ ] **Step 4: Update the two answer methods to include an empty `suggestions` key**

In `answerTodaysMeds()`, change the final `return [...]` to include `'suggestions' => []`:

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
        ];
```

In `answerPrn()`, change the final `return [...]` similarly:

```php
        return [
            'intent' => 'prn_status',
            'answer' => $answer,
            'data' => $data->all(),
            'suggestions' => [],
        ];
```

(The early `reply('todays_meds', ...)` / `reply('prn_status', ...)` "no meds" returns already go through `reply()`, which now defaults `suggestions` to `[]` — no change needed there.)

- [ ] **Step 5: Attach chips on the unclear + ambiguous branches**

In `handle()`, update the ambiguous branch:

```php
        if ($resolution->status === 'ambiguous') {
            $names = collect($resolution->matches)->map(fn ($r) => $this->label($r))->implode(', ');
            $suggestions = collect($resolution->matches)->map(fn ($r) => [
                'label' => $this->label($r),
                'message' => "today's meds for {$this->label($r)}",
            ])->all();

            return $this->reply('unknown', "More than one resident matches that name. Which one did you mean: {$names}?", [], $suggestions);
        }
```

And update the `default =>` arm of the `match ($intent)` (the single-resident-unclear case):

```php
        return match ($intent) {
            'todays_meds' => $this->answerTodaysMeds($resident),
            'prn_status' => $this->answerPrn($resident),
            default => $this->reply(
                'unknown',
                "I can help with {$this->label($resident)}'s meds for today, or their PRN meds. Which would you like?",
                [],
                $this->choiceSuggestions($resident),
            ),
        };
```

- [ ] **Step 6: Add the `choiceSuggestions()` and `starterSuggestions()` methods**

Add these methods to the class (e.g. after `label()`):

```php
    /**
     * @return array<int, array{label: string, message: string}>
     */
    private function choiceSuggestions(Resident $resident): array
    {
        $name = $this->label($resident);

        return [
            ['label' => "Today's meds", 'message' => "today's meds for {$name}"],
            ['label' => 'PRN', 'message' => "PRN for {$name}"],
        ];
    }

    /**
     * Starter chips built from residents the user can see (FacilityScope active).
     *
     * @return array<int, array{label: string, message: string}>
     */
    public function starterSuggestions(User $user, int $limit = 3): array
    {
        $residents = Resident::query()->orderBy('first_name')->limit($limit)->get();

        $chips = [];
        foreach ($residents->values() as $i => $resident) {
            $name = $this->label($resident);
            if ($i % 2 === 0) {
                $chips[] = ['label' => "Today's meds for {$name}", 'message' => "today's meds for {$name}"];
            } else {
                $chips[] = ['label' => "PRN for {$name}", 'message' => "PRN for {$name}"];
            }
        }

        return $chips;
    }
```

Also update the `handle()` docblock:

```php
    /**
     * @return array{intent: string, answer: string, data: array, suggestions: array}
     */
```

- [ ] **Step 7: Run tests to verify they pass**

Run: `vendor/bin/phpunit tests/Feature/AssistantServiceTest.php`
Expected: PASS (v1 tests + 4 new). If a v1 test asserted an exact array shape without `suggestions`, update it to expect the new key.

- [ ] **Step 8: Commit**

```bash
git add app/Services/AssistantService.php tests/Feature/AssistantServiceTest.php
git commit -m "feat(assistant): add suggestion chips + starter suggestions to service"
```

---

## Task 2: Suggestions endpoint

**Files:**
- Modify: `app/Http/Controllers/Api/AssistantController.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/AssistantSuggestionsEndpointTest.php`

**Interfaces:**
- Consumes: `AssistantService::starterSuggestions(User, int): array` (Task 1); `BaseApiController` gates + `success()`.
- Produces: `GET /api/v1/assistant/suggestions` → `{ data: { suggestions: [{label, message}] } }`.

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/AssistantSuggestionsEndpointTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Constants\Modules;
use App\Models\Branch;
use App\Models\Facility;
use App\Models\Resident;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AssistantSuggestionsEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/v1/assistant/suggestions')->assertUnauthorized();
    }

    public function test_returns_resident_based_suggestions(): void
    {
        $facility = Facility::factory()->create();
        $branch = Branch::factory()->create(['facility_id' => $facility->id]);
        $user = User::factory()->create(['facility_id' => $facility->id, 'role' => 'admin']);
        Resident::factory()->create(['branch_id' => $branch->id, 'first_name' => 'Mary', 'last_name' => 'Smith']);
        Sanctum::actingAs($user);

        $res = $this->getJson('/api/v1/assistant/suggestions')->assertOk();
        $suggestions = $res->json('data.suggestions');

        $this->assertIsArray($suggestions);
        $this->assertLessThanOrEqual(3, count($suggestions));
        $this->assertNotEmpty($suggestions);
        $this->assertArrayHasKey('label', $suggestions[0]);
        $this->assertArrayHasKey('message', $suggestions[0]);
    }

    public function test_does_not_leak_residents_from_another_facility(): void
    {
        $facility = Facility::factory()->create();
        $branch = Branch::factory()->create(['facility_id' => $facility->id]);
        $user = User::factory()->create(['facility_id' => $facility->id, 'role' => 'admin']);

        $other = Facility::factory()->create();
        $otherBranch = Branch::factory()->create(['facility_id' => $other->id]);
        Resident::factory()->create(['branch_id' => $otherBranch->id, 'first_name' => 'Zebedee', 'last_name' => 'Cross']);

        Sanctum::actingAs($user);

        $res = $this->getJson('/api/v1/assistant/suggestions')->assertOk();
        $body = json_encode($res->json('data.suggestions'));

        $this->assertStringNotContainsString('Zebedee', $body);
    }

    public function test_denies_when_medications_module_disabled(): void
    {
        $facility = Facility::factory()->create();
        $branch = Branch::factory()->create(['facility_id' => $facility->id]);
        $user = User::factory()->create(['facility_id' => $facility->id, 'role' => 'admin']);
        $facility->disableModule(Modules::MEDICATIONS);
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/assistant/suggestions')->assertForbidden();
    }
}
```

Note: `test_denies_when_medications_module_disabled` mirrors the setup used in `tests/Feature/AssistantEndpointTest.php` (`$facility->disableModule(...)` with an `admin` — branch-level admin — user). If the helper name differs, copy the exact deny-path arrange block from that file.

- [ ] **Step 2: Run tests to verify they fail**

Run: `vendor/bin/phpunit tests/Feature/AssistantSuggestionsEndpointTest.php`
Expected: FAIL — route not defined (401 test may pass incidentally via the SPA catch-all; the `assertOk`/`assertForbidden` tests fail).

- [ ] **Step 3: Add the controller action**

In `app/Http/Controllers/Api/AssistantController.php`, add the method inside the class:

```php
    public function suggestions(Request $request, AssistantService $assistant): JsonResponse
    {
        if ($error = $this->requireModuleAccess(Modules::MEDICATIONS)) {
            return $error;
        }

        if ($error = $this->requirePermission('view_medications')) {
            return $error;
        }

        return $this->success([
            'suggestions' => $assistant->starterSuggestions($request->user()),
        ]);
    }
```

- [ ] **Step 4: Add the route**

In `routes/api.php`, directly below the existing `/assistant/query` route (added in v1), add:

```php
    Route::get('/assistant/suggestions', [\App\Http\Controllers\Api\AssistantController::class, 'suggestions'])
        ->middleware(['auth:sanctum', 'throttle:60,1']);
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `vendor/bin/phpunit tests/Feature/AssistantSuggestionsEndpointTest.php`
Expected: PASS (4 tests).

- [ ] **Step 6: Run the full assistant suite**

Run: `vendor/bin/phpunit --filter Assistant`
Expected: all assistant tests green.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/Api/AssistantController.php routes/api.php tests/Feature/AssistantSuggestionsEndpointTest.php
git commit -m "feat(assistant): add starter suggestions endpoint"
```

---

## Task 3: Frontend drawer + chips

**Files:**
- Modify: `resources/js/hooks/useAssistant.js`
- Modify: `resources/js/components/ChatWidget.jsx`
- Verify: `npm run build`

**Interfaces:**
- Consumes: `POST /assistant/query` → `{ data: { intent, answer, data, suggestions } }`; `GET /assistant/suggestions` → `{ data: { suggestions } }`.
- Produces: a full-height right-drawer `<ChatWidget />` rendering bot answers, per-message chips, and starter chips.

- [ ] **Step 1: Add the starter-suggestions query hook**

Replace `resources/js/hooks/useAssistant.js` with:

```javascript
import { useMutation, useQuery } from '@tanstack/react-query';
import api from '../services/api';

// POSTs a message to the assistant endpoint and returns { intent, answer, data, suggestions }.
export function useAssistant() {
    return useMutation({
        mutationFn: async (message) => {
            const res = await api.post('/assistant/query', { message });
            // BaseApiController::success() wraps the payload as { data: ... }
            return res.data.data;
        },
    });
}

// Fetches resident-aware starter chips for the empty state. Only runs when enabled.
export function useStarterSuggestions(enabled) {
    return useQuery({
        queryKey: ['assistant', 'starter-suggestions'],
        queryFn: async () => {
            const res = await api.get('/assistant/suggestions');
            return res.data.data.suggestions ?? [];
        },
        enabled,
        staleTime: 5 * 60 * 1000,
    });
}
```

- [ ] **Step 2: Rewrite ChatWidget as a drawer with chips**

Replace `resources/js/components/ChatWidget.jsx` with:

```jsx
import { useState } from 'react';
import { MessageCircle, X, Send } from 'lucide-react';
import { useAssistant, useStarterSuggestions } from '../hooks/useAssistant';

function Chip({ label, onClick, disabled }) {
    return (
        <button
            type="button"
            onClick={onClick}
            disabled={disabled}
            className="rounded-full border border-[var(--theme-primary,#2563eb)] px-3 py-1 text-xs font-medium text-[var(--theme-primary,#2563eb)] hover:bg-[var(--theme-primary,#2563eb)] hover:text-white disabled:opacity-50"
        >
            {label}
        </button>
    );
}

export default function ChatWidget() {
    const [open, setOpen] = useState(false);
    const [input, setInput] = useState('');
    const [messages, setMessages] = useState([]); // { role: 'user'|'bot', text, suggestions? }
    const assistant = useAssistant();
    const starter = useStarterSuggestions(open && messages.length === 0);

    const sendMessage = (text) => {
        const t = (text || '').trim();
        if (!t || assistant.isPending) return;

        setMessages((m) => [...m, { role: 'user', text: t }]);
        setInput('');

        assistant.mutate(t, {
            onSuccess: (result) => {
                setMessages((m) => [
                    ...m,
                    { role: 'bot', text: result.answer, suggestions: result.suggestions ?? [] },
                ]);
            },
            onError: () => {
                setMessages((m) => [
                    ...m,
                    { role: 'bot', text: "Sorry, I couldn't answer that right now." },
                ]);
            },
        });
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
                        <span className="font-semibold text-gray-800 dark:text-gray-100">Assistant</span>
                        <button onClick={() => setOpen(false)} aria-label="Close assistant">
                            <X className="h-5 w-5 text-gray-500" />
                        </button>
                    </div>

                    <div className="flex-1 space-y-3 overflow-y-auto px-4 py-3">
                        {messages.length === 0 && (
                            <div className="space-y-3">
                                <p className="text-sm text-gray-400">
                                    Ask me a resident's meds for today, or their PRN meds.
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
                                <div
                                    className={
                                        m.role === 'user'
                                            ? 'ml-auto max-w-[85%] whitespace-pre-line rounded-lg bg-[var(--theme-primary,#2563eb)] px-3 py-2 text-sm text-white'
                                            : 'mr-auto max-w-[85%] whitespace-pre-line rounded-lg bg-gray-100 px-3 py-2 text-sm text-gray-800 dark:bg-gray-800 dark:text-gray-100'
                                    }
                                >
                                    {m.text}
                                </div>
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

                        {assistant.isPending && <p className="text-xs text-gray-400">Thinking…</p>}
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

- [ ] **Step 3: Build to verify it compiles**

Run: `npm run build`
Expected: exit 0, no errors referencing `ChatWidget`, `useAssistant`, `useStarterSuggestions`, or `lucide-react`. Quote the tail.

- [ ] **Step 4: Self-review**

Confirm: no `console.log`; the drawer is full-height right-edge; chips call `sendMessage`; starter query only enabled when open+empty; single mount point in `Layout.jsx` unchanged (still one `<ChatWidget />`).

- [ ] **Step 5: Commit**

```bash
git add resources/js/hooks/useAssistant.js resources/js/components/ChatWidget.jsx
git commit -m "feat(assistant): full-height drawer with suggestion + starter chips"
```

---

## Self-Review

- **Spec coverage:** chips mechanism (Task 1 `reply`+`choiceSuggestions`, `handle` branches), ambiguous chips (Task 1), starter endpoint (Task 2), resident-aware starter chips (Task 1 `starterSuggestions` + Task 2), drawer + chip rendering + starter chips (Task 3), gates + tenant isolation on suggestions (Task 2 tests), no-PHI path unchanged (chips route through `/assistant/query` → `ResidentResolver`), empty suggestions on normal answers (Task 1 test). All spec sections mapped.
- **Type consistency:** suggestion shape `{label, message}` identical across `choiceSuggestions`, `starterSuggestions`, endpoint, and frontend; `handle()` return `{intent, answer, data, suggestions}` consistent between service, tests, and frontend unwrap; `starterSuggestions(User, int)` signature matches controller call.
- **Placeholder scan:** every code step has full code; the two "adapt to existing helper" notes reference concrete existing files/helpers, not placeholders.
