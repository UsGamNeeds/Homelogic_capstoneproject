import React from 'react';
import { useQuery } from '@tanstack/react-query';
import { Link, useParams } from 'react-router-dom';
import { Truck, AlertCircle, Plus } from 'lucide-react';
import api from '../../../services/api';
import { formatPacificCalendarMedium, formatPacificDateTimeShort } from '../../../utils/pacificTime';
import logger from '../../../utils/logger';
import { isMedicationClinicalAdmin } from '../../../utils/medicationHubPermissions';
import { RESIDENT_CONTEXT_QUERY_KEY } from '../../../utils/headerResidentSwitcher';

export default function MedicationHubDeliveriesTab() {
    const { residentId } = useParams();

    const { data: currentUser } = useQuery({
        queryKey: ['current-user'],
        queryFn: async () => (await api.get('/user')).data,
        staleTime: 60_000,
    });
    const isClinicalAdmin = isMedicationClinicalAdmin(currentUser);
    const addDeliveryHref = `/medication-deliveries?${RESIDENT_CONTEXT_QUERY_KEY}=${encodeURIComponent(residentId ?? '')}`;

    const { data, isLoading, error } = useQuery({
        queryKey: ['med-hub-deliveries', residentId],
        queryFn: async () => (await api.get('/medication-deliveries', { params: { resident_id: residentId, per_page: 50 } })).data,
        enabled: !!residentId,
    });

    const rows = data?.data ?? [];

    if (isLoading) {
        return (
            <div className="flex justify-center py-16">
                <div className="h-10 w-10 animate-spin rounded-full border-2 border-[var(--theme-primary)]/30 border-t-[var(--theme-primary)]" />
            </div>
        );
    }

    if (error) {
        logger.warn('Medication hub deliveries load failed:', error);
        return (
            <div className="rounded-xl border border-amber-200 bg-amber-50 p-6 flex gap-3 text-amber-900">
                <AlertCircle className="w-5 h-5 shrink-0" aria-hidden="true" />
                <div>
                    <p className="font-bold text-sm">Could not load deliveries</p>
                    <p className="text-sm mt-1 opacity-90">You may need pharmacy module access, or try again later.</p>
                    <Link to="/medication-deliveries" className="inline-block mt-3 text-sm font-bold underline">
                        Open full deliveries workspace
                    </Link>
                </div>
            </div>
        );
    }

    return (
        <div className="space-y-4">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <div className="flex items-center gap-2 text-gray-900">
                    <Truck className="w-5 h-5 text-[var(--theme-primary)]" aria-hidden="true" />
                    <h2 className="text-base font-bold">Deliveries for this resident</h2>
                </div>
                <div className="flex flex-wrap items-center justify-end gap-2">
                    {isClinicalAdmin && (
                        <Link
                            to={addDeliveryHref}
                            className="inline-flex items-center gap-1.5 rounded-lg bg-[var(--theme-primary)] px-3 py-1.5 text-xs font-bold text-[var(--theme-text-on-primary)] shadow-sm hover:opacity-95 transition-opacity"
                        >
                            <Plus className="w-3.5 h-3.5 shrink-0" aria-hidden="true" />
                            Add delivery
                        </Link>
                    )}
                    <Link
                        to="/medication-deliveries"
                        className="text-xs font-bold text-[var(--theme-primary)] hover:underline"
                    >
                        All facility deliveries →
                    </Link>
                </div>
            </div>

            {rows.length === 0 ? (
                <div className="text-sm text-gray-500 rounded-xl border border-gray-100 bg-white p-6 space-y-3">
                    <p>No delivery records for this resident.</p>
                    {isClinicalAdmin && (
                        <Link
                            to={addDeliveryHref}
                            className="inline-flex items-center gap-1.5 text-sm font-bold text-[var(--theme-primary)] hover:underline"
                        >
                            <Plus className="w-4 h-4 shrink-0" aria-hidden="true" />
                            Add a delivery for this resident
                        </Link>
                    )}
                </div>
            ) : (
                <div className="overflow-x-auto rounded-xl border border-gray-100 bg-white shadow-sm">
                    <table className="min-w-full text-sm">
                        <thead className="bg-gray-50 text-left text-xs font-bold uppercase tracking-wide text-gray-500">
                            <tr>
                                <th className="px-4 py-3">Received</th>
                                <th className="px-4 py-3">Type</th>
                                <th className="px-4 py-3">Status</th>
                                <th className="px-4 py-3">Medication</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100">
                            {rows.map((row) => (
                                <tr key={row.id} className="hover:bg-gray-50/80">
                                    <td className="px-4 py-3 text-gray-700">
                                        {row.received_date ? formatPacificCalendarMedium(row.received_date) : '—'}
                                        {row.created_at ? (
                                            <span className="block text-xs text-gray-400">{formatPacificDateTimeShort(row.created_at)}</span>
                                        ) : null}
                                    </td>
                                    <td className="px-4 py-3 text-gray-600">{row.delivery_type || '—'}</td>
                                    <td className="px-4 py-3">
                                        <span className="inline-flex rounded-full border border-gray-200 bg-gray-50 px-2 py-0.5 text-xs font-semibold text-gray-700">
                                            {row.status || '—'}
                                        </span>
                                    </td>
                                    <td className="px-4 py-3 text-gray-700">{row.medication?.name || row.medication_id || '—'}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </div>
    );
}
