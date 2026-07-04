# Evergreen Assistant v1.3 — Conversational Context Design Spec

**Date:** 2026-07-04
**Status:** Approved (design), pending implementation plan
**Branch:** multi-tenant
**Builds on:** v1 / v1.1 / v1.2 (shipped). Prior specs in `docs/superpowers/specs/2026-07-04-evergreen-assistant-*.md`.

## 1. Summary

The assistant is single-shot: it forgets the resident between messages, so a
follow-up like "show all his meds" (no name) falls back to "Which resident?" even
right after the user said "douglas". It also has no notion of the page the user
opened it from, and cannot answer for a resident's *full* medication list (only
today's doses and PRN).

v1.3 makes the conversation feel natural by adding:
1. **Active resident memory** — the widget remembers the resident it last answered
   about; name-less follow-ups resolve against them.
2. **Page-context seeding** — opening the assistant on a resident-scoped page starts
   it already focused on that resident.
3. **`all_medications` intent** — answers "all his medications" with the full active
   medication list, not just today's schedule.

No conversation history is stored server-side; context is a single resident id passed
per request. The "no PHI to the LLM" guarantee is unchanged (an id, not a name).

## 2. Goals & Non-Goals

### Goals
- Remember the active resident across turns until the user names a different one or clears it.
- Seed the active resident from the page the assistant is opened on (resident-scoped pages).
- Add an `all_medications` intent + full-list answer card.
- Make the active resident visible + clearable in the widget header.

### Non-Goals (v1.3)
- No server-side conversation history / transcript storage.
- No multi-resident memory (exactly one active resident at a time).
- No new writes; still read-only.
- No change to the redaction path or tenant-isolation model.
- Page-seeding is wired into a first set of high-traffic resident pages, not every page
  (others opt in later with one hook call).

## 3. Decisions (from brainstorming)

| # | Decision | Choice |
|---|----------|--------|
| Q1 | Conversation memory | Yes — sticky active resident until switched/cleared |
| Q2 | Page context | Yes — seed active resident from the current resident page |
| Q3 | All-meds intent | Yes — add `all_medications` (full active list) |

## 4. Architecture

### 4.1 Active-resident memory (request-scoped id, client-held)

- `AssistantService::handle()` signature becomes
  `handle(string $message, User $user, ?int $contextResidentId = null): array`.
- **Resolution precedence:**
  1. If the message names exactly one resident (`ResidentResolver` status `one`) → use them.
  2. Else if the message is ambiguous (`ambiguous`) → ask which (chips), ignore context.
  3. Else (`none`): if `contextResidentId` is a resident the user may see (re-checked via a
     FacilityScope-active `Resident::find`) → use that resident. Otherwise the existing
     "Which resident?" / help path.
- The reply gains a `resident: {id, name} | null` field — the resident actually answered
  about (null for none/ambiguous/help). The client stores it as the active resident.
- Every query request may include `context_resident_id` (nullable int). The endpoint
  validates it (`nullable|integer`) and passes it through; the service re-checks visibility
  via FacilityScope, so a stale/foreign id is simply ignored (never leaks across tenants).

**PHI safety:** `context_resident_id` is an integer, never a name. When context is used the
message text carries no resident name (e.g. "show all his meds"), so intent parsing via the
LLM sees no PHI — identical guarantee to today. When the message *does* carry a name, the
existing `ResidentResolver` redaction applies before any LLM call.

### 4.2 Page-context seeding (client)

- A small app-level React context `AssistantResidentContext` exposes
  `{ pageResident, setPageResident }` where `pageResident` is `{ id, name } | null`.
- A provider wraps the app (near the existing providers in `app.jsx`).
- Resident-scoped pages publish their loaded resident via a hook
  `useAssistantPageResident({ id, name })` (a thin `useEffect` that sets/clears the context).
  v1.3 wires the first set: the resident profile page and the resident assessment-detail page
  (the flows demoed). Pages that don't opt in leave `pageResident` null (graceful fallback).
- On **open with an empty message list**, the widget seeds its active resident from
  `pageResident` (if set). Typing a different name still switches it.

### 4.3 `all_medications` intent

- `IntentParsingService`: enum becomes `todays_meds | prn_status | all_medications | unknown`.
  - Fast-path: a rule matching "all" + a med keyword (e.g. `/\ball\b/` together with
    `med|meds|medication|medications|drug|drugs`) returns `all_medications`, and this rule is
    checked **before** the `todays_meds` rule so "all his meds" does not collapse to today.
  - System prompt updated to describe the new label.
- `AssistantService::answerAllMedications(resident)`: lists every **active** medication for the
  resident — name + instructions (schedule text). Data items: `{ id, name, instructions }`.
  Empty → "No active medications on record for {name}." Rendered as a distinct card.

### 4.4 Suggestion chips

- `choiceSuggestions(resident)` (shown when a resident is known but the intent is unclear) gains
  a third chip: `{ label: 'All meds', message: "all medications for {name}" }` — the message
  contains "all" + "medications" so it fast-paths to `all_medications`.

### 4.5 Header active-resident indicator

- When an active resident is set, the widget header shows "About: {name}" with an ✕ control that
  clears the active resident (and the page seed for the session), returning to the generic state.

## 5. Component Changes

**Backend:**
- `app/Services/IntentParsingService.php` — enum + fast-path + prompt for `all_medications`.
- `app/Services/AssistantService.php` — `handle()` gains `?int $contextResidentId`; resolution
  precedence; `resident` in every reply; `answerAllMedications()`; `All meds` chip.
- `app/Http/Controllers/Api/AssistantController.php` — `query()` validates + forwards
  `context_resident_id`.

**Frontend:**
- `resources/js/hooks/useAssistant.js` — `useAssistant` mutation sends `{ message, context_resident_id }`.
- `resources/js/context/AssistantResidentContext.jsx` (new) — provider + `useAssistantPageResident` hook.
- `resources/js/app.jsx` — wrap app in the provider.
- `resources/js/components/assistant/ChatWidget.jsx` — active-resident state, send context id,
  store `resident` from replies, seed from page context, header indicator + clear.
- `resources/js/components/assistant/AllMedsResult.jsx` (new) — full-list card.
- Resident profile page + assessment-detail page — call `useAssistantPageResident(...)`
  (exact files identified during planning by scouting which pages load a single resident).

## 6. Security & Multi-Tenancy

- `context_resident_id` is re-resolved through the global `FacilityScope` on every request; a
  foreign/stale id resolves to nothing and is ignored — no cross-tenant access.
- Read-only; the new intent only issues SELECTs.
- No un-redacted resident name reaches the LLM (context uses an id; named messages redact first).
- No `dangerouslySetInnerHTML`; all text escaped.

## 7. Testing

- **Backend feature:**
  - Context fallback: `handle("show all his meds", user, contextId)` with no name in the message
    resolves to the context resident and returns `all_medications`.
  - Switch: a message naming a different resident overrides the context id.
  - Foreign context id (other facility) is ignored (falls to "Which resident?").
  - `all_medications` intent classification (fast-path) + `answerAllMedications` lists active meds;
    empty case handled.
  - Reply always includes a `resident` key (null on none/ambiguous).
  - Endpoint validates `context_resident_id` as nullable integer and forwards it.
- **Frontend:** `npm run build` (no JS test runner) + manual browser smoke: open on a resident
  page → active resident shown; ask "all his meds" without a name → answered; clear ✕ resets.

## 8. Known Limitations (v1.3)

- One active resident at a time; asking about two residents in one breath still needs a name each.
- Page-seeding covers the first wired pages only; unwired pages fall back to chat naming.
- `all_medications` lists active meds with instructions text; no richer clinical grouping.
