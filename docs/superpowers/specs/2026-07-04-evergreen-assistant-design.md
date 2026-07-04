# Evergreen Assistant — v1 Design Spec

**Date:** 2026-07-04
**Status:** Approved (design), pending implementation plan
**Branch:** multi-tenant

## 1. Summary

A read-only, staff-facing chat assistant embedded in Evergreen as a global
floating widget. Care staff ask plain-English questions during med rounds and
get answers drawn from existing resident/medication data. The assistant never
writes data and never exposes anything the logged-in user could not already see
in the UI.

**v1 answers exactly two questions:**

1. **Today's meds** for a named resident — today's scheduled slots plus
   given/overdue status.
2. **PRN** for a named resident — which PRN medications exist and when each was
   last administered.

Everything else is explicitly out of scope for v1.

## 2. Goals & Non-Goals

### Goals
- Fast lookup during med rounds without navigating the UI.
- Zero patient data (PHI) sent to any external LLM.
- Strict multi-tenant isolation and permission parity with the existing UI.
- Small surface area — reuse existing medication services, add no new med logic.

### Non-Goals (v1)
- No writes/actions of any kind (no administering, logging, editing).
- No PRN "safe to give again" verdict (see Known Limitations).
- No conversation history persistence.
- No admin/reporting queries, no resident/family access, no app-helpdesk mode.
- No cross-resident / unit-wide queries ("who's overdue", "due next hour").

## 3. Decisions (from brainstorming)

| # | Decision | Choice |
|---|----------|--------|
| Q1 | Audience | Staff assistant (carers) |
| Q2 | Capability | Read-only (no writes) |
| Q3 | Engine | Hybrid — LLM parses intent only; PHI stays local |
| Q4 | v1 intents | Today's meds for resident X; PRN for resident X + last given |
| Q5 | UI placement | Global floating widget, bottom-right, every page |

## 4. Architecture

### 4.1 The hybrid brain — honoring "no PHI to LLM"

Staff free-text contains resident names, and names are PHI. To keep the "no PHI
to LLM" guarantee, entity resolution happens locally and only redacted text
reaches the LLM.

```
1. Staff types: "what meds is mary on today"
2. LOCAL entity pass: match "mary" against THIS tenant's residents
   (existing FacilityScope-scoped query). Replace matched name with a token:
   -> "what meds is {RESIDENT} on today"
3. LLM intent pass: send REDACTED text to Claude. Claude returns intent only:
   { intent: "todays_meds", slot: "RESIDENT" }
   Claude never receives a real name or any medication data.
4. LOCAL resolve + answer: map {RESIDENT} back to the real resident, run the
   existing MedicationService query, render the answer locally.
```

**Local regex fast-path:** obvious phrasings ("meds for X today", "prn for X")
are matched by local rules and skip the LLM entirely (cost + latency win). The
LLM is the fallback for fuzzy wording only.

**Intent enum (v1):** `todays_meds`, `prn_status`, `unknown`.
`unknown` -> friendly "I can help with today's meds or PRN for a resident."

### 4.2 Data flow

```
ChatWidget (React)
  -> POST /api/v1/assistant/query { message }
     [Sanctum auth, requireModuleAccess(MEDICATIONS), view_medications]
  -> AssistantController
       -> AssistantService::handle(message, user)
            1. resolveResidentEntities(message)   // local, tenant-scoped
            2. IntentParsingService::parse(redacted)  // regex fast-path, LLM fallback
            3. dispatch by intent:
                 todays_meds -> MedicationService::getMedicationsWithStatus(meds, today())
                 prn_status  -> PRN meds + latest MedicationAdministration per med
            4. format answer payload
  -> JSON { answer, data, meta }
```

### 4.3 Backend components

- **Route:** `POST /api/v1/assistant/query` in `routes/api.php`, middleware
  `auth:sanctum` + module/permission gate.
- **`AssistantController`** (`app/Http/Controllers/Api/AssistantController.php`)
  — validates input, calls `AssistantService`, returns JSON via existing
  `ApiResponse` trait.
- **`AssistantService`** (`app/Services/AssistantService.php`) — orchestrates
  entity resolution, intent parsing, data fetch, answer formatting. Contains the
  local resident-matching + redaction logic.
- **`IntentParsingService`** (`app/Services/IntentParsingService.php`) — regex
  fast-path; on miss, calls Claude via the `Http` facade. Returns a structured
  intent object. Fails closed to `unknown` on any API/parse error.
- **Data reuse (no new med logic):**
  - Today's meds: `MedicationService::getMedicationsWithStatus($medications, today())`.
  - PRN detection: medications whose `instructions` contain "PRN".
  - Last given: `MedicationAdministration::where('medication_id', $id)
    ->orderBy('administered_at', 'desc')->first()`.

### 4.4 Config

- `config/services.php`:
  ```php
  'anthropic' => [
      'api_key' => env('ANTHROPIC_API_KEY'),
      'model'   => env('ANTHROPIC_MODEL', 'claude-haiku-4-5-20251001'),
  ],
  ```
- `.env.example`: add `ANTHROPIC_API_KEY=` and `ANTHROPIC_MODEL=`.
- Intent parsing is a short, sync HTTP call — no queue needed (default `sync`).
- Model choice: intent classification is trivial -> use Haiku (cheap, fast).

### 4.5 Frontend components (React SPA)

- **`ChatWidget.jsx`** (`resources/js/components/ChatWidget.jsx`) — floating
  button + Radix Dialog panel, mounted once in `Layout.jsx` (after `<Outlet />`).
  Tailwind styling, `lucide-react` icon, z-[100] for button.
- **`useAssistant`** (`resources/js/hooks/useAssistant.js`) — TanStack Query
  mutation calling the existing axios `api` service (`/api/v1`, CSRF + Bearer
  auto-injected).
- Messages held in component state only; no persistence in v1.

## 5. Security & Multi-Tenancy

- **Isolation is free:** every data read goes through models carrying the global
  `FacilityScope`, so results are automatically limited to the user's facility/
  branch. Resident matching in step 1 runs through the same scoped query, so the
  assistant cannot even resolve a name outside the tenant.
- **Permission parity:** endpoint requires the same `Modules::MEDICATIONS`
  module access and `view_medications` permission the UI already enforces.
- **No PHI egress:** only name-redacted intent text reaches Claude. No medication
  data, no real names, no identifiers.
- **Fail closed:** LLM/API errors degrade to `unknown` intent, never to an
  unscoped or guessed answer.
- **Operational:** confirm Anthropic API terms/BAA posture before enabling in a
  facility handling real PHI, even though only redacted text is transmitted.

## 6. Known Limitations (v1)

- **PRN "can I give now?" is NOT answered.** The schema stores no min-interval or
  max-per-day for PRN meds. The assistant reports facts only — the PRN meds, and
  each one's last-administered timestamp and active status — and does **not**
  render a "safe to give again" verdict. Interval/limit rules are separate future
  work.
- **Name collisions:** if a name matches multiple residents, the assistant asks a
  disambiguating question ("Which? Mary S (rm 4) or Mary T (rm 12)?") rather than
  guessing.
- **Two intents only:** anything outside today's-meds / PRN returns a friendly
  capability hint.

## 7. Testing Strategy (outline; detailed in plan)

- **Unit:** resident redaction (single match, multi-match, no match); regex
  fast-path intent matching; `unknown` fallback on LLM error.
- **Feature:** `POST /api/v1/assistant/query` — auth required; module/permission
  gate enforced (403 without access); tenant isolation (user A cannot resolve
  resident of tenant B); correct today's-meds and PRN payloads against seeded
  data.
- **Frontend:** widget open/close; message send/render; error state.
- Patient-safety focus (Aisha): verify med/PRN payloads against real seeded data;
  verify no cross-tenant leakage; verify no PHI in outbound LLM request (assert
  redaction).

## 8. Rollout

- Ship behind the existing `Modules::MEDICATIONS` gate; assistant only appears
  when the module is enabled and the user has `view_medications`.
- Ships on the `multi-tenant` branch.
