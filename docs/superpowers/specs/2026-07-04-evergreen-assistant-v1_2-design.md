# Evergreen Assistant v1.2 — Visual Refresh Design Spec

**Date:** 2026-07-04
**Status:** Approved (design), pending implementation plan
**Branch:** multi-tenant
**Builds on:** v1 + v1.1 (shipped). Prior specs in `docs/superpowers/specs/2026-07-04-evergreen-assistant-*.md`.

## 1. Summary

A frontend-only visual refresh of the assistant drawer. Today the bot answer is a
plain `whitespace-pre-line` text wall (`- name: status` lines), which is hard to scan
and overwhelming for residents with many medications. This iteration renders the
already-returned structured `data` as grouped, color-coded medication rows, tames long
lists by collapsing completed meds, and refreshes the widget chrome.

**No backend change.** The service already returns the structured `data` this refresh
needs; only `resources/js/` changes.

## 2. Goals & Non-Goals

### Goals
- Render `todays_meds` answers as structured rows with color-coded status pills.
- Tame long lists: a summary count bar + status grouping + collapsed "done today".
- Render `prn_status` answers as structured rows (keep the safety note).
- Refresh chrome: header with icon + subtitle, restyled bubbles, nicer loading + empty state.
- Split the growing widget into small, focused components.

### Non-Goals (v1.2)
- No backend/API change.
- No new intents, no conversation memory, no new data fields.
- No JS test runner (none exists); verification is `npm run build` + manual browser smoke.
- No change to the chip behavior or the "no PHI to LLM" path.

## 3. Data Contract (already returned — no change)

`POST /assistant/query` → `{ data: { intent, answer, data, suggestions } }`.

- `intent`: `todays_meds` | `prn_status` | `unknown`.
- For `todays_meds`, `data` is a list of:
  `{ id, name, is_fully_administered_today: bool, is_overdue: bool, next_dose_at: string|null }`.
- For `prn_status`, `data` is a list of: `{ id, name, last_given_at: string|null }`.
- `answer`: human string — retained as the fallback for any reply whose `intent` is not
  `todays_meds`/`prn_status` or whose `data` is empty.
- `suggestions`: `[{label, message}]` (unchanged; rendered as chips).

## 4. Rendering Rules

### 4.1 `todays_meds` — grouped med rows

Derive each med's bucket from its flags (no new data needed):

| Bucket | Condition | Pill |
|--------|-----------|------|
| Overdue | `is_overdue === true` | red — "OVERDUE" |
| Due | `!is_overdue && !is_fully_administered_today && next_dose_at` | amber — "due HH:MM" |
| Done | `is_fully_administered_today === true` | green — "done" |
| No schedule | none of the above (`!next_dose_at`, not done) | gray — "no times today" |

- **Summary bar** at top of the card: colored-dot counts, e.g. `● 3 overdue  ● 2 due  ● 11 done`
  (omit any zero-count group). Derived by counting buckets.
- **Order:** Overdue group, then Due group, then No-schedule group, then a **collapsed**
  `Done today (N)` disclosure (closed by default; click to expand). If there are zero done
  meds, omit the disclosure. If total meds ≤ a small threshold (e.g. 4), render flat
  (no collapse) since there's nothing to tame.
- **Row:** med name (left, truncates) + status pill (right). `HH:MM` formatted from
  `next_dose_at` (ISO) in the browser's local time.
- Empty `data` → fall back to the `answer` text ("No active medications…").

### 4.2 `prn_status` — PRN rows

- Rows: med name + "last given `YYYY-MM-DD HH:MM`" or a muted "no record" pill.
- Keep the safety note beneath: "Shows last-given only — can't advise whether it's safe
  to give again." (already in `answer`; render it as a footnote line).
- Empty `data` → fall back to `answer`.

### 4.3 Other replies

`unknown` / help / no-meds / no-prn / error → render the `answer` string in a bot bubble
(as today). Suggestion chips render below any bot message that has them.

### 4.4 Chrome

- **Header:** small bot icon (lucide `Bot` or `MessageCircle`) + "Assistant" + a muted
  subtitle "Meds & PRN helper"; close button.
- **Bubbles:** user messages right-aligned accent; bot content left-aligned in a light
  surface. Med/PRN cards render as bot content (not inside a chat bubble — a card).
- **Loading:** three animated typing dots instead of the "Thinking…" text.
- **Empty state:** one-line hint + starter chips (unchanged data source), tidied spacing.
- Colors use existing theme tokens (`--theme-primary`) for accents; status colors use
  Tailwind red/amber/green/gray scales with dark-mode variants. No new color system.

## 5. Component Structure

New folder `resources/js/components/assistant/`:

- `ChatWidget.jsx` — drawer shell + state (open, input, messages), send path, starter
  query. Mounted once in `Layout.jsx` (update the import path).
- `MedResult.jsx` — renders a `todays_meds` result (summary bar + groups + collapse).
- `PrnResult.jsx` — renders a `prn_status` result.
- `StatusPill.jsx` — small colored pill; takes a `tone` ('overdue'|'due'|'done'|'muted')
  and label.
- `Chip.jsx` — the suggestion/starter chip button (extracted from current inline `Chip`).

`resources/js/hooks/useAssistant.js` is unchanged (already returns `intent`/`data`).
A bot message object becomes `{ role: 'bot', intent, answer, data, suggestions }`; the
renderer picks `MedResult` / `PrnResult` / plain text by `intent` + `data` length.

## 6. Security & Multi-Tenancy

- No backend or data-flow change; the "no PHI to LLM" and FacilityScope guarantees are
  untouched. This is presentation only.
- No `dangerouslySetInnerHTML`; all resident/med strings render as escaped text nodes.

## 7. Testing

- `npm run build` (exit 0) — no JS test runner in the repo.
- Manual browser smoke (human): a resident with many meds shows the summary bar, grouped
  rows, collapsed "done", and correct pill colors; a PRN query shows last-given rows; an
  unknown query shows the plain help text + chips.
- No backend tests (no backend change).

## 8. Known Limitations (v1.2)

- Grouping/pills are derived from existing flags only; no new clinical signals.
- `HH:MM` uses browser local time (consistent with the rest of the SPA).
- Collapse threshold is a fixed small number, not user-configurable.
