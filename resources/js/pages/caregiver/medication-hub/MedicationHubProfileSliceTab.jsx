import React from 'react';
import { useQuery } from '@tanstack/react-query';
import { Link, useParams } from 'react-router-dom';
import {
    AlertCircle,
    Building2,
    ClipboardList,
    ExternalLink,
    Heart,
    MapPin,
    Pill,
    Phone,
    Shield,
    Stethoscope,
    User,
    UserCircle,
    FileText,
} from 'lucide-react';
import api from '../../../services/api';
import { formatPacificCalendarMedium, calculateAgeFromPacificBirthDate } from '../../../utils/pacificTime';

function formatPhone(value) {
    if (!value) return null;
    const cleaned = String(value).replace(/[^\d+]/g, '');
    if (cleaned.length === 10) return `(${cleaned.slice(0, 3)}) ${cleaned.slice(3, 6)}-${cleaned.slice(6)}`;
    return String(value);
}

function formatLooseList(value) {
    if (value == null || value === '') return null;
    if (Array.isArray(value)) {
        const parts = value.filter((x) => x != null && String(x).trim() !== '');
        return parts.length ? parts.map((x) => String(x).trim()).join(', ') : null;
    }
    if (typeof value === 'object') return JSON.stringify(value);
    const s = String(value).trim();
    return s || null;
}

function formatGender(g) {
    if (!g) return null;
    const s = String(g).toLowerCase();
    if (s === 'male' || s === 'm') return 'Male';
    if (s === 'female' || s === 'f') return 'Female';
    return s.charAt(0).toUpperCase() + s.slice(1);
}

function SectionCard({ icon: Icon, title, subtitle, children, className = '' }) {
    return (
        <section
            className={`rounded-xl border border-gray-100 bg-white shadow-sm overflow-hidden ${className}`}
        >
            <div className="px-4 py-3 border-b border-gray-100 bg-gradient-to-r from-[var(--theme-primary)]/5 to-transparent">
                <div className="flex items-start gap-2">
                    {Icon ? (
                        <div className="mt-0.5 w-8 h-8 rounded-lg bg-[var(--theme-primary)]/10 flex items-center justify-center shrink-0">
                            <Icon className="w-4 h-4 text-[var(--theme-primary)]" aria-hidden="true" />
                        </div>
                    ) : null}
                    <div className="min-w-0">
                        <h3 className="text-sm font-bold text-gray-900">{title}</h3>
                        {subtitle ? <p className="text-xs text-gray-500 mt-0.5">{subtitle}</p> : null}
                    </div>
                </div>
            </div>
            <div className="p-4">{children}</div>
        </section>
    );
}

function Field({ label, value, span = false }) {
    const display = value != null && String(value).trim() !== '' ? value : null;
    return (
        <div className={span ? 'sm:col-span-2' : ''}>
            <dt className="text-[11px] font-bold uppercase tracking-wide text-gray-400">{label}</dt>
            <dd className="mt-1 text-sm text-gray-900 leading-snug break-words">{display ?? '—'}</dd>
        </div>
    );
}

function ProseBlock({ label, value }) {
    const display = formatLooseList(value);
    return (
        <div>
            <dt className="text-[11px] font-bold uppercase tracking-wide text-gray-400">{label}</dt>
            <dd className="mt-1 text-sm text-gray-800 whitespace-pre-wrap leading-relaxed">
                {display ?? '—'}
            </dd>
        </div>
    );
}

export default function MedicationHubProfileSliceTab() {
    const { residentId } = useParams();
    const base = `/my-residents/${residentId}/medications`;

    const { data: resident, isLoading, error } = useQuery({
        queryKey: ['med-hub-layout-resident', residentId],
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
    const room = resident.room_number || resident.room;
    const branchName = resident.branch?.name;
    let allergiesDisplay = formatLooseList(resident.allergies);
    if (
        !allergiesDisplay &&
        Array.isArray(resident.allergies) &&
        resident.allergies.length === 0
    ) {
        allergiesDisplay = 'None recorded';
    }
    const medicalConditions = formatLooseList(resident.medical_conditions);
    const diagnosis = formatLooseList(resident.diagnosis);
    const phone = formatPhone(resident.phone);
    const meds = Array.isArray(resident.medications) ? resident.medications : [];
    const activeMeds = meds.filter((m) => m.is_active !== false);

    const statusLabel = resident.status
        ? String(resident.status).replace(/_/g, ' ')
        : null;
    const isActive = resident.is_active !== false;

    return (
        <div className="space-y-5">
            {/* Hero */}
            <div className="rounded-xl border border-gray-100 bg-white shadow-sm overflow-hidden">
                <div className="h-1 bg-gradient-to-r from-[var(--theme-primary)] to-[var(--theme-primary-dark)]" aria-hidden="true" />
                <div className="p-4 sm:p-5 flex flex-col sm:flex-row gap-4 sm:items-center">
                    <div className="relative w-16 h-16 sm:w-20 sm:h-20 rounded-2xl overflow-hidden border-2 border-[var(--theme-primary)]/20 bg-[var(--theme-primary)]/10 shrink-0">
                        {resident.profile_image_url || resident.profile_image ? (
                            <img
                                src={resident.profile_image_url || `/storage/${resident.profile_image}`}
                                alt=""
                                className="w-full h-full object-cover"
                            />
                        ) : (
                            <div className="absolute inset-0 flex items-center justify-center text-[var(--theme-primary)]">
                                <UserCircle className="w-10 h-10 sm:w-12 sm:h-12" />
                            </div>
                        )}
                    </div>
                    <div className="min-w-0 flex-1">
                        <p className="text-[10px] font-bold uppercase tracking-widest text-[var(--theme-primary)]">
                            Resident profile
                        </p>
                        <h2 className="text-lg sm:text-xl font-bold text-gray-900 tracking-tight truncate">
                            {fullName || 'Resident'}
                        </h2>
                        <div className="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-gray-600">
                            {resident.date_of_birth ? (
                                <span>
                                    DOB {formatPacificCalendarMedium(resident.date_of_birth)}
                                    {age !== null ? ` · ${age} y.o.` : ''}
                                </span>
                            ) : null}
                            {formatGender(resident.gender) ? (
                                <span className="text-gray-500">· {formatGender(resident.gender)}</span>
                            ) : null}
                            {phone ? (
                                <span className="inline-flex items-center gap-1 text-gray-600">
                                    <Phone className="w-3.5 h-3.5 shrink-0 opacity-60" aria-hidden="true" />
                                    {phone}
                                </span>
                            ) : null}
                        </div>
                        <div className="mt-2 flex flex-wrap items-center gap-2">
                            {statusLabel ? (
                                <span className="inline-flex items-center rounded-full border border-gray-200 bg-gray-50 px-2.5 py-0.5 text-xs font-semibold text-gray-700 capitalize">
                                    {statusLabel}
                                </span>
                            ) : null}
                            <span
                                className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-bold ${
                                    isActive
                                        ? 'bg-emerald-50 text-emerald-800 border border-emerald-200'
                                        : 'bg-gray-100 text-gray-600 border border-gray-200'
                                }`}
                            >
                                {isActive ? 'Active' : 'Inactive'}
                            </span>
                            {resident.code_status ? (
                                <span className="inline-flex items-center rounded-full border border-blue-100 bg-blue-50 px-2.5 py-0.5 text-xs font-bold text-blue-800">
                                    Code: {resident.code_status}
                                </span>
                            ) : null}
                        </div>
                    </div>
                    <div className="flex flex-col sm:items-end gap-2 shrink-0">
                        <Link
                            to={`/my-residents/${residentId}`}
                            className="inline-flex items-center justify-center gap-1.5 rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs font-bold text-gray-800 hover:bg-gray-50 transition-colors"
                        >
                            Full resident record
                            <ExternalLink className="w-3.5 h-3.5" aria-hidden="true" />
                        </Link>
                        <Link
                            to={`${base}/list`}
                            className="inline-flex items-center justify-center gap-1.5 rounded-lg bg-[var(--theme-primary)] px-3 py-2 text-xs font-bold text-[var(--theme-text-on-primary)] hover:opacity-95 transition-opacity"
                        >
                            <Pill className="w-3.5 h-3.5" aria-hidden="true" />
                            Medication list
                        </Link>
                    </div>
                </div>
                <p className="px-4 sm:px-5 pb-4 text-xs text-gray-500 border-t border-gray-50 pt-3">
                    Read-only reference for the care team while in the Medication Hub. Update details from the full
                    resident record.
                </p>
            </div>

            <div className="grid grid-cols-1 lg:grid-cols-2 gap-5">
                <SectionCard icon={MapPin} title="Location & stay" subtitle="Branch, room, and dates on file">
                    <dl className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <Field label="Branch" value={branchName} />
                        <Field label="Room" value={room} />
                        <Field
                            label="Admission"
                            value={
                                resident.admission_date
                                    ? formatPacificCalendarMedium(resident.admission_date)
                                    : null
                            }
                        />
                        <Field
                            label="Discharge"
                            value={
                                resident.discharge_date
                                    ? formatPacificCalendarMedium(resident.discharge_date)
                                    : null
                            }
                        />
                    </dl>
                </SectionCard>

                <SectionCard
                    icon={Heart}
                    title="Allergies & conditions"
                    subtitle="Critical context for medication administration"
                >
                    <dl className="space-y-4">
                        <ProseBlock label="Allergies" value={allergiesDisplay} />
                        <ProseBlock label="Diagnosis" value={diagnosis} />
                        <ProseBlock label="Medical conditions" value={medicalConditions} />
                    </dl>
                </SectionCard>

                <SectionCard icon={Stethoscope} title="Care team" subtitle="Physicians and identifiers">
                    <dl className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <Field label="Physician" value={resident.physician_name} />
                        <Field label="Primary care" value={resident.primary_care_doctor} />
                        <Field label="Medicare #" value={resident.medicare_number} span />
                    </dl>
                </SectionCard>

                <SectionCard icon={Phone} title="Emergency contact" subtitle="Reach person on file">
                    <dl className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <Field label="Name" value={resident.emergency_contact_name} />
                        <Field
                            label="Phone"
                            value={resident.emergency_contact_phone ? formatPhone(resident.emergency_contact_phone) : null}
                        />
                    </dl>
                </SectionCard>
            </div>

            <SectionCard
                icon={Pill}
                title="Medications on file"
                subtitle={`${activeMeds.length} active order${activeMeds.length === 1 ? '' : 's'} (from resident record)`}
            >
                {activeMeds.length === 0 ? (
                    <p className="text-sm text-gray-500">
                        No active medication orders in this snapshot. Open the{' '}
                        <Link to={`${base}/list`} className="font-bold text-[var(--theme-primary)] hover:underline">
                            Medications
                        </Link>{' '}
                        tab to review or add.
                    </p>
                ) : (
                    <>
                        <ul className="divide-y divide-gray-100 rounded-lg border border-gray-100 overflow-hidden">
                            {activeMeds.slice(0, 12).map((m) => (
                                <li
                                    key={m.id}
                                    className="px-3 py-2.5 flex flex-col sm:flex-row sm:items-start sm:justify-between gap-1 bg-white hover:bg-gray-50/80"
                                >
                                    <div className="min-w-0">
                                        <p className="text-sm font-semibold text-gray-900">
                                            {m.name || m.drug?.name || 'Medication'}
                                        </p>
                                        {m.drug?.name && m.name && m.name !== m.drug.name ? (
                                            <p className="text-xs text-gray-500">{m.drug.name}</p>
                                        ) : null}
                                    </div>
                                    <p className="text-xs sm:text-sm text-gray-600 sm:text-right sm:max-w-[55%]">
                                        {m.instructions || '—'}
                                    </p>
                                </li>
                            ))}
                        </ul>
                        {activeMeds.length > 12 ? (
                            <p className="text-xs text-gray-500 mt-2">
                                Showing 12 of {activeMeds.length}. See the full list in{' '}
                                <Link to={`${base}/list`} className="font-bold text-[var(--theme-primary)] hover:underline">
                                    Medications
                                </Link>
                                .
                            </p>
                        ) : null}
                    </>
                )}
            </SectionCard>

            <div className="grid grid-cols-1 lg:grid-cols-2 gap-5">
                <SectionCard icon={FileText} title="Care plan & instructions" subtitle="Orders and special directions">
                    <dl className="space-y-4">
                        <ProseBlock label="Care plan" value={resident.care_plan} />
                        <ProseBlock label="Special instructions" value={resident.special_instructions} />
                    </dl>
                </SectionCard>

                <SectionCard icon={ClipboardList} title="General notes" subtitle="Resident record notes">
                    <ProseBlock label="Notes" value={resident.notes} />
                </SectionCard>
            </div>

            <div className="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-dashed border-gray-200 bg-gray-50/50 px-4 py-3 text-xs text-gray-500">
                <span className="inline-flex items-center gap-1.5">
                    <Shield className="w-3.5 h-3.5 text-gray-400" aria-hidden="true" />
                    Profile data is sourced from the resident record API (same as the hub header).
                </span>
                <Link
                    to={`/my-residents/${residentId}`}
                    className="font-bold text-[var(--theme-primary)] hover:underline inline-flex items-center gap-1"
                >
                    <User className="w-3.5 h-3.5" aria-hidden="true" />
                    Edit in resident hub
                </Link>
            </div>
        </div>
    );
}
