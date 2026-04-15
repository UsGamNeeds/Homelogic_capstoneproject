import React from 'react';
import { Link, useParams } from 'react-router-dom';
import { ClipboardList, ExternalLink } from 'lucide-react';

export default function MedicationHubMedLogTab() {
    const { residentId } = useParams();
    const historyHref = `/medication-history?resident=${encodeURIComponent(residentId || '')}`;

    return (
        <div className="rounded-xl border border-gray-100 bg-white p-8 shadow-sm space-y-4">
            <div className="flex items-center gap-2 text-gray-900">
                <ClipboardList className="w-5 h-5 text-[var(--theme-primary)]" aria-hidden="true" />
                <h2 className="text-base font-bold">Medication administration log</h2>
            </div>
            <p className="text-sm text-gray-600 max-w-xl">
                Full administration history with filters lives in the clinical <strong>Medication history</strong> workspace.
                It opens scoped to this resident.
            </p>
            <Link
                to={historyHref}
                className="inline-flex items-center gap-2 rounded-lg bg-[var(--theme-primary)] px-4 py-2.5 text-sm font-bold text-[var(--theme-text-on-primary)] hover:opacity-90 transition-opacity"
            >
                Open medication history
                <ExternalLink className="w-4 h-4 opacity-90" aria-hidden="true" />
            </Link>
        </div>
    );
}
