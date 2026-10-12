import React from 'react';
import { useQuery } from '@tanstack/react-query';
import { Link, useNavigate } from 'react-router-dom';
import { Users, Search, MapPin, Calendar, ChevronLeft } from 'lucide-react';
import api from '../../services/api';
import EntityCardShell, { EntityCardHeader } from '../ui/EntityCardShell';
import DataPill from '../ui/DataPill';
import ResidentAvatarInline from '../ui/ResidentAvatarInline';
import { formatPacificCalendarMedium } from '../../utils/pacificTime';

/**
 * Generic "pick a resident, then proceed" step. Used as the first step of the
 * two-step add flow for resident-scoped records (vitals, sleep, appointments,
 * assessments, incidents, medications).
 *
 * @param {string} moduleLabel - small eyebrow label, e.g. "Medication Management"
 * @param {string} heading - big header title, e.g. "Add Vital Signs"
 * @param {string} subheading - helper text under the heading
 * @param {(residentId: number|string) => string} buildDestination - route to navigate to on pick
 * @param {string} backTo - route for the back button
 */
export default function ResidentPickerPage({
    moduleLabel = 'Add Record',
    heading = 'Select a Resident',
    subheading = 'Choose the resident this record is for, then continue.',
    buildDestination,
    backTo,
    icon: Icon = Users,
}) {
    const navigate = useNavigate();
    const [search, setSearch] = React.useState('');
    const [debouncedSearch, setDebouncedSearch] = React.useState('');

    React.useEffect(() => {
        const timeout = setTimeout(() => setDebouncedSearch(search.trim()), 350);
        return () => clearTimeout(timeout);
    }, [search]);

    const { data, isLoading, error } = useQuery({
        queryKey: ['resident-picker', debouncedSearch],
        queryFn: async () => {
            const params = { per_page: 100 };
            if (debouncedSearch) params.search = debouncedSearch;
            const response = await api.get('/residents', { params });
            return response.data;
        },
    });

    const residents = React.useMemo(() => data?.data ?? [], [data?.data]);

    return (
        <div className="space-y-6">
            <header
                className="rounded-2xl p-6 text-white shadow-lg"
                style={{ background: `linear-gradient(to right, var(--theme-primary), var(--theme-primary-hover), var(--theme-primary))` }}
            >
                <div className="flex items-start gap-3">
                    {backTo && (
                        <button
                            type="button"
                            onClick={() => navigate(backTo)}
                            className="mt-1 inline-flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-lg bg-white/10 text-white hover:bg-white/20"
                            aria-label="Back"
                        >
                            <ChevronLeft className="h-5 w-5" />
                        </button>
                    )}
                    <div>
                        <p className="text-sm font-medium uppercase tracking-wide opacity-90">{moduleLabel}</p>
                        <h1 className="text-2xl font-semibold md:text-3xl">{heading}</h1>
                        <p className="mt-2 max-w-xl text-sm opacity-90">{subheading}</p>
                    </div>
                </div>
            </header>

            <section className="rounded-2xl bg-white p-6 shadow-sm">
                <div className="relative w-full md:w-96">
                    <Search className="pointer-events-none absolute left-3 top-1/2 h-5 w-5 -translate-y-1/2 text-gray-400" />
                    <input
                        type="search"
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                        placeholder="Search residents by name..."
                        autoFocus
                        className="w-full rounded-lg border border-gray-200 bg-white py-2 pl-11 pr-4 text-sm shadow-sm focus:outline-none focus:ring-2"
                        style={{ '--tw-ring-color': 'var(--theme-primary-bg)' }}
                    />
                </div>
            </section>

            {error ? (
                <div className="rounded-2xl border border-red-200 bg-red-50 p-6 text-sm text-red-700">
                    Unable to load residents. {error.response?.data?.message || error.message}
                </div>
            ) : isLoading ? (
                <div className="flex min-h-[200px] items-center justify-center rounded-2xl bg-white shadow-sm">
                    <div
                        className="h-10 w-10 animate-spin rounded-full border-2 border-t-4"
                        style={{ borderColor: 'var(--theme-primary-bg)', borderTopColor: 'var(--theme-primary)' }}
                    />
                </div>
            ) : residents.length === 0 ? (
                <div className="rounded-2xl border border-dashed border-gray-200 bg-white p-12 text-center shadow-sm">
                    <Icon className="mx-auto h-12 w-12" style={{ color: 'var(--theme-primary-bg)' }} />
                    <h3 className="mt-4 text-lg font-semibold text-gray-900">No residents found</h3>
                    <p className="mt-2 text-sm text-gray-500">Try adjusting your search query.</p>
                </div>
            ) : (
                <section className="grid gap-6 sm:grid-cols-2 xl:grid-cols-3">
                    {residents.map((resident) => {
                        const fullName = [resident.first_name, resident.middle_names, resident.last_name]
                            .filter(Boolean)
                            .join(' ');
                        const branchName = resident?.branch?.name ?? 'Unassigned';

                        return (
                            <Link
                                key={resident.id}
                                to={buildDestination(resident.id)}
                                aria-label={fullName ? `Continue with ${fullName}` : 'Continue with this resident'}
                                className="block rounded-2xl text-left text-inherit no-underline outline-none focus-visible:ring-2 focus-visible:ring-[var(--theme-primary)] focus-visible:ring-offset-2"
                            >
                                <EntityCardShell className="cursor-pointer">
                                    <EntityCardHeader
                                        left={<ResidentAvatarInline resident={resident} className="h-10 w-10 text-xs" />}
                                        right={
                                            <span
                                                className="inline-flex rounded-lg border border-emerald-200/80 bg-emerald-50/80 p-2 text-[var(--theme-primary)]"
                                                aria-hidden
                                            >
                                                <Icon className="h-4 w-4" strokeWidth={2.25} />
                                            </span>
                                        }
                                    />
                                    <h3 className="text-lg font-bold leading-snug text-slate-900 sm:text-xl">
                                        {fullName || 'Unnamed Resident'}
                                    </h3>
                                    <div className="mt-4 grid grid-cols-1 gap-2.5 sm:grid-cols-2">
                                        <DataPill icon={MapPin}>
                                            <span className="font-normal text-slate-600">{branchName}</span>
                                        </DataPill>
                                        {resident.room_number && (
                                            <DataPill icon={Calendar}>
                                                <span className="font-normal text-slate-600">Room {resident.room_number}</span>
                                            </DataPill>
                                        )}
                                    </div>
                                </EntityCardShell>
                            </Link>
                        );
                    })}
                </section>
            )}
        </div>
    );
}
