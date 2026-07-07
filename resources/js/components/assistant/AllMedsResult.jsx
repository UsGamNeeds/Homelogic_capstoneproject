function titleFromAnswer(answer) {
    const first = (answer || '').split('\n')[0] || 'Medications';
    return first.replace(/:\s*$/, '');
}

export default function AllMedsResult({ answer, meds }) {
    return (
        <div className="mr-auto w-full max-w-[92%] rounded-xl border border-gray-200 bg-white p-3 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <div className="mb-2 text-sm font-semibold text-gray-800 dark:text-gray-100">
                {titleFromAnswer(answer)}
            </div>
            <div className="divide-y divide-gray-100 dark:divide-gray-700">
                {meds.map((m) => (
                    <div key={m.id} className="py-1.5">
                        <div className="text-sm text-gray-800 dark:text-gray-100">{m.name}</div>
                        {m.instructions ? (
                            <div className="text-xs text-gray-400 dark:text-gray-500">{m.instructions}</div>
                        ) : null}
                    </div>
                ))}
            </div>
        </div>
    );
}
