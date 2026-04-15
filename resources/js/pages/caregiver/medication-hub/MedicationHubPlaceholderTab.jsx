import React from 'react';
import { Construction } from 'lucide-react';

export default function MedicationHubPlaceholderTab({ title, description }) {
    return (
        <div className="rounded-xl border border-dashed border-gray-200 bg-white p-10 text-center shadow-sm">
            <Construction className="mx-auto h-10 w-10 text-gray-300" aria-hidden="true" />
            <h2 className="mt-4 text-lg font-bold text-gray-900">{title}</h2>
            <p className="mt-2 max-w-md mx-auto text-sm text-gray-500">
                {description || 'This section is planned in a later implementation phase.'}
            </p>
        </div>
    );
}
