import React, { useState, useMemo, useEffect } from 'react';
import { useQuery } from '@tanstack/react-query';
import { useNavigate } from 'react-router-dom';
import { startOfMonth, endOfMonth, startOfWeek, endOfWeek, startOfDay, endOfDay } from 'date-fns';
import { ChevronLeft, ChevronRight, Search, Users, MapPin } from 'lucide-react';
import CalendarView from '../CalendarView';
import api from '../../services/api';
import AppointmentSearchForm, { EMPTY_FILTERS } from './AppointmentSearchForm';

function computeRangeForView(view, date) {
    if (view === 'month') return { start: startOfMonth(date), end: endOfMonth(date) };
    if (view === 'day') return { start: startOfDay(date), end: endOfDay(date) };
    if (view === 'list') {
        // The list is meant to answer "what's coming up / what happened recently"
        // without requiring the user to navigate first, so give it a wide,
        // generous window rather than whatever narrow week/day was last browsed.
        return { start: new Date(date.getFullYear(), date.getMonth() - 3, 1), end: new Date(date.getFullYear(), date.getMonth() + 6, 0) };
    }
    return { start: startOfWeek(date), end: endOfWeek(date) };
}

const STATUS_LEGEND = [
    { value: 'not_scheduled', label: 'Not Scheduled / None', color: '#9ca3af' },
    { value: 'scheduled', label: 'Scheduled', color: '#2563eb' },
    { value: 'rescheduled', label: 'Rescheduled', color: '#a5f3fc', textColor: '#0e7490' },
    { value: 'results_pending', label: 'Results Pending', color: '#f0abfc', textColor: '#86198f' },
    { value: 'completed', label: 'Completed', color: '#16a34a' },
    { value: 'cancelled', label: 'Cancelled', color: '#eab308', textColor: '#713f12' },
    { value: 'missed', label: 'Missed', color: '#ef4444' },
    { value: 'declined', label: 'Declined', color: '#d4a373', textColor: '#5c4324' },
];

const STATUS_COLOR_MAP = STATUS_LEGEND.reduce((acc, s) => {
    acc[s.value] = s;
    return acc;
}, {});

// Legacy status values (pre-dating the extended appointment form) fold into the
// closest new legend bucket so old records still render sensibly.
const LEGACY_STATUS_ALIAS = {
    confirmed: 'scheduled',
    in_progress: 'scheduled',
    no_show: 'missed',
};

function statusMeta(status) {
    const key = LEGACY_STATUS_ALIAS[status] || status;
    return STATUS_COLOR_MAP[key] || STATUS_COLOR_MAP.not_scheduled;
}

function formatTime12h(time) {
    if (!time) return null;
    const [h, m] = time.split(':');
    const hour12 = (parseInt(h, 10) % 12) || 12;
    const ampm = parseInt(h, 10) >= 12 ? 'PM' : 'AM';
    return `${hour12}:${m} ${ampm}`;
}

const VIEW_OPTIONS = [
    { key: 'month', label: 'Month' },
    { key: 'week', label: 'Week' },
    { key: 'day', label: 'Day' },
    { key: 'list', label: 'List' },
];

function ViewSwitcher({ activeView, onChange }) {
    return (
        <div className="inline-flex shrink-0 rounded-lg overflow-hidden border border-gray-700">
            {VIEW_OPTIONS.map((opt) => (
                <button
                    key={opt.key}
                    type="button"
                    onClick={() => onChange(opt.key)}
                    className={`whitespace-nowrap px-3 py-1.5 text-xs sm:px-4 sm:text-sm font-semibold transition-colors ${
                        activeView === opt.key ? 'bg-slate-900 text-white' : 'bg-white text-slate-700 hover:bg-slate-50'
                    }`}
                >
                    {opt.label}
                </button>
            ))}
        </div>
    );
}

function CustomToolbar({ label, onNavigate }) {
    return (
        <div className="flex flex-col gap-3 mb-4 sm:flex-row sm:items-center sm:justify-between">
            <div className="text-base font-bold text-gray-900">{label}</div>
            <div className="flex items-center gap-2">
                <button
                    type="button"
                    onClick={() => onNavigate('TODAY')}
                    className="px-3 py-1.5 rounded-lg bg-slate-200 text-slate-800 text-sm font-semibold hover:bg-slate-300"
                >
                    Today
                </button>
                <button
                    type="button"
                    onClick={() => onNavigate('PREV')}
                    className="p-1.5 rounded-lg bg-slate-800 text-white hover:bg-slate-700"
                    aria-label="Previous"
                >
                    <ChevronLeft className="w-4 h-4" />
                </button>
                <button
                    type="button"
                    onClick={() => onNavigate('NEXT')}
                    className="p-1.5 rounded-lg bg-slate-800 text-white hover:bg-slate-700"
                    aria-label="Next"
                >
                    <ChevronRight className="w-4 h-4" />
                </button>
            </div>
        </div>
    );
}

/**
 * Facility/branch-wide appointment calendar + advanced search. Shows every
 * resident's appointments (not scoped to one person) — the search form is how
 * you narrow down to a specific individual, date range, consultant, etc.
 * Self-contained: fetches its own branches/residents/users for the search
 * form's dropdowns so it can be dropped in without extra prop plumbing.
 */
export default function AppointmentsCalendarBoard({ branchId }) {
    const navigate = useNavigate();
    const { data: branchesData } = useQuery({
        queryKey: ['branches-list'],
        queryFn: async () => (await api.get('/branches', { params: { per_page: 100 } })).data,
    });
    const branches = (branchesData?.data || []).filter((b) => b.is_active !== false);

    const { data: residentsData } = useQuery({
        queryKey: ['residents-list', branchId || 'all'],
        queryFn: async () => (await api.get('/residents', { params: { per_page: 200, ...(branchId ? { branch_id: branchId } : {}) } })).data,
    });
    const residents = residentsData?.data || [];

    const { data: usersData } = useQuery({
        queryKey: ['users-list'],
        queryFn: async () => (await api.get('/users', { params: { per_page: 100 } })).data,
    });
    const users = usersData?.data || [];
    const [anchorDate, setAnchorDate] = useState(() => new Date());
    const [localView, setLocalView] = useState('week');
    const [range, setRange] = useState(() => computeRangeForView('week', new Date()));
    const [showSearch, setShowSearch] = useState(false);
    const [showCalendar, setShowCalendar] = useState(false);
    const [showClientList, setShowClientList] = useState(false);
    const [clientSearch, setClientSearch] = useState('');
    const [filters, setFilters] = useState(EMPTY_FILTERS);
    const [activeSearch, setActiveSearch] = useState(null); // null = browse mode (use `range`); object = search mode

    // Keep the fetch range in sync with whichever date/view is currently browsed,
    // regardless of whether the change came from the calendar's own Prev/Next/Today
    // controls or the Month/Week/Day/List switcher above it.
    useEffect(() => {
        if (activeSearch) return;
        setRange(computeRangeForView(localView, anchorDate));
    }, [localView, anchorDate, activeSearch]);

    const filteredClients = useMemo(() => {
        const q = clientSearch.trim().toLowerCase();
        if (!q) return residents;
        return residents.filter((r) => `${r.first_name || ''} ${r.last_name || ''}`.toLowerCase().includes(q));
    }, [residents, clientSearch]);

    const tz = Intl.DateTimeFormat().resolvedOptions().timeZone;

    const queryParams = useMemo(() => {
        if (activeSearch) {
            const params = { per_page: 200 };
            Object.entries(activeSearch).forEach(([key, value]) => {
                if (value) params[key] = value;
            });
            if (branchId) params.branch_id = params.branch_id || branchId;
            return params;
        }
        return {
            per_page: 200,
            date_from: range.start.toISOString().split('T')[0],
            date_to: range.end.toISOString().split('T')[0],
            ...(branchId ? { branch_id: branchId } : {}),
        };
    }, [activeSearch, range, branchId]);

    const { data, isLoading, error } = useQuery({
        queryKey: ['appointments-board', queryParams],
        queryFn: async () => (await api.get('/appointments', { params: queryParams })).data,
        enabled: showCalendar,
    });

    const appointments = data?.data || [];

    const events = useMemo(() => {
        return appointments.map((apt) => {
            const start = new Date(apt.appointment_date);
            if (apt.appointment_time) {
                const [h, m] = apt.appointment_time.split(':');
                start.setHours(parseInt(h, 10) || 0, parseInt(m, 10) || 0, 0);
            } else {
                start.setHours(9, 0, 0);
            }
            let end = new Date(start);
            if (apt.end_time) {
                const [eh, em] = apt.end_time.split(':');
                end.setHours(parseInt(eh, 10) || 0, parseInt(em, 10) || 0, 0);
            } else {
                end = new Date(start.getTime() + 60 * 60 * 1000);
            }

            const meta = statusMeta(apt.status);
            const residentName = apt.resident
                ? `${apt.resident.first_name || ''} ${apt.resident.last_name || ''}`.trim()
                : 'Unknown';
            const withWho = apt.healthcare_provider?.name
                ? `Consultant: ${apt.healthcare_provider.name}`
                : apt.hospital?.name
                    ? `Hospital: ${apt.hospital.name}`
                    : apt.other_provider
                        ? `Other: ${apt.other_provider}`
                        : null;
            const specialtyLabel = apt.specialty_lookup?.name || apt.specialty || null;

            const titleLines = [`Individual: ${residentName}`];
            if (withWho) titleLines.push(`Appointment with: ${withWho}`);
            if (specialtyLabel) titleLines.push(`Specialty: ${specialtyLabel}`);

            return {
                id: apt.id,
                title: titleLines.join('\n'),
                start,
                end,
                color: meta.color,
                borderColor: meta.color,
                textColor: meta.textColor || '#ffffff',
                resource: apt,
            };
        });
    }, [appointments]);

    const sortedEvents = useMemo(() => {
        return [...events].sort((a, b) => a.start - b.start);
    }, [events]);

    return (
        <div className="space-y-4">
            <div className="bg-white rounded-xl border border-gray-200 shadow-sm p-4">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-end">
                    <div className="flex items-center gap-2 self-start sm:self-auto">
                        <button
                            type="button"
                            onClick={() => setShowCalendar((v) => !v)}
                            className={`px-4 py-1.5 rounded-lg border text-sm font-semibold transition-colors ${
                                showCalendar
                                    ? 'border-[var(--theme-primary)] bg-[var(--theme-primary)] text-[var(--theme-text-on-primary)]'
                                    : 'border-gray-300 text-gray-700 hover:bg-gray-50'
                            }`}
                        >
                            {showCalendar ? 'Hide Calendar' : 'Calendar View'}
                        </button>
                        <button
                            type="button"
                            onClick={() => setShowSearch((v) => !v)}
                            className="px-4 py-1.5 rounded-lg border border-gray-300 text-sm font-semibold text-gray-700 hover:bg-gray-50"
                        >
                            {showSearch ? 'Hide Search' : 'Advanced Search'}
                        </button>
                        <button
                            type="button"
                            onClick={() => setShowClientList((v) => !v)}
                            className="px-4 py-1.5 rounded-lg border border-gray-300 text-sm font-semibold text-gray-700 hover:bg-gray-50"
                        >
                            {showClientList ? 'Hide Clients' : 'Select Client'}
                        </button>
                    </div>
                </div>
            </div>

            {showClientList && (
                <div className="bg-white rounded-xl border border-gray-200 shadow-sm p-4 space-y-3">
                    <div className="relative max-w-sm">
                        <Search className="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" />
                        <input
                            type="search"
                            value={clientSearch}
                            onChange={(e) => setClientSearch(e.target.value)}
                            placeholder="Search clients by name..."
                            autoFocus
                            className="w-full pl-9 pr-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-[var(--theme-primary)] focus:border-transparent"
                        />
                    </div>
                    {filteredClients.length === 0 ? (
                        <p className="text-sm text-gray-500 py-4 text-center">No clients found.</p>
                    ) : (
                        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2 max-h-80 overflow-y-auto">
                            {filteredClients.map((resident) => (
                                <button
                                    key={resident.id}
                                    type="button"
                                    onClick={() => navigate(`/appointments/add/${resident.id}`)}
                                    className="flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-2 text-left text-sm hover:border-[var(--theme-primary)] hover:bg-[var(--theme-primary-bg)] transition-colors"
                                >
                                    <Users className="w-4 h-4 text-gray-400 shrink-0" />
                                    <span className="flex-1 min-w-0 truncate font-medium text-gray-900">
                                        {resident.first_name} {resident.last_name}
                                    </span>
                                    {resident.room_number && (
                                        <span className="flex items-center gap-1 text-xs text-gray-400 shrink-0">
                                            <MapPin className="w-3 h-3" /> {resident.room_number}
                                        </span>
                                    )}
                                </button>
                            ))}
                        </div>
                    )}
                </div>
            )}

            {showSearch && (
                <AppointmentSearchForm
                    filters={filters}
                    onChange={setFilters}
                    branches={branches}
                    residents={residents}
                    users={users}
                    onSearch={() => {
                        setActiveSearch(filters);
                        setShowCalendar(true);
                    }}
                    onClear={() => {
                        setFilters(EMPTY_FILTERS);
                        setActiveSearch(null);
                    }}
                />
            )}

            {!showCalendar ? null : error ? (
                <div className="bg-white rounded-xl border border-red-200 p-8 text-center text-red-600">
                    Unable to load appointments.
                </div>
            ) : isLoading ? (
                <div className="bg-white rounded-xl border border-gray-200 p-12 text-center text-gray-500">
                    Loading calendar...
                </div>
            ) : (
                <div className="bg-white rounded-xl border border-gray-200 shadow-sm p-4">
                    <div className="flex flex-wrap items-center gap-3 mb-4">
                        <ViewSwitcher activeView={localView} onChange={setLocalView} />
                        <p className="text-sm text-gray-600">
                            <span className="font-bold text-gray-900">Time Zone:</span> {tz}
                        </p>
                        <div className="flex flex-wrap items-center gap-3">
                            {STATUS_LEGEND.map((s) => (
                                <span key={s.value} className="inline-flex items-center gap-1.5 text-xs text-gray-600">
                                    <span className="inline-block w-3.5 h-3.5 rounded-sm" style={{ backgroundColor: s.color }} />
                                    {s.label}
                                </span>
                            ))}
                        </div>
                    </div>

                    {localView === 'list' ? (
                        sortedEvents.length === 0 ? (
                            <div className="py-12 text-center text-gray-500 text-sm">
                                No appointments{activeSearch ? ' match your search.' : ' in this range.'}
                            </div>
                        ) : (
                            <ul className="divide-y divide-gray-100">
                                {sortedEvents.map((event) => {
                                    const apt = event.resource;
                                    return (
                                        <li key={event.id}>
                                            <button
                                                type="button"
                                                onClick={() => navigate(`/appointments/${event.id}`)}
                                                className="w-full flex items-center gap-3 px-2 py-3 text-left hover:bg-gray-50 transition-colors"
                                            >
                                                <span
                                                    className="w-2.5 h-2.5 rounded-full shrink-0"
                                                    style={{ backgroundColor: event.color }}
                                                    aria-hidden="true"
                                                />
                                                <span className="w-28 shrink-0 text-xs font-semibold text-gray-500">
                                                    {event.start.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}
                                                    {apt?.appointment_time && <><br />{formatTime12h(apt.appointment_time)}</>}
                                                </span>
                                                <span className="flex-1 min-w-0 whitespace-pre-line text-sm text-gray-800">
                                                    {event.title}
                                                </span>
                                                <span
                                                    className="shrink-0 text-[10px] font-bold uppercase tracking-wide px-2 py-0.5 rounded-full"
                                                    style={{ backgroundColor: event.color, color: event.textColor }}
                                                >
                                                    {apt?.status || 'not_scheduled'}
                                                </span>
                                            </button>
                                        </li>
                                    );
                                })}
                            </ul>
                        )
                    ) : (
                        <CalendarView
                            events={events}
                            date={anchorDate}
                            onNavigate={setAnchorDate}
                            view={localView}
                            onView={setLocalView}
                            views={['month', 'week', 'day']}
                            height="650px"
                            components={{ toolbar: CustomToolbar }}
                        />
                    )}
                </div>
            )}
        </div>
    );
}
