import React, { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { useNavigate, useSearchParams } from 'react-router-dom';
import { Plus, Edit, Trash2, X, Droplet, Calendar, User } from 'lucide-react';
import api from '../services/api';
import Card from '../components/Card';
import ConfirmDialog from '../components/ui/ConfirmDialog';
import Modal from '../components/ui/Modal';
import Tooltip from '../components/ui/Tooltip';
import { toast } from 'sonner';
import logger from '../utils/logger';
import { readResidentIdParam } from '../utils/headerResidentSwitcher';

const NOTIFICATION_LEVEL_COLORS = {
    urgent: 'bg-red-100 text-red-800 border-red-300',
    high: 'bg-orange-100 text-orange-800 border-orange-300',
    medium: 'bg-yellow-100 text-yellow-800 border-yellow-300',
    low: 'bg-green-100 text-green-800 border-green-300',
};

const METHOD_LABELS = {
    fingerstick: 'Fingerstick',
    cgm: 'Continuous Monitor (CGM)',
    lab_draw: 'Lab Draw',
};

const TREATMENT_LABELS = {
    none: 'None',
    insulin: 'Insulin',
    oral_medication: 'Oral Medication',
    dietary: 'Dietary',
    other: 'Other',
};

export default function BloodGlucoseReports() {
    const queryClient = useQueryClient();
    const navigate = useNavigate();
    const [searchParams] = useSearchParams();
    const [showForm, setShowForm] = useState(false);
    const [editing, setEditing] = useState(null);
    const [deleteConfirmId, setDeleteConfirmId] = useState(null);
    const [residentFilter, setResidentFilter] = useState('');

    React.useEffect(() => {
        setResidentFilter(readResidentIdParam(searchParams));
    }, [searchParams]);

    const { data, isLoading, error } = useQuery({
        queryKey: ['blood-glucose-reports'],
        queryFn: async () => (await api.get('/blood-glucose-reports', { params: { per_page: 50 } })).data,
        retry: 1,
    });

    const { data: currentUser } = useQuery({
        queryKey: ['current-user'],
        queryFn: async () => {
            try {
                return (await api.get('/user')).data;
            } catch {
                return null;
            }
        },
    });

    // Only needed for the edit modal's resident dropdown — new reports go through
    // the two-step resident-picker flow instead.
    const { data: residentsData } = useQuery({
        queryKey: ['residents-list'],
        queryFn: async () => (await api.get('/residents', { params: { per_page: 100 } })).data,
        enabled: showForm && !!editing,
    });

    const isSuperAdmin = currentUser?.role === 'super_admin';
    const isAdmin = currentUser?.role === 'administrator' || currentUser?.role === 'admin';
    const permissions = Array.isArray(currentUser?.permissions) ? currentUser.permissions : [];
    const canCreate = isSuperAdmin || isAdmin || permissions.includes('create_blood_glucose_reports');
    const canEdit = isSuperAdmin || isAdmin || permissions.includes('edit_blood_glucose_reports');
    const canDelete = isSuperAdmin || isAdmin || permissions.includes('delete_blood_glucose_reports');

    const deleteMutation = useMutation({
        mutationFn: async (id) => api.delete(`/blood-glucose-reports/${id}`),
        onSuccess: () => {
            queryClient.invalidateQueries(['blood-glucose-reports']);
            toast.success('Blood glucose report deleted successfully');
        },
        onError: (error) => {
            logger.error('Error deleting blood glucose report:', error);
            toast.error(error.response?.data?.message || 'Failed to delete blood glucose report');
        },
    });

    const handleConfirmDelete = () => {
        if (deleteConfirmId == null) return;
        deleteMutation.mutate(deleteConfirmId, { onSuccess: () => setDeleteConfirmId(null) });
    };

    const reports = data?.data || [];

    // Carry the resident already in scope into the create flow, so the user is not asked to pick again.
    const addBloodGlucosePath = residentFilter ? `/blood-glucose-reports/add/${residentFilter}` : '/blood-glucose-reports/add';

    return (
        <>
            <ConfirmDialog
                isOpen={deleteConfirmId != null}
                onClose={() => !deleteMutation.isPending && setDeleteConfirmId(null)}
                onConfirm={handleConfirmDelete}
                title="Delete this blood glucose report?"
                description="This action cannot be undone."
                confirmLabel="Delete"
                cancelLabel="Cancel"
                variant="danger"
                isPending={deleteMutation.isPending}
            />
            <Modal
                isOpen={showForm}
                onClose={() => {
                    setShowForm(false);
                    setEditing(null);
                }}
                title={editing ? 'Edit Blood Glucose Report' : 'New Blood Glucose Report'}
                size="xl"
            >
                <BloodGlucoseReportForm
                    key={editing?.id ?? 'new'}
                    record={editing}
                    residents={residentsData?.data || []}
                    inModal
                    onClose={() => {
                        setShowForm(false);
                        setEditing(null);
                    }}
                    onSuccess={() => {
                        setShowForm(false);
                        setEditing(null);
                        queryClient.invalidateQueries(['blood-glucose-reports']);
                    }}
                />
            </Modal>

            <div className="space-y-6">
                <div className="flex justify-between items-center">
                    <h1 className="text-2xl font-bold text-gray-900">Blood Glucose Reports</h1>
                    {canCreate && (
                        <button
                            onClick={() => navigate(addBloodGlucosePath)}
                            className="flex items-center gap-2 px-4 py-2 bg-[var(--theme-primary)] text-[var(--theme-text-on-primary)] rounded-lg hover:bg-[var(--theme-primary-hover)] transition-colors"
                        >
                            <Plus className="w-5 h-5" />
                            New Report
                        </button>
                    )}
                </div>

                {error ? (
                    <Card>
                        <div className="text-center py-8 text-red-600">Error loading blood glucose reports</div>
                    </Card>
                ) : isLoading ? (
                    <Card>
                        <div className="text-center py-8">Loading...</div>
                    </Card>
                ) : reports.length === 0 ? (
                    <Card>
                        <div className="text-center py-12">
                            <Droplet className="w-12 h-12 text-gray-300 mx-auto mb-3" />
                            <p className="text-gray-500 text-sm">No blood glucose reports found</p>
                            {canCreate && (
                                <button
                                    onClick={() => navigate(addBloodGlucosePath)}
                                    className="mt-4 inline-flex items-center gap-2 px-4 py-2 bg-[var(--theme-primary)] text-[var(--theme-text-on-primary)] rounded-lg hover:bg-[var(--theme-primary-hover)] transition-colors"
                                >
                                    <Plus className="w-4 h-4" />
                                    New Report
                                </button>
                            )}
                        </div>
                    </Card>
                ) : (
                    <div className="space-y-4">
                        {reports.map((report) => (
                            <Card key={report.id} className="hover:shadow-md transition-shadow">
                                <div className="flex justify-between items-start">
                                    <div className="flex-1">
                                        <div className="flex items-center gap-3 mb-2 flex-wrap">
                                            <h3 className="text-lg font-semibold text-gray-900">
                                                {report.value} mg/dL
                                            </h3>
                                            {report.method_used && (
                                                <span className="px-2 py-1 text-xs font-medium rounded bg-blue-100 text-blue-800">
                                                    {METHOD_LABELS[report.method_used] || report.method_used}
                                                </span>
                                            )}
                                            <span
                                                className={`px-2 py-1 text-xs font-medium rounded border ${NOTIFICATION_LEVEL_COLORS[report.notification_level] || 'bg-gray-100 text-gray-800'}`}
                                            >
                                                {report.notification_level || 'low'}
                                            </span>
                                            {report.fasting ? (
                                                <span className="px-2 py-1 text-xs font-medium rounded bg-purple-100 text-purple-800">
                                                    Fasting
                                                </span>
                                            ) : null}
                                        </div>

                                        <div className="flex items-center gap-4 text-sm text-gray-500">
                                            {report.resident && (
                                                <div className="flex items-center gap-1">
                                                    <User className="w-4 h-4" />
                                                    <span>{report.resident.name || `${report.resident.first_name} ${report.resident.last_name}`}</span>
                                                </div>
                                            )}
                                            {report.reading_date && (
                                                <div className="flex items-center gap-1">
                                                    <Calendar className="w-4 h-4" />
                                                    <span>
                                                        {new Date(report.reading_date).toLocaleDateString()}
                                                        {report.reading_time ? ` at ${report.reading_time}` : ''}
                                                    </span>
                                                </div>
                                            )}
                                        </div>

                                        {report.treatment_type && report.treatment_type !== 'none' && (
                                            <p className="text-sm text-gray-600 mt-2">
                                                Treatment: {TREATMENT_LABELS[report.treatment_type] || report.treatment_type}
                                                {report.medication_name ? ` — ${report.medication_name}` : ''}
                                            </p>
                                        )}

                                        {report.comments && (
                                            <p className="text-gray-600 mt-2 line-clamp-2">{report.comments}</p>
                                        )}
                                    </div>

                                    <div className="ml-4 flex gap-2">
                                        {canEdit && (
                                            <Tooltip content="Edit" position="top">
                                                <button
                                                    type="button"
                                                    onClick={() => {
                                                        setEditing(report);
                                                        setShowForm(true);
                                                    }}
                                                    className="rounded-lg border-2 border-gray-300 bg-white p-2.5 text-[var(--theme-primary)] shadow-sm transition-all hover:border-[var(--theme-primary-dark)] hover:bg-[var(--theme-primary-bg)]"
                                                    aria-label="Edit blood glucose report"
                                                >
                                                    <Edit className="h-5 w-5 !text-emerald-700" strokeWidth={2.5} />
                                                </button>
                                            </Tooltip>
                                        )}
                                        {canDelete && (
                                            <Tooltip content="Delete" position="top">
                                                <button
                                                    type="button"
                                                    onClick={() => setDeleteConfirmId(report.id)}
                                                    className="rounded-lg border-2 border-red-400 bg-white p-2.5 text-red-700 shadow-sm transition-all hover:border-red-500 hover:bg-red-50"
                                                    aria-label="Delete blood glucose report"
                                                >
                                                    <Trash2 className="h-5 w-5 !text-red-700" strokeWidth={2.5} />
                                                </button>
                                            </Tooltip>
                                        )}
                                    </div>
                                </div>
                            </Card>
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}

export function BloodGlucoseReportForm({
    record,
    residents = [],
    defaultResidentId = '',
    hideResidentSelect = false,
    inModal = false,
    onClose,
    onSuccess,
}) {
    const [formData, setFormData] = useState({
        resident_id: record?.resident_id ? String(record.resident_id) : (defaultResidentId ? String(defaultResidentId) : ''),
        report_date: record?.report_date ? record.report_date.split('T')[0] : new Date().toISOString().split('T')[0],
        notification_level: record?.notification_level || 'low',
        reading_date: record?.reading_date ? record.reading_date.split('T')[0] : new Date().toISOString().split('T')[0],
        reading_time: record?.reading_time ? record.reading_time.slice(0, 5) : new Date().toTimeString().slice(0, 5),
        value: record?.value ?? '',
        method_used: record?.method_used || '',
        fasting: record?.fasting ?? null,
        hours_since_last_meal: record?.hours_since_last_meal ?? '',
        insulin_given: record?.insulin_given ?? null,
        nurse_notified: record?.nurse_notified ?? null,
        treatment_type: record?.treatment_type || '',
        medication_name: record?.medication_name || '',
        medication_amount: record?.medication_amount || '',
        medication_route: record?.medication_route || '',
        medication_strength: record?.medication_strength || '',
        medication_frequency: record?.medication_frequency || '',
        comments: record?.comments || '',
    });
    const [errors, setErrors] = useState({});
    const [isSubmitting, setIsSubmitting] = useState(false);

    const handleSubmit = async (e) => {
        e.preventDefault();
        setErrors({});
        setIsSubmitting(true);

        try {
            const payload = {
                ...formData,
                resident_id: parseInt(formData.resident_id),
                value: formData.value ? parseFloat(formData.value) : null,
                hours_since_last_meal: formData.hours_since_last_meal ? parseFloat(formData.hours_since_last_meal) : null,
                fasting: formData.fasting === null ? null : Boolean(formData.fasting),
                insulin_given: formData.insulin_given === null ? null : Boolean(formData.insulin_given),
                nurse_notified: formData.nurse_notified === null ? null : Boolean(formData.nurse_notified),
                method_used: formData.method_used || null,
                treatment_type: formData.treatment_type || null,
            };

            if (record) {
                await api.put(`/blood-glucose-reports/${record.id}`, payload);
                toast.success('Success', 'Blood glucose report updated successfully', { isFormSubmission: true });
            } else {
                await api.post('/blood-glucose-reports', payload);
                toast.success('Success', 'Blood glucose report recorded successfully', { isFormSubmission: true });
            }
            onSuccess();
        } catch (error) {
            if (error.response?.data?.errors) {
                setErrors(error.response.data.errors);
            } else {
                const errorMessage = error.response?.data?.message || 'Failed to save blood glucose report';
                setErrors({ general: errorMessage });
                toast.error('Error', errorMessage);
            }
        } finally {
            setIsSubmitting(false);
        }
    };

    const YesNo = ({ field }) => (
        <div className="flex items-center gap-4">
            {[['Yes', true], ['No', false]].map(([label, value]) => (
                <label key={label} className="flex items-center gap-1.5 text-sm text-gray-700">
                    <input
                        type="radio"
                        checked={formData[field] === value}
                        onChange={() => setFormData({ ...formData, [field]: value })}
                        className="text-[var(--theme-primary)] focus:ring-[var(--theme-primary)]"
                    />
                    {label}
                </label>
            ))}
        </div>
    );

    return (
        <div className={inModal ? '' : 'bg-white rounded-lg shadow p-6'}>
            {!inModal && (
                <div className="flex items-center justify-between mb-6">
                    <h2 className="text-xl font-semibold text-gray-900">
                        {record ? 'Edit Blood Glucose Report' : 'New Blood Glucose Report'}
                    </h2>
                    <button type="button" onClick={onClose} className="text-gray-400 hover:text-gray-600">
                        <X className="w-6 h-6" />
                    </button>
                </div>
            )}

            {errors.general && (
                <div className="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg">
                    <p className="text-sm text-red-800">{errors.general}</p>
                </div>
            )}

            <form onSubmit={handleSubmit} className="space-y-6">
                <div>
                    <h3 className="text-sm font-bold text-gray-700 uppercase tracking-wide mb-3">General Information</h3>
                    <div className="grid grid-cols-2 gap-4">
                        {!hideResidentSelect && (
                            <div className="col-span-2">
                                <label className="block text-sm font-medium text-gray-700 mb-2">Resident *</label>
                                <select
                                    value={formData.resident_id}
                                    onChange={(e) => setFormData({ ...formData, resident_id: e.target.value })}
                                    required
                                    className="w-full px-4 py-2 border border-gray-300 rounded-lg text-gray-900 bg-white focus:ring-2 focus:ring-[var(--theme-primary)] focus:border-transparent"
                                >
                                    <option value="">Select Resident</option>
                                    {residents.map((r) => (
                                        <option key={r.id} value={r.id}>
                                            {r.first_name} {r.last_name}
                                        </option>
                                    ))}
                                </select>
                                {errors.resident_id && <p className="text-xs text-red-600 mt-1">{errors.resident_id[0]}</p>}
                            </div>
                        )}
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-2">Date *</label>
                            <input
                                type="date"
                                value={formData.report_date}
                                onChange={(e) => setFormData({ ...formData, report_date: e.target.value })}
                                required
                                className="w-full px-4 py-2 border border-gray-300 rounded-lg text-gray-900 bg-white focus:ring-2 focus:ring-[var(--theme-primary)] focus:border-transparent"
                            />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-2">Notification Level</label>
                            <select
                                value={formData.notification_level}
                                onChange={(e) => setFormData({ ...formData, notification_level: e.target.value })}
                                className="w-full px-4 py-2 border border-gray-300 rounded-lg text-gray-900 bg-white focus:ring-2 focus:ring-[var(--theme-primary)] focus:border-transparent"
                            >
                                <option value="low">Low</option>
                                <option value="medium">Medium</option>
                                <option value="high">High</option>
                                <option value="urgent">Urgent</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div className="border-t pt-6">
                    <h3 className="text-sm font-bold text-gray-700 uppercase tracking-wide mb-3">Blood Glucose Information</h3>
                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-2">Date of Reading *</label>
                            <input
                                type="date"
                                value={formData.reading_date}
                                onChange={(e) => setFormData({ ...formData, reading_date: e.target.value })}
                                required
                                className="w-full px-4 py-2 border border-gray-300 rounded-lg text-gray-900 bg-white focus:ring-2 focus:ring-[var(--theme-primary)] focus:border-transparent"
                            />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-2">Time</label>
                            <input
                                type="time"
                                value={formData.reading_time}
                                onChange={(e) => setFormData({ ...formData, reading_time: e.target.value })}
                                className="w-full px-4 py-2 border border-gray-300 rounded-lg text-gray-900 bg-white focus:ring-2 focus:ring-[var(--theme-primary)] focus:border-transparent"
                            />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-2">Value (mg/dL) *</label>
                            <input
                                type="number"
                                step="0.1"
                                min="0"
                                max="1000"
                                value={formData.value}
                                onChange={(e) => setFormData({ ...formData, value: e.target.value })}
                                required
                                className="w-full px-4 py-2 border border-gray-300 rounded-lg text-gray-900 bg-white focus:ring-2 focus:ring-[var(--theme-primary)] focus:border-transparent"
                            />
                            {errors.value && <p className="text-xs text-red-600 mt-1">{errors.value[0]}</p>}
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-2">Method Used</label>
                            <select
                                value={formData.method_used}
                                onChange={(e) => setFormData({ ...formData, method_used: e.target.value })}
                                className="w-full px-4 py-2 border border-gray-300 rounded-lg text-gray-900 bg-white focus:ring-2 focus:ring-[var(--theme-primary)] focus:border-transparent"
                            >
                                <option value="">- Please Select -</option>
                                <option value="fingerstick">Fingerstick</option>
                                <option value="cgm">Continuous Monitor (CGM)</option>
                                <option value="lab_draw">Lab Draw</option>
                            </select>
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-2">Fasting?</label>
                            <YesNo field="fasting" />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-2">Time Since Last Meal (Hrs)</label>
                            <input
                                type="number"
                                step="0.1"
                                min="0"
                                max="48"
                                value={formData.hours_since_last_meal}
                                onChange={(e) => setFormData({ ...formData, hours_since_last_meal: e.target.value })}
                                className="w-full px-4 py-2 border border-gray-300 rounded-lg text-gray-900 bg-white focus:ring-2 focus:ring-[var(--theme-primary)] focus:border-transparent"
                            />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-2">Insulin Given?</label>
                            <YesNo field="insulin_given" />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-2">Nurse/Doctor Notified?</label>
                            <YesNo field="nurse_notified" />
                        </div>
                    </div>
                </div>

                <div className="border-t pt-6">
                    <h3 className="text-sm font-bold text-gray-700 uppercase tracking-wide mb-3">Other Treatment</h3>
                    <div className="mb-4">
                        <label className="block text-sm font-medium text-gray-700 mb-2">Treatment Type</label>
                        <select
                            value={formData.treatment_type}
                            onChange={(e) => setFormData({ ...formData, treatment_type: e.target.value })}
                            className="w-full px-4 py-2 border border-gray-300 rounded-lg text-gray-900 bg-white focus:ring-2 focus:ring-[var(--theme-primary)] focus:border-transparent"
                        >
                            <option value="">- Please Select -</option>
                            <option value="none">None</option>
                            <option value="insulin">Insulin</option>
                            <option value="oral_medication">Oral Medication</option>
                            <option value="dietary">Dietary</option>
                            <option value="other">Other</option>
                        </select>
                    </div>

                    {formData.treatment_type && formData.treatment_type !== 'none' && (
                        <div className="rounded-lg border border-gray-200 p-4">
                            <h4 className="text-sm font-semibold text-gray-900 mb-3">Medication</h4>
                            <div className="grid grid-cols-2 gap-4">
                                <div className="col-span-2">
                                    <label className="block text-sm font-medium text-gray-700 mb-2">Name</label>
                                    <input
                                        type="text"
                                        value={formData.medication_name}
                                        onChange={(e) => setFormData({ ...formData, medication_name: e.target.value })}
                                        className="w-full px-4 py-2 border border-gray-300 rounded-lg text-gray-900 bg-white focus:ring-2 focus:ring-[var(--theme-primary)] focus:border-transparent"
                                    />
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-2">Give Amount / Quantity</label>
                                    <input
                                        type="text"
                                        value={formData.medication_amount}
                                        onChange={(e) => setFormData({ ...formData, medication_amount: e.target.value })}
                                        className="w-full px-4 py-2 border border-gray-300 rounded-lg text-gray-900 bg-white focus:ring-2 focus:ring-[var(--theme-primary)] focus:border-transparent"
                                    />
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-2">Route</label>
                                    <input
                                        type="text"
                                        value={formData.medication_route}
                                        onChange={(e) => setFormData({ ...formData, medication_route: e.target.value })}
                                        className="w-full px-4 py-2 border border-gray-300 rounded-lg text-gray-900 bg-white focus:ring-2 focus:ring-[var(--theme-primary)] focus:border-transparent"
                                    />
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-2">Strength</label>
                                    <input
                                        type="text"
                                        value={formData.medication_strength}
                                        onChange={(e) => setFormData({ ...formData, medication_strength: e.target.value })}
                                        className="w-full px-4 py-2 border border-gray-300 rounded-lg text-gray-900 bg-white focus:ring-2 focus:ring-[var(--theme-primary)] focus:border-transparent"
                                    />
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-2">Frequency</label>
                                    <input
                                        type="text"
                                        value={formData.medication_frequency}
                                        onChange={(e) => setFormData({ ...formData, medication_frequency: e.target.value })}
                                        className="w-full px-4 py-2 border border-gray-300 rounded-lg text-gray-900 bg-white focus:ring-2 focus:ring-[var(--theme-primary)] focus:border-transparent"
                                    />
                                </div>
                            </div>
                        </div>
                    )}
                </div>

                <div className="border-t pt-6">
                    <h3 className="text-sm font-bold text-gray-700 uppercase tracking-wide mb-3">Comments</h3>
                    <textarea
                        value={formData.comments}
                        onChange={(e) => setFormData({ ...formData, comments: e.target.value })}
                        rows={4}
                        className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[var(--theme-primary)] focus:border-transparent"
                        placeholder="Additional notes..."
                    />
                </div>

                <div className="flex items-center justify-end space-x-3 pt-4 border-t">
                    <button
                        type="button"
                        onClick={onClose}
                        className="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors"
                    >
                        Cancel
                    </button>
                    <button
                        type="submit"
                        disabled={isSubmitting}
                        className="px-4 py-2 bg-[var(--theme-primary)] text-[var(--theme-text-on-primary)] rounded-lg hover:bg-[var(--theme-primary-hover)] transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        {isSubmitting ? 'Saving...' : (record ? 'Update' : 'Create')}
                    </button>
                </div>
            </form>
        </div>
    );
}
