# Evergreen Assistant v1.4 — Scheduled Times Design Spec

**Date:** 2026-07-04
**Status:** Approved (design), pending implementation plan
**Branch:** multi-tenant
**Builds on:** v1–v1.3 (shipped). Prior specs in `docs/superpowers/specs/2026-07-04-evergreen-assistant-*.md`.

## 1. Summary

The today's-meds card shows each med's overall status (Overdue / Due HH:MM / Done /
No times) but not the actual scheduled administration times. Staff want to see every
slot a medication is due today and which have already been given. The per-slot data is
already computed inside `MedicationService::getMedicationsWithStatus()` (it checks each
of `time_1..time_4` for a matching administration within ±60 min) but is discarded. This
iteration surfaces it: each scheduled med row lists its slot times, each marked given or
pending.

Frontend + a small additive backend change; no new data source, no new intent, read-only.

## 2. Goals & Non-Goals

### Goals
- Attach a per-slot `slots` array to each today's-meds item: `[{ at, given }]` in schedule order.
- Render the slot times on each scheduled med row, marking given vs pending.
- Keep the existing status pill (Overdue / Due / Done / No times) as the summary.

### Non-Goals (v1.4)
- No change to the `all_medications` or `prn_status` cards.
- No new intent, no writes, no new query source.
- No change to the ±60-min administration-window logic (reuse as-is).

## 3. Decisions (from brainstorming)

| # | Decision | Choice |
|---|----------|--------|
| Q1 | Which times | All scheduled slots per med |
| Q2 | Given vs pending | Mark each slot given (muted + ✓) or pending |

## 4. Architecture

### 4.1 Backend — attach `slots` (additive)

`MedicationService::getMedicationsWithStatus(Collection $meds, $date): Collection` already
loops `time_1..time_4`, parses each set slot to a `Carbon` `$scheduledTime`, and computes
`$hasAdmin` (an administration within ±60 min with an accepted status). Extend the loop to
also collect, per set slot:

```php
$slots[] = [
    'at' => $scheduledTime->toIso8601String(),
    'given' => $hasAdmin,
];
```

and set `$med->slots = $slots;` (empty array when the med has no set times — e.g. PRN /
unscheduled). This is purely additive: existing callers (`MedicationDashboardController`,
etc.) ignore the new attribute, so their behavior is unchanged.

`AssistantService::answerTodaysMeds()` adds `'slots' => $m->slots ?? []` to each item in
the `data` array. All other fields (`id`, `name`, `is_overdue`, `is_fully_administered_today`,
`next_dose_at`) stay. `answer` (the text fallback) is unchanged.

**Data contract (todays_meds item) becomes:**
`{ id, name, is_fully_administered_today, is_overdue, next_dose_at, slots: [{ at: string, given: bool }] }`.

### 4.2 Frontend — render slot times in `MedResult`

Each `MedRow` gains a times line below the med name when `slots` is non-empty:
- Each slot rendered as its time (same 12h `toLocaleTimeString` format the pills use), joined by `·`.
- **Given** slots: muted color + a ✓ mark.
- **Pending** slots: normal weight.
- Meds with an empty `slots` array (the "No times today" bucket) render no times line — unchanged.
- The existing status pill on the right stays as the summary.

No change to bucketing/grouping, the summary count bar, or the collapsed "Done today" section —
each row inside any group simply shows its slot line.

## 5. Component Changes

**Backend:**
- `app/Services/MedicationService.php` — collect + attach `slots` per med in `getMedicationsWithStatus()`.
- `app/Services/AssistantService.php` — add `slots` to each `answerTodaysMeds` data item.

**Frontend:**
- `resources/js/components/assistant/MedResult.jsx` — render the per-row slot times (given/pending).

## 6. Security & Multi-Tenancy

- No new query or data source; `slots` derives from medications already fetched under the
  resident's FacilityScope. Read-only. No PHI-path change.
- No `dangerouslySetInnerHTML`; times are plain escaped text.

## 7. Testing

- **Backend feature:** with a med scheduled at two slots and one administration recorded near
  the first slot, `answerTodaysMeds` (via `handle`) returns that med's `slots` with the first
  `given: true` and the second `given: false`, and the times match the scheduled slots.
- **Frontend:** `npm run build` (no JS test runner) + manual smoke: a scheduled med shows its
  slot times with given ones checked/muted and pending ones plain; a "No times today" med shows
  no times line.

## 8. Known Limitations (v1.4)

- "Given" reflects the same ±60-min window `getMedicationsWithStatus` already uses; it is not a
  re-derivation and inherits that method's semantics.
- Slot times shown in the browser's local time (consistent with the rest of the SPA).
- Only the today's-meds card shows slots; the full-list and PRN cards are unchanged.
