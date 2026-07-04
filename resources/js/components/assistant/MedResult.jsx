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
                            key={s.at ?? i}
                            className={
                                s.given
                                    ? 'text-gray-400 dark:text-gray-500'
                                    : 'font-medium text-gray-700 dark:text-gray-200'
                            }
                        >
                            {formatTime(s.at)}
                            {s.given ? ' ✓' : ''}
                            <span className="sr-only">{s.given ? ' given' : ' pending'}</span>
                        </span>
                    ))}
                </div>
            )}
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
                        aria-expanded={doneOpen}
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
