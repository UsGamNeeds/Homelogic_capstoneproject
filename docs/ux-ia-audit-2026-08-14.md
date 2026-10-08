# UX / IA audit — Evergreen (HomeLogic360)

Date: 2026-08-14 · Branch: `multi-tenant` · Audited against production `https://homelogic360.net`
Status: **audit + plan. No code changed yet. Awaiting approval.**

Taal's report: "I select a resident, then I have to select the resident again to create any record — two, three clicks to reach an actual task. Medication is in both portals, vitals is in both portals. Things are not organized at all."

Both complaints are confirmed. Root causes below, with live evidence.

---

## 1. Evidence: the click-path I actually walked

Goal: record vitals for one resident (a caregiver's single most common task).

| # | Action | URL landed on | Problem |
|---|---|---|---|
| 1 | Sidebar → Residents | `/my-residents` | — |
| 2 | Card → View profile | `/my-residents/41` | Correct. Resident hub, scoped, good design |
| 3 | Hub tab → Vitals | `/my-residents/41?tab=vitals` | Correct. Shows his vitals |
| 4 | `+ Record Vitals` | `/vitals?resident=41` | **Ejected from the resident hub into the global Clinical list.** Sidebar highlight jumps Residents → Clinical |
| 5 | — | same page | **Filter silently dropped.** Dropdown reads "All Residents"; list shows Joseph *and* Brittany |
| 6 | `Add Vitals` | `/vitals/add` | **"Select a resident to record vitals for."** Pick Joseph a second time |
| 7 | Pick resident | `/vitals/add/41` | Form finally opens |

**5 navigations and 2 resident selections to reach one form.** Target is 2 navigations and 1 selection.

The same pattern applies to medications, sleep, appointments, incidents, assessments and blood glucose.

---

## 2. Root cause A — the resident context is thrown away

There is no global "selected resident" state. Every page re-derives it from the URL via
`resources/js/utils/headerResidentSwitcher.js` (`parseResidentContextId`, canonical key `residentId`).
`contexts/AssistantResidentContext.jsx` looks like the missing piece but only feeds the AI chat widget.

### A1. Param-name mismatch (this is a real bug, not just IA)

- `resources/js/pages/caregiver/ResidentHubPage.jsx:840` sends `` navigate(`/vitals?resident=${residentId}`) ``
- `resources/js/pages/Vitals.jsx:68-72` reads `RESIDENT_CONTEXT_QUERY_KEY` (`'residentId'`) or `'resident_id'`

`resident` matches neither, so the filter is discarded with no error. Step 5 above.

### A2. "Add" buttons discard a resident the page already knows

Every module has a scoped/unscoped route pair in `Root.jsx` — `vitals/add` (renders the picker grid)
vs `vitals/add/:residentId`. The list pages compute the current resident and then navigate to the
**unscoped** path anyway:

| Page | Knows the resident at | "Add" navigates to |
|---|---|---|
| `pages/Vitals.jsx:271`, `:500` | `:70` | `/vitals/add` |
| `pages/Medications.jsx:1210` | `:180` | `/medications/add` |
| `pages/Sleep.jsx:444` | `:22` | `/sleep/add` |
| `pages/Appointments.jsx:805` | `:26` | `/appointments/add` |
| `pages/Incidents.jsx:683`, `:731` | — | `/incidents/add` |
| `pages/Assessments.jsx:367` | — | `/assessments/add` |

### A3. Hub quick-actions point at list pages instead of forms

- `ResidentHubPage.jsx:840` / `:884` — "+ Record Vitals" → the filtered **list**, not the form.
- `ResidentHubPage.jsx:943` / `:966` — "+ Schedule" → `/appointments/create/${residentId}`. **This one is correct.**

The right pattern already exists in the same file. The others just don't follow it.

---

## 3. Root cause B — two portals do the same job

### B1. `/clinical` and `/my-residents` are literally the same screen

`pages/caregiver/ClinicalHubPage.jsx` and `pages/caregiver/ResidentsHubPage.jsx` both render only
`components/caregiver/HubResidentsOverview.jsx`. Verified live: identical resident card grid, identical
filters. The only difference is Residents cards expose 3 actions and Clinical cards 2.

Two sidebar entries, one destination. Nothing tells the user which to use.

### B2. Clinical tabs duplicate the resident hub tabs

| Resident hub (`/my-residents/41`) | Clinical (`/clinical`) |
|---|---|
| Overview, **Medications**, **Vitals**, **Assessments**, **Appointments**, **Charts**, T-Logs, Care Plan, Documents, Profile | Overview, **Vitals**, Sleep, **Medications**, Medication history, Deliveries, **Appointments**, **Assessments**, Blood Glucose, **Charts** |

Five tabs are duplicated, **and the order differs** (hub puts Medications 2nd/Vitals 3rd; Clinical puts
Vitals 2nd/Medications 4th), so muscle memory breaks when moving between them. Sleep, Deliveries,
Medication history and Blood Glucose exist *only* in Clinical, so neither is a superset — the user must
learn both.

Also: the Clinical tab bar renders on `/vitals` but **not** on `/clinical` itself.

### B3. Other duplicate entry points (20 total found)

- **Pharmacy / Deliveries** — in Clinical tabs, Management tabs, *and* the per-resident medication hub.
- **Leave Requests** — two routes, `/leave-requests` and `/team/leave-requests`, chosen by role.
  `Layout.jsx:471` computes `leaveRequestsPath` for this and is then never used — dead code.
- **Label collisions**: "Billing" = facility expenses (Management) vs SaaS subscription (Team).
  "Staff" = clock-in/visitors (Management) vs user directory (Team).
- **Residents** — three surfaces: `/my-residents`, `/residents`, `/organization/residents`.
- **Dashboard "Modules Overview"** — a 12-button grid that is a third parallel nav on top of
  sidebar + section tabs.

---

## 4. Root cause C — Filament admin nav is hand-built

`app/Filament/Navigation/CustomNavigationProvider.php` (~550 lines) is wired at
`AdminPanelProvider.php:46` and calls `$builder->items([])->groups([])`, then returns
`->items($items)->groups([])` (lines 551/554).

Consequences:
- `$navigationGroup` / `$navigationLabel` / `$navigationSort` on **all 37 resources** is dead code.
- `AdminPanelProvider.php:54-59`'s `->navigationGroups([...])` never renders.
- Removing the custom provider will **not** restore sane grouping — the resource group strings don't
  match the panel's list. This is a landmine for whoever tries.
- Reports group has 9 children, Administration 10 (both past the ~8 comfortable limit).

---

## 5. The IA principle to fix this

> **One object, one home. Global lists are for triage and reading. Writes happen in the object's context.**

Concretely:

- **Residents** = the workspace. Everything you do *to a person* happens in that person's hub.
- **Clinical** = cross-resident triage only: what's due, what's overdue, what's abnormal, today's round.
  It answers "what needs doing across the house", never "edit this one person".
- **Reports** = read-only analytics. No create actions.
- Every create action inherits its resident from context. A resident picker appears **only** when the
  user genuinely started with no resident in mind.

---

## 6. Proposed plan

### Phase 0 — stop the bleeding (small, safe, high payoff)

1. Fix the param mismatch: `ResidentHubPage.jsx:840`/`:884` → navigate to `/vitals/add/${residentId}`
   (the form), not `/vitals?resident=`.
2. Audit every hub quick-action against the correct `:943` pattern; point each at its scoped create route.
3. Make the six list-page "Add" buttons forward the resident they already hold:
   `` navigate(residentFilter ? `/vitals/add/${residentFilter}` : '/vitals/add') ``.
4. Standardise the query key on `residentId` everywhere; delete the `resident` / `resident_id` variants
   or alias them in `parseResidentContextId` so old links keep working.

Result: the walked path drops from 5 navigations + 2 selections to 2 + 1.

### Phase 1 — split the two portals

5. `/clinical` overview stops being a resident grid. It becomes a triage board: meds due now, vitals
   overdue, abnormal vitals, pending assessments, open incidents.
6. Resident-scoped tabs leave Clinical. Clinical keeps only cross-resident views
   (Medication history, Deliveries, Blood Glucose trends, Charts).
7. Align hub tab order with Clinical tab order for whatever remains in both.
8. Render the section tab bar consistently — including on `/clinical`.

### Phase 2 — dedupe and rename

9. Pick one home for Pharmacy and Deliveries (recommend: Management, with a read-only slice in the
   resident hub).
10. Collapse Leave Requests to one route; delete dead `leaveRequestsPath` at `Layout.jsx:471`.
11. Rename collisions: "Facility expenses" vs "Subscription & billing"; "Staff & site" vs "Staff directory".
12. Decide the fate of `/residents` vs `/my-residents` vs `/organization/residents` — recommend
    `/residents` (all, admin) and `/my-residents` (assigned, caregiver), retiring the third.
13. Reconsider the dashboard "Modules Overview" grid now that the sidebar works.

### Phase 3 — Filament

14. Either delete `CustomNavigationProvider` and restore real `$navigationGroup` values on the
    resources, or keep it and strip the dead `->navigationGroups()` config plus the 37 unused
    properties. Do not leave both.
15. Split Reports (9) and Administration (10) into smaller groups.

---

## 7. Decisions I need from Taal

1. **Phase 0 alone, or Phase 0+1?** Phase 0 is contained and low-risk. Phase 1 changes what Clinical
   *means* and will surprise existing staff until they relearn it.
2. **Is Clinical-as-triage right?** I inferred it. If your caregivers actually use Clinical as
   "pick any resident, do a task", the fix is the opposite: make Clinical the resident-first entry and
   thin out the Residents portal instead.
3. **Deployment**: this is live production with real residents. Staged behind a branch and demoed to
   you first, or shipped in small increments?

---

## 8. Notes for whoever picks this up

- Local repo has **no `node_modules`** but does have `public/build/manifest.json`. Laravel serves that
  stale bundle, whose sidebar predates the hub refactor. Any local browser audit is invalid until
  `npm install && npm run build`. Audit against prod, or rebuild first.
- Local demo logins (from untracked seeders): `demo.admin@serenityafh.com` / `DemoAdmin!2026`,
  `demo.caregiver@serenityafh.com` / `DemoCaregiver!2026`.
- `demo.admin` has `facility_id=1` but `branch_id=null` and consequently sees 0 residents / 0 staff
  even though branch 1 holds 29. Possible real bug: an admin with a null branch sees nothing.
  Not investigated — out of scope for this audit.
