import React from 'react';
import { useQuery } from '@tanstack/react-query';
import { Link } from 'react-router-dom';
import { Pill, MapPin, Clock, ChevronRight, CheckCircle2 } from 'lucide-react';
import { dashboardStatsQueryOptions } from '../../queries/dashboardStats';

/**
 * Timezone note: `due_time` is formatted server-side in the facility's timezone, while
 * `due_at` is a UTC instant. Deriving a Today/Tomorrow label by parsing `due_at` in the
 * browser reported the wrong DAY whenever the viewer's timezone differed from the
 * facility's (8:00 PM in Los Angeles is 03:00 the next day in UTC), so a dose due
 * tonight read as "Tomorrow". The API only ever returns slots scheduled for today, so
 * the time alone is unambiguous and no day label is derived here.
 */

export default function MedicationsDueList() {
    const { data: stats, isLoading, error } = useQuery(dashboardStatsQueryOptions);

    const reminders = React.useMemo(
        () => (Array.isArray(stats?.medication_reminders) ? stats.medication_reminders : []),
        [stats?.medication_reminders],
    );

    return (
        <div className="space-y-6">
            <header
                className="rounded-2xl p-6 text-white shadow-lg"
                style={{
                    background: `linear-gradient(to right, var(--theme-primary), var(--theme-primary-hover), var(--theme-primary))`,
                }}
            >
                <p className="text-sm font-medium uppercase tracking-wide opacity-90">Medication Management</p>
                <h1 className="text-2xl font-semibold md:text-3xl">Medications Due</h1>
                <p className="mt-2 max-w-xl text-sm opacity-90">
                    Medications due in the next 24 hours that haven&apos;t been given yet.
                </p>
            </header>

            {error ? (
                <div className="rounded-2xl border border-red-200 bg-red-50 p-6 text-sm text-red-700">
                    Unable to load medications due. {error.response?.data?.message || error.message}
                </div>
            ) : isLoading ? (
                <div className="flex min-h-[200px] items-center justify-center rounded-2xl bg-white shadow-sm">
                    <div
                        className="h-10 w-10 animate-spin rounded-full border-2 border-t-4"
                        style={{ borderColor: 'var(--theme-primary-bg)', borderTopColor: 'var(--theme-primary)' }}
                    />
                </div>
            ) : reminders.length === 0 ? (
                <div className="rounded-2xl border border-dashed border-gray-200 bg-white p-12 text-center shadow-sm">
                    <CheckCircle2 className="mx-auto h-12 w-12 text-emerald-400" />
                    <h3 className="mt-4 text-lg font-semibold text-gray-900">All caught up</h3>
                    <p className="mt-2 text-sm text-gray-500">Nothing is due for your residents right now.</p>
                </div>
            ) : (
                <section className="space-y-3">
                    {reminders.map((reminder, index) => {
                        return (
                            <Link
                                key={`${reminder.medication_id}-${reminder.due_time}-${index}`}
                                to={
                                    reminder.resident_id
                                        ? `/my-residents/${reminder.resident_id}/medications/list`
                                        : '/medications/residents'
                                }
                                className="flex items-center justify-between gap-4 rounded-2xl border border-gray-100 bg-white p-4 shadow-sm transition-shadow hover:shadow-md"
                            >
                                <div className="flex min-w-0 items-center gap-4">
                                    <span className="inline-flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl bg-red-50 text-red-600">
                                        <Pill className="h-5 w-5" />
                                    </span>
                                    <div className="min-w-0">
                                        <p className="truncate font-semibold text-gray-900">{reminder.medication_name}</p>
                                        <p className="truncate text-sm text-gray-500">
                                            {reminder.resident_name}
                                            {reminder.medication_dosage ? ` · ${reminder.medication_dosage}` : ''}
                                        </p>
                                    </div>
                                </div>
                                <div className="flex flex-shrink-0 items-center gap-4">
                                    <div className="text-right">
                                        <p className="flex items-center gap-1 text-sm font-semibold text-gray-900">
                                            <Clock className="h-3.5 w-3.5 text-gray-400" />
                                            {reminder.due_time}
                                        </p>
                                        {reminder.room && reminder.room !== 'N/A' && (
                                            <p className="mt-0.5 flex items-center justify-end gap-1 text-xs text-gray-400">
                                                <MapPin className="h-3 w-3" />
                                                Room {reminder.room}
                                            </p>
                                        )}
                                    </div>
                                    <ChevronRight className="h-5 w-5 text-gray-300" />
                                </div>
                            </Link>
                        );
                    })}
                </section>
            )}
        </div>
    );
}
