import React from 'react';
import { useQuery } from '@tanstack/react-query';
import { Link, useNavigate } from 'react-router-dom';
import { UserSquare2, Search, Briefcase, ChevronLeft } from 'lucide-react';
import api from '../../services/api';
import EntityCardShell, { EntityCardHeader } from '../ui/EntityCardShell';
import DataPill from '../ui/DataPill';

/**
 * Generic "pick a staff member, then proceed" step — the Employee Documents
 * analog of ResidentPickerPage, since employee documents belong to a User,
 * not a Resident.
 */
export default function StaffPickerPage({
    moduleLabel = 'Add Record',
    heading = 'Select a Staff Member',
    subheading = 'Choose the employee this record is for, then continue.',
    buildDestination,
    backTo,
}) {
    const navigate = useNavigate();
    const [search, setSearch] = React.useState('');
    const [debouncedSearch, setDebouncedSearch] = React.useState('');

    React.useEffect(() => {
        const timeout = setTimeout(() => setDebouncedSearch(search.trim()), 350);
        return () => clearTimeout(timeout);
    }, [search]);

    const { data, isLoading, error } = useQuery({
        queryKey: ['staff-picker', debouncedSearch],
        queryFn: async () => {
            const params = { active_only: 'true', per_page: 100 };
            if (debouncedSearch) params.search = debouncedSearch;
            const response = await api.get('/users', { params });
            return response.data;
        },
    });

    const staff = React.useMemo(() => data?.data ?? data ?? [], [data]);

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
                        placeholder="Search staff by name..."
                        autoFocus
                        className="w-full rounded-lg border border-gray-200 bg-white py-2 pl-11 pr-4 text-sm shadow-sm focus:outline-none focus:ring-2"
                        style={{ '--tw-ring-color': 'var(--theme-primary-bg)' }}
                    />
                </div>
            </section>

            {error ? (
                <div className="rounded-2xl border border-red-200 bg-red-50 p-6 text-sm text-red-700">
                    Unable to load staff. {error.response?.data?.message || error.message}
                </div>
            ) : isLoading ? (
                <div className="flex min-h-[200px] items-center justify-center rounded-2xl bg-white shadow-sm">
                    <div
                        className="h-10 w-10 animate-spin rounded-full border-2 border-t-4"
                        style={{ borderColor: 'var(--theme-primary-bg)', borderTopColor: 'var(--theme-primary)' }}
                    />
                </div>
            ) : staff.length === 0 ? (
                <div className="rounded-2xl border border-dashed border-gray-200 bg-white p-12 text-center shadow-sm">
                    <UserSquare2 className="mx-auto h-12 w-12" style={{ color: 'var(--theme-primary-bg)' }} />
                    <h3 className="mt-4 text-lg font-semibold text-gray-900">No staff found</h3>
                    <p className="mt-2 text-sm text-gray-500">Try adjusting your search query.</p>
                </div>
            ) : (
                <section className="grid gap-6 sm:grid-cols-2 xl:grid-cols-3">
                    {staff.map((member) => (
                        <Link
                            key={member.id}
                            to={buildDestination(member.id)}
                            aria-label={`Continue with ${member.name}`}
                            className="block rounded-2xl text-left text-inherit no-underline outline-none focus-visible:ring-2 focus-visible:ring-[var(--theme-primary)] focus-visible:ring-offset-2"
                        >
                            <EntityCardShell className="cursor-pointer">
                                <EntityCardHeader
                                    left={
                                        <span className="inline-flex h-10 w-10 items-center justify-center rounded-full bg-slate-100 text-sm font-semibold text-slate-700">
                                            {(member.name || '?').slice(0, 1).toUpperCase()}
                                        </span>
                                    }
                                    right={
                                        <span
                                            className="inline-flex rounded-lg border border-emerald-200/80 bg-emerald-50/80 p-2 text-[var(--theme-primary)]"
                                            aria-hidden
                                        >
                                            <UserSquare2 className="h-4 w-4" strokeWidth={2.25} />
                                        </span>
                                    }
                                />
                                <h3 className="text-lg font-bold leading-snug text-slate-900 sm:text-xl">
                                    {member.name || 'Unnamed Staff'}
                                </h3>
                                <div className="mt-4">
                                    <DataPill icon={Briefcase}>
                                        <span className="font-normal capitalize text-slate-600">{member.role || 'Staff'}</span>
                                    </DataPill>
                                </div>
                            </EntityCardShell>
                        </Link>
                    ))}
                </section>
            )}
        </div>
    );
}
