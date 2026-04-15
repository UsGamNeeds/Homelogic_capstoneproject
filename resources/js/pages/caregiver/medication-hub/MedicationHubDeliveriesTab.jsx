import React from 'react';
import { useQuery } from '@tanstack/react-query';
import { Link, useParams } from 'react-router-dom';
import { Truck, AlertCircle } from 'lucide-react';
import api from '../../../services/api';
import { formatPacificCalendarMedium, formatPacificDateTimeShort } from '../../../utils/pacificTime';
import logger from '../../../utils/logger';

export default function MedicationHubDeliveriesTab() {
    const { residentId } = useParams();
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
                <Link
                    to="/medication-deliveries"
                    className="text-xs font-bold text-[var(--theme-primary)] hover:underline"
                >
                    All facility deliveries →
                </Link>
            </div>

            {rows.length === 0 ? (
                <p className="text-sm text-gray-500 rounded-xl border border-gray-100 bg-white p-6">No delivery records for this resident.</p>
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
