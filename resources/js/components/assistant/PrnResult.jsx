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
