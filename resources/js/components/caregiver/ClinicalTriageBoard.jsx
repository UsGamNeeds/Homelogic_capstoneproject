import React from 'react';
import { useQuery } from '@tanstack/react-query';
import { Link } from 'react-router-dom';
import { Pill, MapPin, Clock, ChevronRight, CalendarCheck } from 'lucide-react';
import { dashboardStatsQueryOptions } from '../../queries/dashboardStats';
import EmptyState from '../ui/EmptyState';

/**
 * Cross-resident clinical triage: what needs doing across the house right now.
 * Deliberately NOT a resident list — picking a person belongs in the Residents hub.
 *
 * Timezone note: the API only ever emits slots scheduled for TODAY in the facility's
 * timezone (DashboardService::getMedicationReminders), and `due_time` is already
 * formatted server-side in that timezone. `due_at` is a UTC instant, so parsing it in
 * the browser to derive a Today/Tomorrow label reports the wrong DAY whenever the
 * viewer's timezone differs from the facility's — 8:00 PM in Los Angeles is 03:00 the
 * next day in UTC. We therefore display the server's `due_time` verbatim and never
 * derive a day label in the browser. `due_at` is used only for ordering, where a UTC
 * instant is unambiguous.
 */

/** Sort key that keeps slots in real chronological order rather than alphabetical time strings. */
function slotSortKey(reminder) {
    const parsed = reminder.due_at ? new Date(reminder.due_at).getTime() : NaN;
    return Number.isNaN(parsed) ? Number.MAX_SAFE_INTEGER : parsed;
}

/**
 * Group doses into the rounds a caregiver actually walks: one block per due time.
 * @param {Array<object>} reminders
 * @returns {Array<{key: string, timeLabel: string, sortKey: number, items: Array<object>}>}
 */
function groupIntoRounds(reminders) {
    const rounds = new Map();

    for (const reminder of reminders) {
        const timeLabel = reminder.due_time || 'Unscheduled';
        const existing = rounds.get(timeLabel);

        if (existing) {
            existing.items.push(reminder);
            existing.sortKey = Math.min(existing.sortKey, slotSortKey(reminder));
            continue;
        }

        rounds.set(timeLabel, { key: timeLabel, timeLabel, sortKey: slotSortKey(reminder), items: [reminder] });
    }

    return [...rounds.values()].sort((a, b) => a.sortKey - b.sortKey);
}

/** Where a caregiver goes to actually give the dose. */
function administerPath(residentId) {
    return residentId ? `/my-residents/${residentId}/medications/mar` : '/medications/residents';
}

function RoundBlock({ round }) {
    return (
        <section className="rounded-2xl border border-gray-100 bg-white shadow-sm">
            <header className="flex items-center justify-between gap-3 border-b border-gray-100 px-4 py-3">
                <div className="flex min-w-0 items-center gap-2">
                    <Clock className="h-4 w-4 flex-shrink-0 text-gray-400" aria-hidden="true" />
                    <h3 className="truncate text-sm font-semibold text-gray-900">{round.timeLabel}</h3>
                </div>
                <span className="flex-shrink-0 rounded-full bg-[var(--theme-primary-bg)] px-2.5 py-0.5 text-xs font-semibold text-[var(--theme-primary)]">
                    {round.items.length} {round.items.length === 1 ? 'dose' : 'doses'}
                </span>
            </header>

            <ul className="divide-y divide-gray-100">
                {round.items.map((item, index) => (
                    <li key={`${item.medication_id}-${item.due_time}-${index}`}>
                        <Link
                            to={administerPath(item.resident_id)}
                            className="flex items-center justify-between gap-4 px-4 py-3 transition-colors hover:bg-gray-50"
                        >
                            <div className="flex min-w-0 items-center gap-3">
                                <span className="inline-flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-xl bg-[var(--theme-primary-bg)] text-[var(--theme-primary)]">
                                    <Pill className="h-4 w-4" aria-hidden="true" />
                                </span>
                                <div className="min-w-0">
                                    <p className="truncate text-sm font-semibold text-gray-900">{item.resident_name}</p>
                                    <p className="truncate text-xs text-gray-500">
                                        {item.medication_name}
                                        {item.medication_dosage ? ` · ${item.medication_dosage}` : ''}
                                    </p>
                                </div>
                            </div>
                            <div className="flex flex-shrink-0 items-center gap-3">
                                {item.room && item.room !== 'N/A' && (
                                    <span className="hidden items-center gap-1 text-xs text-gray-400 sm:flex">
                                        <MapPin className="h-3 w-3" aria-hidden="true" />
                                        Room {item.room}
                                    </span>
                                )}
                                <ChevronRight className="h-4 w-4 text-gray-300" aria-hidden="true" />
                            </div>
                        </Link>
                    </li>
                ))}
            </ul>
        </section>
    );
}

export default function ClinicalTriageBoard() {
    const { data: stats, isLoading, error } = useQuery(dashboardStatsQueryOptions);

    const reminders = React.useMemo(
        () => (Array.isArray(stats?.medication_reminders) ? stats.medication_reminders : []),
        [stats?.medication_reminders],
    );

    const rounds = React.useMemo(() => groupIntoRounds(reminders), [reminders]);
    const residentCount = React.useMemo(
        () => new Set(reminders.map((r) => r.resident_id).filter(Boolean)).size,
        [reminders],
    );

    return (
        <div className="space-y-4">
            <div className="rounded-2xl border border-gray-100 bg-white px-4 py-3 shadow-sm">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div className="min-w-0">
                        <h2 className="text-base font-semibold leading-snug text-gray-900">Medications due</h2>
                        <p className="mt-0.5 text-xs text-gray-500">
                            Scheduled in the next 24 hours and not yet given.
                        </p>
                    </div>
                    {reminders.length > 0 && (
                        <p className="flex-shrink-0 text-xs text-gray-500">
                            <span className="font-semibold text-gray-900">{reminders.length}</span>
                            {reminders.length === 1 ? ' dose' : ' doses'} across{' '}
                            <span className="font-semibold text-gray-900">{residentCount}</span>
                            {residentCount === 1 ? ' resident' : ' residents'}
                        </p>
                    )}
                </div>
            </div>

            {error ? (
                <div className="rounded-2xl border border-red-200 bg-red-50 p-6 text-sm text-red-700">
                    Unable to load the clinical board. {error.response?.data?.message || error.message}
                </div>
            ) : isLoading ? (
                <div
                    className="flex min-h-[200px] items-center justify-center rounded-2xl bg-white shadow-sm"
                    aria-live="polite"
                >
                    <div className="h-6 w-6 animate-spin rounded-full border-2 border-gray-200 border-t-[var(--theme-primary)]" />
                    <span className="ml-2 text-sm text-gray-500">Loading clinical board…</span>
                </div>
            ) : rounds.length === 0 ? (
                <EmptyState
                    icon={CalendarCheck}
                    title="Nothing scheduled in the next 24 hours"
                    description="Doses already given today are not listed here. Check the medication log to confirm what was administered."
                    action={
                        <Link
                            to="/medication-history"
                            className="inline-flex items-center rounded-lg bg-[var(--theme-primary)] px-4 py-2 text-sm font-medium text-[var(--theme-text-on-primary)] transition-colors hover:bg-[var(--theme-primary-hover)]"
                        >
                            Open medication log
                        </Link>
                    }
                />
            ) : (
                <div className="space-y-3">
                    {rounds.map((round) => (
                        <RoundBlock key={round.key} round={round} />
                    ))}
                </div>
            )}
        </div>
    );
}
