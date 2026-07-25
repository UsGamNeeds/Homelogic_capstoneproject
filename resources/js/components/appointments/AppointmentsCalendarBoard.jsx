import React, { useState, useMemo } from 'react';
import { useQuery } from '@tanstack/react-query';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import CalendarView from '../CalendarView';
import api from '../../services/api';
import AppointmentSearchForm, { EMPTY_FILTERS } from './AppointmentSearchForm';

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

function CustomToolbar({ label, onNavigate, onView, view }) {
    const VIEW_OPTIONS = [
        { key: 'month', label: 'Month' },
        { key: 'week', label: 'Week' },
        { key: 'day', label: 'Day' },
        { key: 'agenda', label: 'List' },
    ];

    return (
        <div className="flex flex-col gap-3 mb-4 sm:flex-row sm:items-center sm:justify-between">
            <div className="inline-flex rounded-lg overflow-hidden border border-gray-700">
                {VIEW_OPTIONS.map((opt) => (
                    <button
                        key={opt.key}
                        type="button"
                        onClick={() => onView(opt.key)}
                        className={`px-4 py-1.5 text-sm font-semibold transition-colors ${
                            view === opt.key ? 'bg-slate-900 text-white' : 'bg-white text-slate-700 hover:bg-slate-50'
                        }`}
                    >
                        {opt.label}
                    </button>
                ))}
            </div>
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
    const [range, setRange] = useState(() => {
        const now = new Date();
        return { start: new Date(now.getFullYear(), now.getMonth(), 1), end: new Date(now.getFullYear(), now.getMonth() + 1, 0) };
    });
    const [showSearch, setShowSearch] = useState(false);
    const [filters, setFilters] = useState(EMPTY_FILTERS);
    const [activeSearch, setActiveSearch] = useState(null); // null = browse mode (use `range`); object = search mode

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

    return (
        <div className="space-y-4">
            <div className="bg-white rounded-xl border border-gray-200 shadow-sm p-4">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
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
                    <button
                        type="button"
                        onClick={() => setShowSearch((v) => !v)}
                        className="self-start sm:self-auto px-4 py-1.5 rounded-lg border border-gray-300 text-sm font-semibold text-gray-700 hover:bg-gray-50"
                    >
                        {showSearch ? 'Hide Search' : 'Advanced Search'}
                    </button>
                </div>
            </div>

            {showSearch && (
                <AppointmentSearchForm
                    filters={filters}
                    onChange={setFilters}
                    branches={branches}
                    residents={residents}
                    users={users}
                    onSearch={() => setActiveSearch(filters)}
                    onClear={() => {
                        setFilters(EMPTY_FILTERS);
                        setActiveSearch(null);
                    }}
                />
            )}

            {error ? (
                <div className="bg-white rounded-xl border border-red-200 p-8 text-center text-red-600">
                    Unable to load appointments.
                </div>
            ) : isLoading ? (
                <div className="bg-white rounded-xl border border-gray-200 p-12 text-center text-gray-500">
                    Loading calendar...
                </div>
            ) : (
                <CalendarView
                    events={events}
                    views={['month', 'week', 'day', 'agenda']}
                    defaultView="week"
                    height="650px"
                    components={{ toolbar: CustomToolbar }}
                    onRangeChange={(newRange) => {
                        if (activeSearch) return; // search results own the view until cleared
                        if (Array.isArray(newRange)) {
                            setRange({ start: newRange[0], end: newRange[newRange.length - 1] });
                        } else if (newRange?.start && newRange?.end) {
                            setRange({ start: newRange.start, end: newRange.end });
                        }
                    }}
                />
            )}
        </div>
    );
}
