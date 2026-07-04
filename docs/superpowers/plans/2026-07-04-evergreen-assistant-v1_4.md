# Evergreen Assistant v1.4 Scheduled Times Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Show each today's-meds row's scheduled slot times, marked given vs pending, by surfacing per-slot data the medication service already computes.

**Architecture:** `MedicationService::getMedicationsWithStatus()` already checks each `time_1..time_4` slot for an administration within ±60 min; attach that per-slot result as a `slots` array. `AssistantService::answerTodaysMeds` passes `slots` through, and `MedResult` renders a times line per row.

**Tech Stack:** Laravel 11, PHPUnit 11, React 19, Tailwind.

## Global Constraints

- Additive only. `slots` is a new field on the med object / data item; existing callers of `getMedicationsWithStatus` (dashboard controllers) ignore it — do not change their behavior.
- Read-only. No new query/data source. No change to the ±60-min window logic, bucketing, or other intents/cards.
- todays_meds data item becomes `{ id, name, is_fully_administered_today, is_overdue, next_dose_at, slots: [{at: string, given: bool}] }`. `slots` is `[]` for meds with no set times.
- PHP: `declare(strict_types=1)` context (file already has it where applicable), typed. Do NOT run pint manually. Tests: PHPUnit, `RefreshDatabase`, `Sanctum::actingAs`, `Http::fake()`.
- Frontend: no JS test runner — verify `npm run build`. No `console.log`, no `dangerouslySetInnerHTML`. Slot times use the same 12h `formatTime` already in `MedResult.jsx`.

---

## File Structure

**Backend (modify):**
- `app/Services/MedicationService.php` — collect + attach `$med->slots` inside `getMedicationsWithStatus()`.
- `app/Services/AssistantService.php` — add `'slots'` to each `answerTodaysMeds` data item.

**Frontend (modify):**
- `resources/js/components/assistant/MedResult.jsx` — render per-row slot times (given/pending).

---

## Task 1: Backend — attach and pass through `slots`

**Files:**
- Modify: `app/Services/MedicationService.php`
- Modify: `app/Services/AssistantService.php`
- Test: `tests/Feature/AssistantServiceTest.php`

**Interfaces:**
- Produces: each `getMedicationsWithStatus` med gains `->slots` (`array<int, array{at: string, given: bool}>`); each `answerTodaysMeds` data item gains `slots` with the same shape.

- [ ] **Step 1: Add the failing test** (append inside the class in `tests/Feature/AssistantServiceTest.php`; reuse `seedFixtures()`/`makeMedication()`)

```php
    public function test_todays_meds_include_scheduled_slots_with_given_flags(): void
    {
        Http::fake();
        [, $branch, $user, $resident] = $this->seedFixtures();
        $med = $this->makeMedication($resident, $branch, [
            'name' => 'Ferrous sulfate 325mg',
            'instructions' => 'b.d',
            'time_1' => '08:00',
            'time_2' => '14:00',
        ]);
        // one administration near the 08:00 slot -> first slot given, second pending
        \App\Models\MedicationAdministration::create([
            'medication_id' => $med->id,
            'resident_id' => $resident->id,
            'branch_id' => $branch->id,
            'administered_by' => $user->id,
            'administered_at' => today()->setTime(8, 5),
            'status' => 'completed',
        ]);

        $result = app(AssistantService::class)->handle('today\'s meds for Mary Smith', $user);

        $this->assertSame('todays_meds', $result['intent']);
        $item = collect($result['data'])->firstWhere('name', 'Ferrous sulfate 325mg');
        $this->assertNotNull($item);
        $this->assertCount(2, $item['slots']);
        $this->assertTrue($item['slots'][0]['given']);
        $this->assertFalse($item['slots'][1]['given']);
        // times reflect the scheduled slots
        $this->assertStringContainsString('08:00', \Carbon\Carbon::parse($item['slots'][0]['at'])->format('H:i'));
        $this->assertStringContainsString('14:00', \Carbon\Carbon::parse($item['slots'][1]['at'])->format('H:i'));
    }
```

Note: if `makeMedication`/`seedFixtures` signatures differ from this, read the top of the test file and adapt (they were used by prior tasks). `makeMedication` must accept the `time_1`/`time_2` overrides — it does (it merges an overrides array into `Medication::create`).

- [ ] **Step 2: Run to verify failure**

Run: `vendor/bin/phpunit tests/Feature/AssistantServiceTest.php --filter test_todays_meds_include_scheduled_slots_with_given_flags`
Expected: FAIL — `slots` key missing from the data item.

- [ ] **Step 3: Attach `slots` in `MedicationService::getMedicationsWithStatus()`**

In `app/Services/MedicationService.php`, inside the `$medications->map(function ($med) ...)` callback:

1. Add a `$slots = [];` initializer alongside the other locals (next to `$nextDoseTime = null;`):

```php
            $nextDoseTime = null;
            $completedCount = 0;
            $totalSlots = 0;
            $slots = [];
            $isPrn = stripos($med->instructions, 'PRN') !== false || stripos($med->instructions, 'as needed') !== false;
```

2. Inside the `try` block, immediately after `$hasAdmin` is computed (right after the `$hasAdmin = $med->administrations->contains(...);` assignment, before the `if ($hasAdmin)` check), record the slot:

```php
                        $slots[] = [
                            'at' => $scheduledTime->toIso8601String(),
                            'given' => $hasAdmin,
                        ];
```

3. After the `for` loop closes (alongside `$med->is_fully_administered_today = ...`), attach the slots:

```php
            $med->slots = $slots;
```

(Leave all other computed attributes — `is_fully_administered_today`, `next_dose_at`, `is_overdue`, `minutes_until_next` — unchanged.)

- [ ] **Step 4: Pass `slots` through in `AssistantService::answerTodaysMeds()`**

In `app/Services/AssistantService.php`, in `answerTodaysMeds()`, add `slots` to each mapped data item:

```php
            'data' => $withStatus->map(fn ($m) => [
                'id' => $m->id,
                'name' => $m->name,
                'is_fully_administered_today' => (bool) $m->is_fully_administered_today,
                'is_overdue' => (bool) $m->is_overdue,
                'next_dose_at' => $m->next_dose_at,
                'slots' => $m->slots ?? [],
            ])->values()->all(),
```

(No other change to the method; the empty-meds early return and `resident` key stay as they are.)

- [ ] **Step 5: Run to verify pass**

Run: `vendor/bin/phpunit tests/Feature/AssistantServiceTest.php`
Expected: PASS (all, including the new test).

- [ ] **Step 6: Run the full assistant suite (guard the shared service change)**

Run: `vendor/bin/phpunit --filter Assistant`
Expected: green. Also run any medication-dashboard tests that exercise `getMedicationsWithStatus` to confirm the additive attribute broke nothing:

Run: `vendor/bin/phpunit --filter Medication`
Expected: green (or unchanged from before this task).

- [ ] **Step 7: Commit**

```bash
git add app/Services/MedicationService.php app/Services/AssistantService.php tests/Feature/AssistantServiceTest.php
git commit -m "feat(assistant): expose per-slot scheduled times with given flags"
```

---

## Task 2: Frontend — render slot times in `MedResult`

**Files:**
- Modify: `resources/js/components/assistant/MedResult.jsx`
- Verify: `npm run build`

**Interfaces:**
- Consumes: each med now has `slots: [{at: string, given: bool}]` (Task 1).

- [ ] **Step 1: Update `MedRow` to render the slot times**

In `resources/js/components/assistant/MedResult.jsx`, replace the `MedRow` component with:

```jsx
function MedRow({ med }) {
    let tone = 'muted';
    let label = 'No times today';
    if (med.is_overdue) {
        tone = 'overdue';
        label = 'Overdue';
    } else if (med.is_fully_administered_today) {
        tone = 'done';
        label = 'Done';
    } else if (med.next_dose_at) {
        tone = 'due';
        label = `Due ${formatTime(med.next_dose_at)}`;
    }

    const slots = Array.isArray(med.slots) ? med.slots : [];

    return (
        <div className="py-1">
            <div className="flex items-center justify-between gap-2">
                <span className="truncate text-sm text-gray-800 dark:text-gray-100">{med.name}</span>
                <StatusPill tone={tone} label={label} />
            </div>
            {slots.length > 0 && (
                <div className="mt-0.5 flex flex-wrap items-center gap-x-3 gap-y-0.5 text-xs">
                    {slots.map((s, i) => (
                        <span
                            key={i}
                            className={
                                s.given
                                    ? 'text-gray-400 dark:text-gray-500'
                                    : 'font-medium text-gray-700 dark:text-gray-200'
                            }
                        >
                            {formatTime(s.at)}
                            {s.given ? ' ✓' : ''}
                        </span>
                    ))}
                </div>
            )}
        </div>
    );
}
```

(No other change to `MedResult.jsx` — `bucketMeds`, the summary bar, grouping, and the collapsed "Done today" section all stay; each row now simply renders its own slot line.)

- [ ] **Step 2: Build to verify**

Run: `npm run build`
Expected: exit 0, no errors referencing `MedResult`. Quote the tail.

- [ ] **Step 3: Self-review**

Confirm: no `console.log`; given slots muted + ✓, pending slots bold; meds with empty `slots` render no times line; the status pill still renders on the right; `formatTime` reused (not re-implemented).

- [ ] **Step 4: Commit**

```bash
git add resources/js/components/assistant/MedResult.jsx
git commit -m "feat(assistant): show scheduled slot times per med row"
```

---

## Self-Review

- **Spec coverage:** per-slot `slots` attached in the service (Task 1 `getMedicationsWithStatus`), passed through (Task 1 `answerTodaysMeds`), rendered given/pending per row (Task 2 `MedRow`), status pill retained (Task 2), no-times meds show no line (Task 2 `slots.length > 0` guard), additive/shared-service safety checked (Task 1 Step 6). All spec sections mapped.
- **Type consistency:** slot shape `{at: string, given: bool}` identical in `getMedicationsWithStatus`, `answerTodaysMeds` data, the backend test, and `MedRow`; `formatTime(s.at)` matches the existing helper signature.
- **Placeholder scan:** every code step has complete code; the "adapt if helper signature differs" note points at concrete existing helpers, not a placeholder.
