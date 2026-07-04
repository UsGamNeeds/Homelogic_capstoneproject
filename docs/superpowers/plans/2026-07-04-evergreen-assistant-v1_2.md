# Evergreen Assistant v1.2 Visual Refresh Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Render assistant med answers as grouped, color-coded rows with a summary bar and collapsible "done today", and refresh the drawer chrome — frontend only, no backend change.

**Architecture:** Split the widget into small components under `resources/js/components/assistant/`. Pure presentational components (`StatusPill`, `Chip`, `MedResult`, `PrnResult`) render from the structured `data` the API already returns; `ChatWidget` becomes the shell that stores each bot message's `{intent, answer, data, suggestions}` and dispatches to the right renderer.

**Tech Stack:** React 19, Tailwind, lucide-react 0.548, TanStack Query (unchanged hooks).

## Global Constraints

- Frontend only. No backend/API change; no new data fields. Consume the existing response `{ data: { intent, answer, data, suggestions } }`.
- `todays_meds` `data` items: `{ id, name, is_fully_administered_today: bool, is_overdue: bool, next_dose_at: string|null }`. `prn_status` `data` items: `{ id, name, last_given_at: string|null }`.
- Keep `answer` string as fallback for replies whose `intent` is not `todays_meds`/`prn_status` or whose `data` is empty.
- No `dangerouslySetInnerHTML`; all resident/med text renders as escaped nodes.
- No `console.log`/debug. New React files may be `.jsx` (repo convention — existing widget is `.jsx`, no TS).
- No JS test runner exists — verification per task is `npm run build` (exit 0). Manual browser smoke is done by the human.
- Keep the single `<ChatWidget />` mount in `Layout.jsx` (update its import path only).
- Status colors: overdue=red, due=amber, done=green, muted=gray (Tailwind scales, dark-mode variants). Accents use `--theme-primary`.

---

## File Structure

**Create:**
- `resources/js/components/assistant/StatusPill.jsx` — colored pill by `tone`.
- `resources/js/components/assistant/Chip.jsx` — suggestion/starter chip button (extracted).
- `resources/js/components/assistant/MedResult.jsx` — `todays_meds` card: title + summary bar + groups + collapsed done.
- `resources/js/components/assistant/PrnResult.jsx` — `prn_status` card: rows + safety footnote.
- `resources/js/components/assistant/ChatWidget.jsx` — refreshed drawer shell (moved here).

**Modify:**
- `resources/js/components/Layout.jsx:46` — update import path to `./assistant/ChatWidget`.

**Delete:**
- `resources/js/components/ChatWidget.jsx` (moved into `assistant/`).

**Unchanged:** `resources/js/hooks/useAssistant.js` (already returns `intent`/`data`).

---

## Task 1: Presentational components (StatusPill, Chip, MedResult, PrnResult)

**Files:**
- Create: `resources/js/components/assistant/StatusPill.jsx`
- Create: `resources/js/components/assistant/Chip.jsx`
- Create: `resources/js/components/assistant/MedResult.jsx`
- Create: `resources/js/components/assistant/PrnResult.jsx`
- Verify: `npm run build`

**Interfaces:**
- Produces:
  - `StatusPill({ tone, label })` — `tone` in `'overdue'|'due'|'done'|'muted'`.
  - `Chip({ label, onClick, disabled })`.
  - `MedResult({ answer, meds })` — `meds` = `todays_meds` data array.
  - `PrnResult({ answer, meds })` — `meds` = `prn_status` data array.

- [ ] **Step 1: Create `StatusPill.jsx`**

```jsx
const TONES = {
    overdue: 'bg-red-50 text-red-700 ring-red-600/20 dark:bg-red-950/40 dark:text-red-300',
    due: 'bg-amber-50 text-amber-700 ring-amber-600/20 dark:bg-amber-950/40 dark:text-amber-300',
    done: 'bg-green-50 text-green-700 ring-green-600/20 dark:bg-green-950/40 dark:text-green-300',
    muted: 'bg-gray-100 text-gray-600 ring-gray-500/20 dark:bg-gray-800 dark:text-gray-300',
};

export default function StatusPill({ tone = 'muted', label }) {
    return (
        <span
            className={`inline-flex shrink-0 items-center rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset ${TONES[tone] ?? TONES.muted}`}
        >
            {label}
        </span>
    );
}
```

- [ ] **Step 2: Create `Chip.jsx`** (extracted verbatim from the current widget)

```jsx
export default function Chip({ label, onClick, disabled }) {
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
```

- [ ] **Step 3: Create `MedResult.jsx`**

```jsx
import { useState } from 'react';
import { ChevronDown, ChevronRight } from 'lucide-react';
import StatusPill from './StatusPill';

const FLAT_THRESHOLD = 4;

function titleFromAnswer(answer) {
    const first = (answer || '').split('\n')[0] || 'Medications';
    return first.replace(/:\s*$/, '');
}

function formatTime(iso) {
    if (!iso) return '';
    const d = new Date(iso);
    if (Number.isNaN(d.getTime())) return '';
    return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
}

// Group meds into buckets from the flags the API already returns.
function bucketMeds(meds) {
    const buckets = { overdue: [], due: [], noSchedule: [], done: [] };
    for (const m of meds) {
        if (m.is_overdue) buckets.overdue.push(m);
        else if (m.is_fully_administered_today) buckets.done.push(m);
        else if (m.next_dose_at) buckets.due.push(m);
        else buckets.noSchedule.push(m);
    }
    return buckets;
}

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
    return (
        <div className="flex items-center justify-between gap-2 py-1">
            <span className="truncate text-sm text-gray-800 dark:text-gray-100">{med.name}</span>
            <StatusPill tone={tone} label={label} />
        </div>
    );
}

function Dot({ className }) {
    return <span className={`inline-block h-2 w-2 rounded-full ${className}`} />;
}

export default function MedResult({ answer, meds }) {
    const [doneOpen, setDoneOpen] = useState(false);
    const buckets = bucketMeds(meds);
    const flat = meds.length <= FLAT_THRESHOLD;

    const summary = [
        buckets.overdue.length > 0 && { dot: 'bg-red-500', text: `${buckets.overdue.length} overdue` },
        buckets.due.length > 0 && { dot: 'bg-amber-500', text: `${buckets.due.length} due` },
        buckets.done.length > 0 && { dot: 'bg-green-500', text: `${buckets.done.length} done` },
    ].filter(Boolean);

    const showDoneCollapsed = !flat && buckets.done.length > 0;

    return (
        <div className="mr-auto w-full max-w-[92%] rounded-xl border border-gray-200 bg-white p-3 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <div className="mb-2 text-sm font-semibold text-gray-800 dark:text-gray-100">
                {titleFromAnswer(answer)}
            </div>

            {summary.length > 0 && (
                <div className="mb-2 flex flex-wrap items-center gap-3 text-xs text-gray-500 dark:text-gray-400">
                    {summary.map((s, i) => (
                        <span key={i} className="inline-flex items-center gap-1">
                            <Dot className={s.dot} />
                            {s.text}
                        </span>
                    ))}
                </div>
            )}

            <div className="divide-y divide-gray-100 dark:divide-gray-700">
                {buckets.overdue.map((m) => (
                    <MedRow key={m.id} med={m} />
                ))}
                {buckets.due.map((m) => (
                    <MedRow key={m.id} med={m} />
                ))}
                {buckets.noSchedule.map((m) => (
                    <MedRow key={m.id} med={m} />
                ))}
                {flat && buckets.done.map((m) => <MedRow key={m.id} med={m} />)}
            </div>

            {showDoneCollapsed && (
                <div className="mt-1">
                    <button
                        type="button"
                        onClick={() => setDoneOpen((o) => !o)}
                        className="flex items-center gap-1 py-1 text-xs font-medium text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
                    >
                        {doneOpen ? <ChevronDown className="h-4 w-4" /> : <ChevronRight className="h-4 w-4" />}
                        Done today ({buckets.done.length})
                    </button>
                    {doneOpen && (
                        <div className="divide-y divide-gray-100 dark:divide-gray-700">
                            {buckets.done.map((m) => (
                                <MedRow key={m.id} med={m} />
                            ))}
                        </div>
                    )}
                </div>
            )}
        </div>
    );
}
```

- [ ] **Step 4: Create `PrnResult.jsx`**

```jsx
import StatusPill from './StatusPill';

function titleFromAnswer(answer) {
    const first = (answer || '').split('\n')[0] || 'PRN medications';
    return first.replace(/:\s*$/, '');
}

function formatDateTime(iso) {
    if (!iso) return '';
    const d = new Date(iso);
    if (Number.isNaN(d.getTime())) return '';
    return d.toLocaleString([], {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function PrnRow({ med }) {
    const label = med.last_given_at ? `Last ${formatDateTime(med.last_given_at)}` : 'No record';
    return (
        <div className="flex items-center justify-between gap-2 py-1">
            <span className="truncate text-sm text-gray-800 dark:text-gray-100">{med.name}</span>
            <StatusPill tone="muted" label={label} />
        </div>
    );
}

export default function PrnResult({ answer, meds }) {
    return (
        <div className="mr-auto w-full max-w-[92%] rounded-xl border border-gray-200 bg-white p-3 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <div className="mb-2 text-sm font-semibold text-gray-800 dark:text-gray-100">
                {titleFromAnswer(answer)}
            </div>
            <div className="divide-y divide-gray-100 dark:divide-gray-700">
                {meds.map((m) => (
                    <PrnRow key={m.id} med={m} />
                ))}
            </div>
            <p className="mt-2 text-xs text-gray-400 dark:text-gray-500">
                Shows last-given only — can't advise whether it's safe to give again.
            </p>
        </div>
    );
}
```

- [ ] **Step 5: Build to verify it compiles**

Run: `npm run build`
Expected: exit 0. (These components are not yet imported anywhere; Vite tree-shakes them but must still parse them without error.) Quote the tail.

- [ ] **Step 6: Commit**

```bash
git add resources/js/components/assistant/StatusPill.jsx resources/js/components/assistant/Chip.jsx resources/js/components/assistant/MedResult.jsx resources/js/components/assistant/PrnResult.jsx
git commit -m "feat(assistant): add med/prn result cards + status pill components"
```

---

## Task 2: Refresh ChatWidget shell and wire it up

**Files:**
- Create: `resources/js/components/assistant/ChatWidget.jsx`
- Modify: `resources/js/components/Layout.jsx:46`
- Delete: `resources/js/components/ChatWidget.jsx`
- Verify: `npm run build`

**Interfaces:**
- Consumes: `Chip`, `MedResult`, `PrnResult` (Task 1); `useAssistant`, `useStarterSuggestions` from `../../hooks/useAssistant`.
- Produces: the refreshed default-export `ChatWidget` mounted in `Layout.jsx`.

- [ ] **Step 1: Create `resources/js/components/assistant/ChatWidget.jsx`**

```jsx
import { useState } from 'react';
import { Bot, X, Send, MessageCircle } from 'lucide-react';
import { useAssistant, useStarterSuggestions } from '../../hooks/useAssistant';
import Chip from './Chip';
import MedResult from './MedResult';
import PrnResult from './PrnResult';

function BotContent({ msg }) {
    const hasData = Array.isArray(msg.data) && msg.data.length > 0;
    if (msg.intent === 'todays_meds' && hasData) {
        return <MedResult answer={msg.answer} meds={msg.data} />;
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
    const [messages, setMessages] = useState([]); // user: {role,text}; bot: {role,intent,answer,data,suggestions}
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
                        <div className="flex items-center gap-2">
                            <span className="flex h-8 w-8 items-center justify-center rounded-full bg-[var(--theme-primary,#2563eb)] text-white">
                                <Bot className="h-5 w-5" />
                            </span>
                            <div className="leading-tight">
                                <div className="text-sm font-semibold text-gray-800 dark:text-gray-100">Assistant</div>
                                <div className="text-xs text-gray-400">Meds &amp; PRN helper</div>
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

- [ ] **Step 2: Update the Layout import path**

In `resources/js/components/Layout.jsx`, change line 46 from:

```javascript
import ChatWidget from './ChatWidget';
```

to:

```javascript
import ChatWidget from './assistant/ChatWidget';
```

(Do NOT change the `<ChatWidget />` mount at line ~742 — only the import path.)

- [ ] **Step 3: Delete the old widget file**

```bash
git rm resources/js/components/ChatWidget.jsx
```

- [ ] **Step 4: Build to verify**

Run: `npm run build`
Expected: exit 0, no errors referencing `ChatWidget`, `MedResult`, `PrnResult`, `StatusPill`, `Chip`, `Bot`, `ChevronDown`, or `./ChatWidget` (stale import). Quote the tail.

- [ ] **Step 5: Self-review**

Confirm: no `console.log`; `grep -rc "from './ChatWidget'" resources/js` returns 0 (no stale import); `grep -c "<ChatWidget" resources/js/components/Layout.jsx` returns 1; bot messages store `intent`+`data`; med/prn cards render only when `data` non-empty, else plain `answer`.

- [ ] **Step 6: Commit**

```bash
git add resources/js/components/assistant/ChatWidget.jsx resources/js/components/Layout.jsx
git commit -m "feat(assistant): refresh drawer chrome + render rich med/prn cards"
```

---

## Self-Review

- **Spec coverage:** rich med rows + pills (Task 1 `MedResult`/`StatusPill`), summary bar + grouping + collapsed done + flat threshold (Task 1 `MedResult`), PRN rows + safety footnote (Task 1 `PrnResult`), chrome refresh — header icon/subtitle + typing dots + tidy empty state (Task 2 `ChatWidget`), component split into `assistant/` (both tasks), `answer` fallback for non-med/empty-data replies (Task 2 `BotContent`), no backend change (nothing in `app/` touched). All spec sections mapped.
- **Type consistency:** `StatusPill({tone,label})`, `Chip({label,onClick,disabled})`, `MedResult({answer,meds})`, `PrnResult({answer,meds})` — signatures identical between Task 1 definitions and Task 2 usage. Bucket flags (`is_overdue`, `is_fully_administered_today`, `next_dose_at`, `last_given_at`) match the data contract verbatim. Message object `{role,intent,answer,data,suggestions}` consistent between `sendMessage` and `BotContent`.
- **Placeholder scan:** every code step has complete component code; no TBD/TODO; the one Layout edit shows exact before/after.
