import React from 'react';
import { Search, X } from 'lucide-react';

const REASON_OPTIONS = [
    { value: 'routine_checkup', label: 'Routine Checkup' },
    { value: 'follow_up', label: 'Follow-up' },
    { value: 'specialist_consult', label: 'Specialist Consult' },
    { value: 'emergency', label: 'Emergency' },
    { value: 'other', label: 'Other' },
];

const STATUS_OPTIONS = [
    { value: 'not_scheduled', label: 'Not Scheduled' },
    { value: 'scheduled', label: 'Scheduled' },
    { value: 'rescheduled', label: 'Rescheduled' },
    { value: 'results_pending', label: 'Results Pending' },
    { value: 'completed', label: 'Completed' },
    { value: 'cancelled', label: 'Cancelled' },
    { value: 'missed', label: 'Missed' },
    { value: 'declined', label: 'Declined' },
];

const NOTIFICATION_LEVEL_OPTIONS = [
    { value: 'low', label: 'Low' },
    { value: 'medium', label: 'Medium' },
    { value: 'high', label: 'High' },
    { value: 'urgent', label: 'Urgent' },
];

const EMPTY_FILTERS = {
    id: '',
    branch_id: '',
    resident_id: '',
    entered_by: '',
    date_from: '',
    date_to: '',
    specialty: '',
    reason_for_appointment: '',
    status: '',
    notification_level: '',
    consultant_name: '',
    hospital_name: '',
};

export { EMPTY_FILTERS };

/**
 * Advanced appointment search — filters the calendar/list below it. At least one
 * of Form ID, Individual, or a Date Range must be provided before it will run
 * (matching the "at least one of these" requirement from the reference form),
 * everything else narrows further.
 */
export default function AppointmentSearchForm({ filters, onChange, onSearch, onClear, branches = [], residents = [], users = [] }) {
    const set = (field, value) => onChange({ ...filters, [field]: value });

    const canSearch = !!(filters.id || filters.resident_id || filters.date_from || filters.date_to);

    const handleSubmit = (e) => {
        e.preventDefault();
        if (canSearch) onSearch();
    };

    const inputClass = 'w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-[var(--theme-primary)] focus:border-transparent';

    return (
        <form onSubmit={handleSubmit} className="bg-white rounded-xl border border-gray-200 shadow-sm p-6 space-y-5">
            {!canSearch && (
                <div className="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2">
                    <p className="text-xs font-medium text-amber-800">Form ID or Individual or Date Range Required</p>
                </div>
            )}

            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label className="block text-sm font-bold text-gray-900 mb-1">Form ID</label>
                    <input type="text" value={filters.id} onChange={(e) => set('id', e.target.value)} className={inputClass} placeholder="Appointment #" />
                </div>
                <div>
                    <label className="block text-sm font-bold text-gray-900 mb-1">Program (Site)</label>
                    <select value={filters.branch_id} onChange={(e) => set('branch_id', e.target.value)} className={inputClass}>
                        <option value="">All Programs</option>
                        {branches.map((b) => (
                            <option key={b.id} value={b.id}>{b.name}</option>
                        ))}
                    </select>
                </div>
                <div>
                    <label className="block text-sm font-bold text-gray-900 mb-1">Individual</label>
                    <select value={filters.resident_id} onChange={(e) => set('resident_id', e.target.value)} className={inputClass}>
                        <option value="">All Residents</option>
                        {residents.map((r) => (
                            <option key={r.id} value={r.id}>{r.first_name} {r.last_name}</option>
                        ))}
                    </select>
                </div>
                <div>
                    <label className="block text-sm font-bold text-gray-900 mb-1">Entered By</label>
                    <select value={filters.entered_by} onChange={(e) => set('entered_by', e.target.value)} className={inputClass}>
                        <option value="">Anyone</option>
                        {users.map((u) => (
                            <option key={u.id} value={u.id}>{u.name}</option>
                        ))}
                    </select>
                </div>
                <div>
                    <label className="block text-sm font-bold text-gray-900 mb-1">Appointment Date From</label>
                    <input type="date" value={filters.date_from} onChange={(e) => set('date_from', e.target.value)} className={inputClass} />
                </div>
                <div>
                    <label className="block text-sm font-bold text-gray-900 mb-1">To</label>
                    <input type="date" value={filters.date_to} onChange={(e) => set('date_to', e.target.value)} className={inputClass} />
                </div>
                <div>
                    <label className="block text-sm font-bold text-gray-900 mb-1">Specialty</label>
                    <input type="text" value={filters.specialty} onChange={(e) => set('specialty', e.target.value)} className={inputClass} />
                </div>
                <div>
                    <label className="block text-sm font-bold text-gray-900 mb-1">Reason for Appointment</label>
                    <select value={filters.reason_for_appointment} onChange={(e) => set('reason_for_appointment', e.target.value)} className={inputClass}>
                        <option value="">- Please Select -</option>
                        {REASON_OPTIONS.map((opt) => (
                            <option key={opt.value} value={opt.value}>{opt.label}</option>
                        ))}
                    </select>
                </div>
                <div>
                    <label className="block text-sm font-bold text-gray-900 mb-1">Appointment Status</label>
                    <select value={filters.status} onChange={(e) => set('status', e.target.value)} className={inputClass}>
                        <option value="">- Please Select -</option>
                        {STATUS_OPTIONS.map((opt) => (
                            <option key={opt.value} value={opt.value}>{opt.label}</option>
                        ))}
                    </select>
                </div>
                <div>
                    <label className="block text-sm font-bold text-gray-900 mb-1">Notification Level</label>
                    <select value={filters.notification_level} onChange={(e) => set('notification_level', e.target.value)} className={inputClass}>
                        <option value="">- Please Select -</option>
                        {NOTIFICATION_LEVEL_OPTIONS.map((opt) => (
                            <option key={opt.value} value={opt.value}>{opt.label}</option>
                        ))}
                    </select>
                </div>
                <div>
                    <label className="block text-sm font-bold text-gray-900 mb-1">Consultant Name</label>
                    <input type="text" value={filters.consultant_name} onChange={(e) => set('consultant_name', e.target.value)} className={inputClass} />
                </div>
                <div>
                    <label className="block text-sm font-bold text-gray-900 mb-1">Hospital Name</label>
                    <input type="text" value={filters.hospital_name} onChange={(e) => set('hospital_name', e.target.value)} className={inputClass} />
                </div>
            </div>

            <div className="flex items-center justify-end gap-3 pt-2 border-t">
                <button
                    type="button"
                    onClick={onClear}
                    className="inline-flex items-center gap-1.5 px-4 py-2 border border-gray-300 rounded-lg text-sm font-semibold text-gray-700 hover:bg-gray-50"
                >
                    <X className="w-4 h-4" />
                    Clear
                </button>
                <button
                    type="submit"
                    disabled={!canSearch}
                    className="inline-flex items-center gap-1.5 px-4 py-2 bg-[var(--theme-primary)] text-[var(--theme-text-on-primary)] rounded-lg text-sm font-semibold hover:bg-[var(--theme-primary-hover)] disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    <Search className="w-4 h-4" />
                    Search
                </button>
            </div>
        </form>
    );
}
