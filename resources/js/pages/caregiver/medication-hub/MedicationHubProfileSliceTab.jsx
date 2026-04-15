import React from 'react';
import { useQuery } from '@tanstack/react-query';
import { useParams } from 'react-router-dom';
import { AlertCircle, User } from 'lucide-react';
import api from '../../../services/api';
import { formatPacificCalendarMedium, calculateAgeFromPacificBirthDate } from '../../../utils/pacificTime';

export default function MedicationHubProfileSliceTab() {
    const { residentId } = useParams();
    const { data: resident, isLoading, error } = useQuery({
        queryKey: ['med-hub-resident-slice', residentId],
        queryFn: async () => {
            const res = await api.get(`/residents/${residentId}`);
            return res.data?.data ?? res.data;
        },
        enabled: !!residentId,
    });

    if (isLoading) {
        return (
            <div className="flex justify-center py-16">
                <div className="h-10 w-10 animate-spin rounded-full border-2 border-[var(--theme-primary)]/30 border-t-[var(--theme-primary)]" />
            </div>
        );
    }

    if (error || !resident) {
        return (
            <div className="rounded-xl border border-gray-200 bg-white p-6 flex gap-3 text-gray-600">
                <AlertCircle className="w-5 h-5 shrink-0" aria-hidden="true" />
                <p className="text-sm">Unable to load resident profile for medication context.</p>
            </div>
        );
    }

    const fullName = [resident.first_name, resident.middle_names, resident.last_name].filter(Boolean).join(' ');
    const age = resident.date_of_birth ? calculateAgeFromPacificBirthDate(resident.date_of_birth) : null;
    const allergies = resident.allergies
        ? Array.isArray(resident.allergies)
            ? resident.allergies.join(', ')
            : resident.allergies
        : null;
    const room = resident.room_number || resident.room;

    return (
        <div className="rounded-xl border border-gray-100 bg-white shadow-sm overflow-hidden">
            <div className="px-4 py-3 border-b border-gray-100 flex items-center gap-2">
                <User className="w-4 h-4 text-[var(--theme-primary)]" aria-hidden="true" />
                <div>
                    <h2 className="text-sm font-bold text-gray-900">Profile (medication context)</h2>
                    <p className="text-xs text-gray-500">Read-only slice — edit the full profile from the resident record.</p>
                </div>
            </div>
            <dl className="grid grid-cols-1 sm:grid-cols-2 gap-4 p-4 text-sm">
                <div>
                    <dt className="text-xs font-bold uppercase tracking-wide text-gray-400">Name</dt>
                    <dd className="mt-1 font-medium text-gray-900">{fullName || '—'}</dd>
                </div>
                <div>
                    <dt className="text-xs font-bold uppercase tracking-wide text-gray-400">DOB</dt>
                    <dd className="mt-1 text-gray-800">
                        {resident.date_of_birth ? formatPacificCalendarMedium(resident.date_of_birth) : '—'}
                        {age !== null ? ` (${age} y.o.)` : ''}
                    </dd>
                </div>
                <div>
                    <dt className="text-xs font-bold uppercase tracking-wide text-gray-400">Room</dt>
                    <dd className="mt-1 text-gray-800">{room || '—'}</dd>
                </div>
                <div>
                    <dt className="text-xs font-bold uppercase tracking-wide text-gray-400">Code status</dt>
                    <dd className="mt-1 text-gray-800">{resident.code_status || '—'}</dd>
                </div>
                <div className="sm:col-span-2">
                    <dt className="text-xs font-bold uppercase tracking-wide text-gray-400">Allergies</dt>
                    <dd className="mt-1 text-gray-800">{allergies || 'None recorded'}</dd>
                </div>
                <div className="sm:col-span-2">
                    <dt className="text-xs font-bold uppercase tracking-wide text-gray-400">Diagnoses / notes</dt>
                    <dd className="mt-1 text-gray-800">{resident.diagnoses || resident.medical_notes || '—'}</dd>
                </div>
            </dl>
        </div>
    );
}
