# Evergreen Assistant Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a read-only, staff-facing chat assistant to Evergreen that answers "today's meds for resident X" and "PRN status for resident X" from existing data, with no PHI sent to any external LLM.

**Architecture:** A floating React widget POSTs a plain-English message to `POST /api/v1/assistant/query`. The backend resolves resident names locally (tenant-scoped) and redacts them to a `{RESIDENT}` token before an LLM ever sees the text; the LLM only classifies intent (`todays_meds` / `prn_status` / `unknown`). Answers are built locally by reusing the existing `MedicationService`. All reads pass through the existing `FacilityScope` + module/permission gates, so isolation is automatic.

**Tech Stack:** Laravel 11, PHPUnit 11, Laravel Sanctum, `Http` facade (Anthropic API), React 19 + React Router v7, TanStack Query, Tailwind, axios.

## Global Constraints

- Read-only. No task may create, update, or delete resident/medication data.
- No PHI (resident names, medication data, identifiers) may be sent to the LLM. Only name-redacted intent text is transmitted. The LLM is called ONLY when a resident was matched and their name was redacted.
- Every backend read must go through models carrying the global `FacilityScope` (do NOT use `withoutGlobalScopes()`).
- Endpoint must enforce `requireModuleAccess(\App\Constants\Modules::MEDICATIONS)` and `requirePermission('view_medications')`.
- Intent enum is exactly: `todays_meds`, `prn_status`, `unknown`. Nothing else.
- LLM/parse failures fail closed to `unknown` — never to a guessed or unscoped answer.
- PHP: `declare(strict_types=1);`, PSR-12, typed signatures. Pint runs automatically on save — do not run it manually.
- Tests: PHPUnit class style (`namespace Tests\Feature;` / `Tests\Unit;`, `extends TestCase`), `use RefreshDatabase` for DB tests, `Laravel\Sanctum\Sanctum::actingAs(...)` for auth.
- Anthropic model default: `claude-haiku-4-5-20251001`.

---

## File Structure

**Backend (new):**
- `app/Services/IntentParsingService.php` — regex fast-path + Anthropic fallback; returns an intent enum string.
- `app/Services/ResidentResolution.php` — readonly DTO: matched residents + redacted text + status.
- `app/Services/ResidentResolver.php` — local, tenant-scoped resident name matching + redaction.
- `app/Services/AssistantService.php` — orchestration: resolve → parse → fetch → format.
- `app/Http/Controllers/Api/AssistantController.php` — transport + gates.

**Backend (modified):**
- `config/services.php` — add `anthropic` block.
- `.env.example` — add `ANTHROPIC_API_KEY`, `ANTHROPIC_MODEL`.
- `routes/api.php` — add the route inside the existing `v1` group.

**Frontend (new):**
- `resources/js/hooks/useAssistant.js` — TanStack mutation calling the API.
- `resources/js/components/ChatWidget.jsx` — floating button + panel.

**Frontend (modified):**
- `resources/js/components/Layout.jsx` — mount `<ChatWidget />` once.

**Tests (new):**
- `tests/Unit/IntentParsingServiceTest.php`
- `tests/Feature/ResidentResolverTest.php`
- `tests/Feature/AssistantServiceTest.php`
- `tests/Feature/AssistantEndpointTest.php`

---

## Task 1: IntentParsingService (+ Anthropic config)

**Files:**
- Create: `app/Services/IntentParsingService.php`
- Modify: `config/services.php`
- Modify: `.env.example`
- Test: `tests/Unit/IntentParsingServiceTest.php`

**Interfaces:**
- Consumes: nothing from other tasks. Reads `config('services.anthropic.*')`. Uses `Illuminate\Support\Facades\Http`.
- Produces:
  - `IntentParsingService::fastPath(string $text): ?string` — regex-only; returns `'todays_meds'` | `'prn_status'` | `null` (no confident local match). Never calls the network.
  - `IntentParsingService::parse(string $redactedText): string` — `fastPath()` first; on `null` calls the LLM; always returns one of `'todays_meds'` | `'prn_status'` | `'unknown'`.

- [ ] **Step 1: Add Anthropic config**

In `config/services.php`, add this entry inside the returned array (alongside `postmark`, `resend`, etc.):

```php
'anthropic' => [
    'api_key' => env('ANTHROPIC_API_KEY'),
    'model' => env('ANTHROPIC_MODEL', 'claude-haiku-4-5-20251001'),
],
```

In `.env.example`, add near the other service keys:

```
ANTHROPIC_API_KEY=
ANTHROPIC_MODEL=claude-haiku-4-5-20251001
```

- [ ] **Step 2: Write the failing tests**

Create `tests/Unit/IntentParsingServiceTest.php`:

```php
<?php

namespace Tests\Unit;

use App\Services\IntentParsingService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class IntentParsingServiceTest extends TestCase
{
    private function service(): IntentParsingService
    {
        return new IntentParsingService();
    }

    public function test_fastpath_detects_prn(): void
    {
        $this->assertSame('prn_status', $this->service()->fastPath('any prn for {RESIDENT}'));
    }

    public function test_fastpath_detects_todays_meds(): void
    {
        $this->assertSame('todays_meds', $this->service()->fastPath('what meds is {RESIDENT} on today'));
    }

    public function test_fastpath_returns_null_when_unclear(): void
    {
        $this->assertNull($this->service()->fastPath('hello there'));
    }

    public function test_parse_uses_fastpath_without_calling_llm(): void
    {
        Http::fake(); // any real call would be recorded
        $result = $this->service()->parse('prn for {RESIDENT}');
        $this->assertSame('prn_status', $result);
        Http::assertNothingSent();
    }

    public function test_parse_calls_llm_when_fastpath_misses(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [['type' => 'text', 'text' => 'todays_meds']],
            ], 200),
        ]);
        $result = $this->service()->parse('give me the rundown for {RESIDENT}');
        $this->assertSame('todays_meds', $result);
        Http::assertSent(fn ($req) => str_contains($req->url(), 'api.anthropic.com'));
    }

    public function test_parse_fails_closed_to_unknown_on_llm_error(): void
    {
        Http::fake(['api.anthropic.com/*' => Http::response('', 500)]);
        $this->assertSame('unknown', $this->service()->parse('mumble mumble {RESIDENT}'));
    }

    public function test_parse_maps_unexpected_llm_output_to_unknown(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [['type' => 'text', 'text' => 'banana']],
            ], 200),
        ]);
        $this->assertSame('unknown', $this->service()->parse('mumble {RESIDENT}'));
    }
}
```

- [ ] **Step 3: Run tests to verify they fail**

Run: `vendor/bin/phpunit tests/Unit/IntentParsingServiceTest.php`
Expected: FAIL — class `App\Services\IntentParsingService` not found.

- [ ] **Step 4: Write the implementation**

Create `app/Services/IntentParsingService.php`:

```php
<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class IntentParsingService
{
    private const INTENTS = ['todays_meds', 'prn_status', 'unknown'];

    private const SYSTEM_PROMPT =
        "You classify a care-home staff request into exactly one label: "
        ."todays_meds (they want a resident's medications scheduled for today), "
        ."prn_status (they want a resident's as-needed/PRN medications), or "
        ."unknown (anything else). The resident's name is redacted as {RESIDENT}. "
        ."Reply with ONLY the label, nothing else.";

    /**
     * Local regex classification. Returns null when no confident match.
     */
    public function fastPath(string $text): ?string
    {
        $t = strtolower($text);

        if (preg_match('/\bprn\b|as needed/', $t) === 1) {
            return 'prn_status';
        }

        if (preg_match('/\b(med|meds|medication|medications|tablet|tablets|drug|drugs|dose|doses)\b/', $t) === 1) {
            return 'todays_meds';
        }

        return null;
    }

    /**
     * Classify intent. Uses the local fast-path first; falls back to the LLM.
     * Always returns a valid enum value; fails closed to 'unknown'.
     */
    public function parse(string $redactedText): string
    {
        $fast = $this->fastPath($redactedText);
        if ($fast !== null) {
            return $fast;
        }

        return $this->classifyWithLlm($redactedText);
    }

    private function classifyWithLlm(string $redactedText): string
    {
        $apiKey = config('services.anthropic.api_key');
        if (empty($apiKey)) {
            return 'unknown';
        }

        try {
            $response = Http::withHeaders([
                'x-api-key' => $apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ])->timeout(10)->post('https://api.anthropic.com/v1/messages', [
                'model' => config('services.anthropic.model', 'claude-haiku-4-5-20251001'),
                'max_tokens' => 16,
                'system' => self::SYSTEM_PROMPT,
                'messages' => [
                    ['role' => 'user', 'content' => $redactedText],
                ],
            ]);

            if ($response->failed()) {
                return 'unknown';
            }

            $label = strtolower(trim((string) $response->json('content.0.text', '')));

            return in_array($label, self::INTENTS, true) ? $label : 'unknown';
        } catch (\Throwable $e) {
            Log::warning('Assistant intent parse failed', ['error' => $e->getMessage()]);

            return 'unknown';
        }
    }
}
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `vendor/bin/phpunit tests/Unit/IntentParsingServiceTest.php`
Expected: PASS (7 tests).

- [ ] **Step 6: Commit**

```bash
git add app/Services/IntentParsingService.php config/services.php .env.example tests/Unit/IntentParsingServiceTest.php
git commit -m "feat(assistant): add intent parsing service with LLM fallback"
```

---

## Task 2: ResidentResolver + DTO

**Files:**
- Create: `app/Services/ResidentResolution.php`
- Create: `app/Services/ResidentResolver.php`
- Test: `tests/Feature/ResidentResolverTest.php`

**Interfaces:**
- Consumes: `App\Models\Resident` (carries `FacilityScope`), `App\Models\User`.
- Produces:
  - `ResidentResolution` readonly DTO with public properties:
    - `array $matches` (list of `App\Models\Resident`, distinct by id)
    - `string $redacted` (message with every matched resident name replaced by `{RESIDENT}`)
    - `string $status` (`'none'` | `'one'` | `'ambiguous'`)
  - `ResidentResolver::resolve(string $message, \App\Models\User $user): ResidentResolution`

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/ResidentResolverTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Facility;
use App\Models\Resident;
use App\Models\User;
use App\Services\ResidentResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ResidentResolverTest extends TestCase
{
    use RefreshDatabase;

    private function seedFacilityUser(): array
    {
        $facility = Facility::factory()->create();
        $branch = Branch::factory()->create(['facility_id' => $facility->id]);
        $user = User::factory()->create([
            'facility_id' => $facility->id,
            'role' => 'admin',
        ]);

        return [$facility, $branch, $user];
    }

    public function test_matches_single_resident_and_redacts_name(): void
    {
        [$facility, $branch, $user] = $this->seedFacilityUser();
        Resident::factory()->create([
            'branch_id' => $branch->id,
            'first_name' => 'Mary',
            'last_name' => 'Smith',
        ]);
        Sanctum::actingAs($user);

        $resolution = app(ResidentResolver::class)->resolve('what meds is Mary Smith on today', $user);

        $this->assertSame('one', $resolution->status);
        $this->assertCount(1, $resolution->matches);
        $this->assertStringContainsString('{RESIDENT}', $resolution->redacted);
        $this->assertStringNotContainsStringIgnoringCase('Mary', $resolution->redacted);
    }

    public function test_no_match_returns_none_status(): void
    {
        [$facility, $branch, $user] = $this->seedFacilityUser();
        Sanctum::actingAs($user);

        $resolution = app(ResidentResolver::class)->resolve('what meds are due', $user);

        $this->assertSame('none', $resolution->status);
        $this->assertCount(0, $resolution->matches);
    }

    public function test_two_distinct_residents_is_ambiguous(): void
    {
        [$facility, $branch, $user] = $this->seedFacilityUser();
        Resident::factory()->create(['branch_id' => $branch->id, 'first_name' => 'Mary', 'last_name' => 'Smith']);
        Resident::factory()->create(['branch_id' => $branch->id, 'first_name' => 'Mary', 'last_name' => 'Taylor']);
        Sanctum::actingAs($user);

        $resolution = app(ResidentResolver::class)->resolve('meds for Mary', $user);

        $this->assertSame('ambiguous', $resolution->status);
        $this->assertCount(2, $resolution->matches);
    }

    public function test_does_not_match_resident_from_another_facility(): void
    {
        [$facility, $branch, $user] = $this->seedFacilityUser();

        $otherFacility = Facility::factory()->create();
        $otherBranch = Branch::factory()->create(['facility_id' => $otherFacility->id]);
        Resident::factory()->create(['branch_id' => $otherBranch->id, 'first_name' => 'Bob', 'last_name' => 'Jones']);

        Sanctum::actingAs($user);

        $resolution = app(ResidentResolver::class)->resolve('meds for Bob Jones today', $user);

        $this->assertSame('none', $resolution->status);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `vendor/bin/phpunit tests/Feature/ResidentResolverTest.php`
Expected: FAIL — `App\Services\ResidentResolver` not found.

Note: if `Branch::factory()` or `Facility::factory()` does not exist, check `database/factories/` and adapt the seed helper to how existing feature tests (e.g. `tests/Feature/AccessScopeTest.php`) build a facility + branch + user. Mirror that test's setup exactly.

- [ ] **Step 3: Write the DTO**

Create `app/Services/ResidentResolution.php`:

```php
<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Resident;

final class ResidentResolution
{
    /**
     * @param  array<int, Resident>  $matches
     */
    public function __construct(
        public readonly array $matches,
        public readonly string $redacted,
        public readonly string $status,
    ) {}
}
```

- [ ] **Step 4: Write the resolver**

Create `app/Services/ResidentResolver.php`:

```php
<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Resident;
use App\Models\User;

class ResidentResolver
{
    /**
     * Match resident names in the message against residents the user may see
     * (FacilityScope applies automatically), and redact matched names.
     */
    public function resolve(string $message, User $user): ResidentResolution
    {
        // FacilityScope limits this to the user's facility/branch automatically.
        $residents = Resident::query()->get(['id', 'first_name', 'last_name', 'branch_id']);

        $matched = [];
        $redacted = $message;

        foreach ($residents as $resident) {
            $full = trim(($resident->first_name ?? '').' '.($resident->last_name ?? ''));
            $candidates = array_filter([
                $full,
                $resident->first_name,
                $resident->last_name,
            ]);

            // Longest candidate first so "Mary Smith" redacts before "Mary".
            usort($candidates, fn ($a, $b) => strlen((string) $b) <=> strlen((string) $a));

            foreach ($candidates as $candidate) {
                $pattern = '/\b'.preg_quote((string) $candidate, '/').'\b/i';
                if (preg_match($pattern, $redacted) === 1) {
                    $matched[$resident->id] = $resident;
                    $redacted = preg_replace($pattern, '{RESIDENT}', $redacted);
                    break; // one hit per resident is enough
                }
            }
        }

        $matches = array_values($matched);
        $status = match (true) {
            count($matches) === 0 => 'none',
            count($matches) === 1 => 'one',
            default => 'ambiguous',
        };

        return new ResidentResolution($matches, $redacted, $status);
    }
}
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `vendor/bin/phpunit tests/Feature/ResidentResolverTest.php`
Expected: PASS (4 tests).

- [ ] **Step 6: Commit**

```bash
git add app/Services/ResidentResolution.php app/Services/ResidentResolver.php tests/Feature/ResidentResolverTest.php
git commit -m "feat(assistant): add tenant-scoped resident resolver with name redaction"
```

---

## Task 3: AssistantService (orchestration + answer formatting)

**Files:**
- Create: `app/Services/AssistantService.php`
- Test: `tests/Feature/AssistantServiceTest.php`

**Interfaces:**
- Consumes:
  - `ResidentResolver::resolve()` → `ResidentResolution` (Task 2)
  - `IntentParsingService::fastPath()` / `parse()` (Task 1)
  - `MedicationService::getMedicationsWithStatus(Collection $meds, $date): Collection` (existing)
  - `App\Models\MedicationAdministration` (existing), `App\Models\Resident::medicationOrders()` (existing HasMany)
- Produces:
  - `AssistantService::handle(string $message, \App\Models\User $user): array` returning
    `['intent' => string, 'answer' => string, 'data' => array]`.

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/AssistantServiceTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Drug;
use App\Models\Facility;
use App\Models\Medication;
use App\Models\MedicationAdministration;
use App\Models\Resident;
use App\Models\User;
use App\Services\AssistantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AssistantServiceTest extends TestCase
{
    use RefreshDatabase;

    private function seed(): array
    {
        $facility = Facility::factory()->create();
        $branch = Branch::factory()->create(['facility_id' => $facility->id]);
        $user = User::factory()->create(['facility_id' => $facility->id, 'role' => 'admin']);
        $resident = Resident::factory()->create([
            'branch_id' => $branch->id,
            'first_name' => 'Mary',
            'last_name' => 'Smith',
        ]);
        Sanctum::actingAs($user);

        return [$facility, $branch, $user, $resident];
    }

    private function makeMedication(Resident $resident, Branch $branch, array $overrides = []): Medication
    {
        $drug = Drug::factory()->create();

        return Medication::create(array_merge([
            'resident_id' => $resident->id,
            'branch_id' => $branch->id,
            'drug_id' => $drug->id,
            'name' => 'Paracetamol 500mg',
            'instructions' => 't.i.d',
            'time_1' => '08:00',
            'is_active' => true,
            'start_date' => now()->subDay()->toDateString(),
        ], $overrides));
    }

    public function test_todays_meds_lists_active_medication(): void
    {
        Http::fake();
        [, $branch, $user, $resident] = $this->seed();
        $this->makeMedication($resident, $branch);

        $result = app(AssistantService::class)->handle('what meds is Mary Smith on today', $user);

        $this->assertSame('todays_meds', $result['intent']);
        $this->assertStringContainsString('Paracetamol', $result['answer']);
        Http::assertNothingSent(); // fast-path handled it, no PHI to LLM
    }

    public function test_prn_reports_last_given(): void
    {
        Http::fake();
        [, $branch, $user, $resident] = $this->seed();
        $prn = $this->makeMedication($resident, $branch, [
            'name' => 'Diazepam',
            'instructions' => 'PRN',
            'time_1' => null,
        ]);
        MedicationAdministration::create([
            'medication_id' => $prn->id,
            'resident_id' => $resident->id,
            'branch_id' => $branch->id,
            'administered_by' => $user->id,
            'administered_at' => now()->subHours(2),
            'status' => 'completed',
        ]);

        $result = app(AssistantService::class)->handle('any prn for Mary Smith', $user);

        $this->assertSame('prn_status', $result['intent']);
        $this->assertStringContainsString('Diazepam', $result['answer']);
        $this->assertStringContainsString('last given', $result['answer']);
    }

    public function test_unknown_resident_prompts_for_name_without_calling_llm(): void
    {
        Http::fake();
        [, , $user] = $this->seed();

        $result = app(AssistantService::class)->handle('what meds are due today', $user);

        $this->assertStringContainsStringIgnoringCase('which resident', $result['answer']);
        Http::assertNothingSent();
    }

    public function test_ambiguous_resident_asks_which_one(): void
    {
        Http::fake();
        [, $branch, $user] = $this->seed();
        Resident::factory()->create(['branch_id' => $branch->id, 'first_name' => 'Mary', 'last_name' => 'Taylor']);

        $result = app(AssistantService::class)->handle('meds for Mary', $user);

        $this->assertStringContainsStringIgnoringCase('which', $result['answer']);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `vendor/bin/phpunit tests/Feature/AssistantServiceTest.php`
Expected: FAIL — `App\Services\AssistantService` not found.

Note: if `Drug::factory()` does not exist, create the drug via `Drug::create([...])` mirroring how `tests/Feature/AdminMarkAdministeredTest.php` builds a `Drug` + `Medication`. Read that test first and match its field names.

- [ ] **Step 3: Write the implementation**

Create `app/Services/AssistantService.php`:

```php
<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\MedicationAdministration;
use App\Models\Resident;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class AssistantService
{
    public function __construct(
        private readonly ResidentResolver $resolver,
        private readonly IntentParsingService $intents,
        private readonly MedicationService $medications,
    ) {}

    /**
     * @return array{intent: string, answer: string, data: array}
     */
    public function handle(string $message, User $user): array
    {
        $resolution = $this->resolver->resolve($message, $user);

        if ($resolution->status === 'none') {
            // No resident matched: never send the raw name to the LLM. Classify
            // locally only, and ask for a resident if this looks med-related.
            $intent = $this->intents->fastPath($message);
            if ($intent === 'todays_meds' || $intent === 'prn_status') {
                return $this->reply($intent, "Which resident? I couldn't find that name in your facility.");
            }

            return $this->reply('unknown', "I can tell you a resident's medications for today, or their PRN (as-needed) meds. Try: \"today's meds for Jane Doe\".");
        }

        if ($resolution->status === 'ambiguous') {
            $names = collect($resolution->matches)->map(fn ($r) => $this->label($r))->implode(', ');

            return $this->reply('unknown', "More than one resident matches that name. Which one did you mean: {$names}?");
        }

        /** @var Resident $resident */
        $resident = $resolution->matches[0];
        $intent = $this->intents->parse($resolution->redacted);

        return match ($intent) {
            'todays_meds' => $this->answerTodaysMeds($resident),
            'prn_status' => $this->answerPrn($resident),
            default => $this->reply('unknown', "I can help with {$this->label($resident)}'s meds for today, or their PRN meds. Which would you like?"),
        };
    }

    private function answerTodaysMeds(Resident $resident): array
    {
        $meds = $resident->medicationOrders()
            ->where('is_active', true)
            ->with(['administrations' => fn ($q) => $q->whereDate('administered_at', today())])
            ->get();

        if ($meds->isEmpty()) {
            return $this->reply('todays_meds', "No active medications on record for {$this->label($resident)}.");
        }

        $withStatus = $this->medications->getMedicationsWithStatus($meds, today());

        $lines = $withStatus->map(function ($m) {
            if ($m->is_fully_administered_today) {
                $status = 'all doses given today';
            } elseif ($m->is_overdue) {
                $status = 'OVERDUE';
            } elseif ($m->next_dose_at) {
                $status = 'next due '.Carbon::parse($m->next_dose_at)->format('H:i');
            } else {
                $status = 'no scheduled times today';
            }

            return "- {$m->name}: {$status}";
        })->implode("\n");

        $answer = "Today's meds for {$this->label($resident)}:\n".$lines;

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
        ];
    }

    private function answerPrn(Resident $resident): array
    {
        $prn = $resident->medicationOrders()
            ->where('is_active', true)
            ->get()
            ->filter(fn ($m) => stripos((string) $m->instructions, 'PRN') !== false
                || stripos((string) $m->instructions, 'as needed') !== false)
            ->values();

        if ($prn->isEmpty()) {
            return $this->reply('prn_status', "No PRN (as-needed) medications on record for {$this->label($resident)}.");
        }

        $data = $prn->map(function ($m) {
            $last = MedicationAdministration::where('medication_id', $m->id)
                ->orderByDesc('administered_at')
                ->first();

            return [
                'id' => $m->id,
                'name' => $m->name,
                'last_given_at' => $last ? Carbon::parse($last->administered_at)->toIso8601String() : null,
            ];
        });

        $lines = $data->map(function ($row) {
            $when = $row['last_given_at']
                ? 'last given '.Carbon::parse($row['last_given_at'])->format('Y-m-d H:i')
                : 'no administration on record';

            return "- {$row['name']}: {$when}";
        })->implode("\n");

        $answer = "PRN meds for {$this->label($resident)}:\n".$lines
            ."\n\nNote: I show last-given only. I can't advise whether it's safe to give again.";

        return [
            'intent' => 'prn_status',
            'answer' => $answer,
            'data' => $data->all(),
        ];
    }

    /**
     * @return array{intent: string, answer: string, data: array}
     */
    private function reply(string $intent, string $answer, array $data = []): array
    {
        return ['intent' => $intent, 'answer' => $answer, 'data' => $data];
    }

    private function label(Resident $resident): string
    {
        return trim(($resident->first_name ?? '').' '.($resident->last_name ?? ''));
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `vendor/bin/phpunit tests/Feature/AssistantServiceTest.php`
Expected: PASS (4 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Services/AssistantService.php tests/Feature/AssistantServiceTest.php
git commit -m "feat(assistant): add orchestration service for meds and PRN answers"
```

---

## Task 4: AssistantController + route

**Files:**
- Create: `app/Http/Controllers/Api/AssistantController.php`
- Modify: `routes/api.php` (inside the existing `v1` group, near the other resident/medication routes ~line 139)
- Test: `tests/Feature/AssistantEndpointTest.php`

**Interfaces:**
- Consumes: `AssistantService::handle()` (Task 3), `BaseApiController` gate helpers `requireModuleAccess()`, `requirePermission()`, `success()` (existing).
- Produces: `POST /api/v1/assistant/query` accepting `{ "message": string }`, returning `{ "data": { intent, answer, data } }` on success.

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/AssistantEndpointTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Facility;
use App\Models\Resident;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AssistantEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_requires_authentication(): void
    {
        $this->postJson('/api/v1/assistant/query', ['message' => 'hi'])
            ->assertUnauthorized();
    }

    public function test_returns_answer_for_authorized_user(): void
    {
        Http::fake();
        $facility = Facility::factory()->create();
        $branch = Branch::factory()->create(['facility_id' => $facility->id]);
        $user = User::factory()->create(['facility_id' => $facility->id, 'role' => 'admin']);
        Resident::factory()->create(['branch_id' => $branch->id, 'first_name' => 'Mary', 'last_name' => 'Smith']);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/assistant/query', ['message' => 'meds for Mary Smith today'])
            ->assertOk()
            ->assertJsonPath('data.intent', 'todays_meds');
    }

    public function test_validates_message_is_required(): void
    {
        $facility = Facility::factory()->create();
        $user = User::factory()->create(['facility_id' => $facility->id, 'role' => 'admin']);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/assistant/query', [])
            ->assertStatus(422);
    }
}
```

Note on the permission/module gate: this test assumes a freshly-created `admin` user in a factory-made facility passes `hasModuleAccess('medications')` and `hasPermission('view_medications')`. Before writing the controller, read how `tests/Feature/AccessScopeTest.php` (or another medications feature test) sets up a user that passes the medications gate, and replicate that setup here so the authorized-user test is valid. If the gate requires seeding module access, add that seeding to this test's arrange block.

- [ ] **Step 2: Run tests to verify they fail**

Run: `vendor/bin/phpunit tests/Feature/AssistantEndpointTest.php`
Expected: FAIL — route `/api/v1/assistant/query` not defined (404/405).

- [ ] **Step 3: Write the controller**

Create `app/Http/Controllers/Api/AssistantController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Constants\Modules;
use App\Services\AssistantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssistantController extends BaseApiController
{
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
        ]);

        $result = $assistant->handle($validated['message'], $request->user());

        return $this->success($result);
    }
}
```

- [ ] **Step 4: Add the route**

In `routes/api.php`, inside the `Route::prefix('v1')->...->group(...)` block (opened at line 87), next to the other resident routes (~line 139), add:

```php
    Route::post('/assistant/query', [\App\Http\Controllers\Api\AssistantController::class, 'query'])
        ->middleware(['auth:sanctum', 'throttle:30,1']);
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `vendor/bin/phpunit tests/Feature/AssistantEndpointTest.php`
Expected: PASS (3 tests).

- [ ] **Step 6: Run the full assistant suite**

Run: `vendor/bin/phpunit --filter Assistant`
Expected: all assistant unit + feature tests PASS.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/Api/AssistantController.php routes/api.php tests/Feature/AssistantEndpointTest.php
git commit -m "feat(assistant): add gated /assistant/query endpoint"
```

---

## Task 5: Frontend chat widget

**Files:**
- Create: `resources/js/hooks/useAssistant.js`
- Create: `resources/js/components/ChatWidget.jsx`
- Modify: `resources/js/components/Layout.jsx` (mount widget once)
- Verify: `npm run build` + manual smoke (no JS test runner is configured in this repo)

**Interfaces:**
- Consumes: default axios instance from `resources/js/services/api.js` (`export default api`, baseURL `/api/v1`, auto CSRF + Bearer). Backend `POST /assistant/query` from Task 4, returning `{ data: { intent, answer, data } }`.
- Produces: a self-contained `<ChatWidget />` mounted globally.

- [ ] **Step 1: Write the data hook**

Create `resources/js/hooks/useAssistant.js`:

```javascript
import { useMutation } from '@tanstack/react-query';
import api from '../services/api';

// POSTs a message to the assistant endpoint and returns { intent, answer, data }.
export function useAssistant() {
    return useMutation({
        mutationFn: async (message) => {
            const res = await api.post('/assistant/query', { message });
            // BaseApiController::success() wraps the payload as { data: ... }
            return res.data.data;
        },
    });
}
```

- [ ] **Step 2: Write the widget**

Create `resources/js/components/ChatWidget.jsx`:

```jsx
import { useState } from 'react';
import { MessageCircle, X, Send } from 'lucide-react';
import { useAssistant } from '../hooks/useAssistant';

export default function ChatWidget() {
    const [open, setOpen] = useState(false);
    const [input, setInput] = useState('');
    const [messages, setMessages] = useState([]); // { role: 'user'|'bot', text }
    const assistant = useAssistant();

    const send = (e) => {
        e.preventDefault();
        const text = input.trim();
        if (!text || assistant.isPending) return;

        setMessages((m) => [...m, { role: 'user', text }]);
        setInput('');

        assistant.mutate(text, {
            onSuccess: (result) => {
                setMessages((m) => [...m, { role: 'bot', text: result.answer }]);
            },
            onError: () => {
                setMessages((m) => [
                    ...m,
                    { role: 'bot', text: "Sorry, I couldn't answer that right now." },
                ]);
            },
        });
    };

    return (
        <>
            {open && (
                <div className="fixed bottom-24 right-6 z-[100] flex h-[28rem] w-80 flex-col rounded-xl border border-gray-200 bg-white shadow-2xl dark:border-gray-700 dark:bg-gray-900">
                    <div className="flex items-center justify-between border-b border-gray-200 px-4 py-3 dark:border-gray-700">
                        <span className="font-semibold text-gray-800 dark:text-gray-100">Assistant</span>
                        <button onClick={() => setOpen(false)} aria-label="Close assistant">
                            <X className="h-5 w-5 text-gray-500" />
                        </button>
                    </div>

                    <div className="flex-1 space-y-2 overflow-y-auto px-4 py-3">
                        {messages.length === 0 && (
                            <p className="text-sm text-gray-400">
                                Ask me a resident's meds for today, or their PRN meds.
                            </p>
                        )}
                        {messages.map((m, i) => (
                            <div
                                key={i}
                                className={
                                    m.role === 'user'
                                        ? 'ml-auto max-w-[85%] whitespace-pre-line rounded-lg bg-[var(--theme-primary,#2563eb)] px-3 py-2 text-sm text-white'
                                        : 'mr-auto max-w-[85%] whitespace-pre-line rounded-lg bg-gray-100 px-3 py-2 text-sm text-gray-800 dark:bg-gray-800 dark:text-gray-100'
                                }
                            >
                                {m.text}
                            </div>
                        ))}
                        {assistant.isPending && <p className="text-xs text-gray-400">Thinking…</p>}
                    </div>

                    <form onSubmit={send} className="flex items-center gap-2 border-t border-gray-200 p-3 dark:border-gray-700">
                        <input
                            value={input}
                            onChange={(e) => setInput(e.target.value)}
                            placeholder="Ask about a resident…"
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

- [ ] **Step 3: Mount the widget in Layout**

In `resources/js/components/Layout.jsx`:

1. Add the import near the other component imports at the top of the file:

```javascript
import ChatWidget from './ChatWidget';
```

2. Mount it once inside the layout's returned tree. Place `<ChatWidget />` immediately after the `<Outlet />` at line 732 (as a sibling, inside the same parent element), so it renders on every authenticated page:

```jsx
                            <Outlet />
                            <ChatWidget />
```

- [ ] **Step 4: Build to verify it compiles**

Run: `npm run build`
Expected: build completes with no errors referencing `ChatWidget`, `useAssistant`, or `lucide-react` (already a dependency).

- [ ] **Step 5: Manual smoke test**

Start the dev server (`npm run dev` in one shell, `php artisan serve` in another, or the project's usual dev command). Then:

1. Log in as a user whose facility has the Medications module and `view_medications`.
2. Confirm the floating button appears bottom-right on a normal page.
3. Open it, type `meds for <a real resident's name> today`, send.
4. Confirm a med list (or "no active medications") comes back, and the resident's name never appears in any outbound request to `api.anthropic.com` (check the Network tab — for a fast-path phrase there should be NO anthropic request at all).

Record the actual observed result (screenshot or copied text) as evidence before marking done.

- [ ] **Step 6: Commit**

```bash
git add resources/js/hooks/useAssistant.js resources/js/components/ChatWidget.jsx resources/js/components/Layout.jsx
git commit -m "feat(assistant): add global chat widget UI"
```

---

## Known Follow-ups (out of scope for this plan)

- Frontend gating: the widget currently renders for all authenticated users; the backend gate is the real enforcement (403 if no module/permission). Hiding the button when the user lacks the Medications module is a cosmetic follow-up.
- PRN min-interval / max-per-day safety verdict — no data exists in schema; separate future work.
- Conversation history persistence — not in v1 (messages are in-memory).

## Self-Review

- **Spec coverage:** widget placement (Task 5), read-only (no write paths anywhere), hybrid brain / no-PHI redaction (Tasks 2+3, LLM only on redacted text after a match), two intents (Task 3 `answerTodaysMeds` / `answerPrn`), config (Task 1), module+permission gate (Task 4), tenant isolation (Task 2 test `test_does_not_match_resident_from_another_facility`), PRN limitation surfaced in answer copy (Task 3), fail-closed (Task 1). All spec sections map to a task.
- **Type consistency:** `fastPath()`/`parse()` names match across Tasks 1 and 3; `ResidentResolution` properties (`matches`, `redacted`, `status`) match between Task 2 definition and Task 3 usage; `handle()` return shape `{intent, answer, data}` consistent between Task 3 and the controller/endpoint in Task 4; `getMedicationsWithStatus()` signature matches the existing service.
- **Placeholder scan:** no TBD/TODO; every code step contains full code. Two notes intentionally instruct the implementer to mirror existing factory/gate setup from named existing tests — these are concrete references, not placeholders.
