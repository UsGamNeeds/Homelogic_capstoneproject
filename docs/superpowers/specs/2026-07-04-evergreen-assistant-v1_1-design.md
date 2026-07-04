# Evergreen Assistant v1.1 — Design Spec

**Date:** 2026-07-04
**Status:** Approved (design), pending implementation plan
**Branch:** multi-tenant
**Builds on:** `docs/superpowers/specs/2026-07-04-evergreen-assistant-design.md` (v1, shipped)

## 1. Summary

Iteration on the shipped v1 assistant, driven by two problems observed in use:

1. **Dead-end conversations.** The bot is single-shot (no conversation memory). When it
   asks "today's meds or PRN?" and the user replies "yes", the reply carries no resident
   and no intent, so it falls back to the generic help message. Reproduced live:
   `brittany hooks barton` → "Which for Brittany Hooks Barton?" → `yes` → generic help.
2. **Window too small.** The widget is a 320×448px floating card; users want the roomier
   side-drawer experience of the Hive AI reference.

v1.1 fixes both **without** adding conversation memory or new intents, by making every
choice the bot offers a **clickable suggestion chip** that sends a known-good phrase back
through the existing pipeline, and by enlarging the widget to a full-height right drawer.

## 2. Goals & Non-Goals

### Goals
- Eliminate the "yes"/free-text-follow-up dead-end via clickable suggestion chips.
- Enlarge the widget to a full-height right-edge drawer (Hive-AI style).
- Add resident-aware starter chips in the empty state so staff learn the 2 capabilities.
- Reuse the existing intent/redaction pipeline unchanged — no new brain, no memory.

### Non-Goals (v1.1)
- No conversation memory / multi-turn state.
- No new intents (still: today's meds, PRN status).
- No writes; still strictly read-only.
- No change to the "no PHI to LLM" redaction path.

## 3. Decisions (from brainstorming)

| # | Decision | Choice |
|---|----------|--------|
| Q1 | Accuracy fix | Clickable suggestion chips (no conversation memory) |
| Q2 | Window/layout | Full-height right-edge drawer (~400px wide) |
| Q3 | Starter chips | Yes, resident-aware (built from real residents the user can see) |

## 4. Architecture

### 4.1 Suggestion chips — the mechanism

A chip is a `{ label, message }` pair. `label` is the button text; `message` is a
ready-to-send phrase that the existing pipeline already handles correctly. Clicking a chip
POSTs `message` to the existing `POST /api/v1/assistant/query` — no new endpoint, no new
intent parsing, no memory. Because the phrases are templated and known-good (e.g.
`today's meds for Brittany Hooks Barton`), they always match the fast-path + resident
resolver.

`AssistantService::handle()`'s return shape gains a `suggestions` key:
`['intent' => string, 'answer' => string, 'data' => array, 'suggestions' => array]`
where `suggestions` is a (possibly empty) list of `['label' => string, 'message' => string]`.

Where suggestions are produced:
- **Matched one resident, intent unclear** (the current "which?" branch): return two chips —
  `['label' => "Today's meds", 'message' => "today's meds for {full name}"]` and
  `['label' => 'PRN', 'message' => "PRN for {full name}"]`.
- **Ambiguous** (>1 resident matched): return one chip per matched resident —
  `['label' => "{full name}", 'message' => "today's meds for {full name}"]`.
- **None / normal answers**: `suggestions` is `[]`.

The templated messages must use words the fast-path recognizes (`meds`, `prn`) so the
follow-up never needs the LLM.

### 4.2 Starter chips — new suggestions endpoint

`GET /api/v1/assistant/suggestions` (auth:sanctum + throttle; gated on
`requireModuleAccess(MEDICATIONS)` + `requirePermission('view_medications')`, identical to
the query endpoint). Returns up to 3 starter chips built from real residents the user can
see (tenant-scoped via FacilityScope), e.g.:

```json
{ "data": { "suggestions": [
  { "label": "Today's meds for Brittany Hooks Barton", "message": "today's meds for Brittany Hooks Barton" },
  { "label": "PRN for Douglas Brassart", "message": "PRN for Douglas Brassart" }
] } }
```

Selection rule: take the first N (default 3) residents from
`Resident::query()->orderBy('first_name')->limit(N)` (FacilityScope active), alternate the
prompt template (today's meds / PRN) across them. If the facility has zero visible
residents, return `suggestions: []` (frontend falls back to the one-line hint).

### 4.3 Backend components

- **`AssistantService`** (modify) — add `suggestions` to the relevant replies; add a small
  private helper `suggestionsFor(...)` to build the chip arrays. No change to the
  resolve → parse → answer core.
- **`AssistantController`** (modify) — add a `suggestions(Request $request): JsonResponse`
  method: run the same two gates, then return starter chips via a new
  `AssistantService::starterSuggestions(User $user, int $limit = 3): array`.
- **`routes/api.php`** (modify) — add `GET /assistant/suggestions` in the v1 group with the
  same middleware as the query route.

### 4.4 Frontend components

- **`ChatWidget.jsx`** (modify) — becomes a full-height right-edge drawer: fixed right, top-0
  to bottom-0, ~400px wide (`w-full sm:w-[400px]`), flex column (header / scrollable
  messages / sticky input). The floating button still toggles it. Each bot message that
  carries `suggestions` renders them as a row of chips below the bubble; clicking a chip
  calls the same send path with the chip's `message`. On open with an empty message list,
  fetch starter chips and render them.
- **`useAssistant.js`** (modify) — keep the `query` mutation; add a `useStarterSuggestions`
  query (TanStack `useQuery`) hitting `GET /assistant/suggestions`, enabled when the drawer
  opens.

## 5. Security & Multi-Tenancy

- No change to the "no PHI to LLM" guarantee: chips send resident **names**, but names are
  redacted by `ResidentResolver` before any LLM call exactly as in v1. The chip messages
  only ever reference residents the user already matched or already can see.
- `GET /assistant/suggestions` enforces the same module + permission gates and runs through
  FacilityScope, so starter chips never expose a resident outside the user's facility.
- Still read-only. The suggestions endpoint only reads resident names.
- Chip `message` values are server-generated from real resident names; the frontend sends
  them verbatim. They are validated by the existing `required|string|max:500` rule on the
  query endpoint.

## 6. Known Limitations (v1.1)

- Still no memory: typing a bare "yes" (instead of clicking a chip) still yields the help
  message. The chips are the intended path; this is acceptable.
- Starter chips show a deterministic first-N residents by name, not "most relevant" — there
  is no relevance signal in v1.1.
- PRN answer limitation from v1 unchanged (last-given only, no safe-to-give verdict).

## 7. Testing Strategy

- **Backend feature:**
  - `handle()` on the matched-one-resident-unclear path returns two chips whose `message`
    values fast-path to `todays_meds` / `prn_status` (assert by feeding each chip message
    back through `handle()` and checking the resulting intent).
  - `handle()` on the ambiguous path returns one chip per matched resident.
  - Normal answers return `suggestions: []`.
  - `GET /api/v1/assistant/suggestions`: 401 unauth; 403 without module/permission (reuse
    v1 deny-path setup); tenant isolation (only this facility's residents appear); returns
    ≤3 chips; `[]` when no residents.
- **Frontend:** `npm run build` (no JS test runner in repo).
- Patient-safety (Aisha lens): assert no chip `message` bypasses redaction (chip messages
  still flow through `ResidentResolver` on the query endpoint) and no cross-tenant resident
  name appears in starter chips.

## 8. Rollout

- Same `Modules::MEDICATIONS` + `view_medications` gate as v1. Ships on `multi-tenant`.
