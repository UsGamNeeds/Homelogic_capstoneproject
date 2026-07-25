import React, { useState } from 'react';
import { useParams, Link, useNavigate } from 'react-router-dom';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import api from '../services/api';
import logger from '../utils/logger';
import {
    ArrowLeft,
    Calendar,
    Clock,
    MapPin,
    User,
    Stethoscope,
    FileText,
    Download,
    Eye,
    CheckCircle,
    XCircle,
    AlertCircle,
    Building2,
    Edit,
} from 'lucide-react';
import Tooltip from '../components/ui/Tooltip';
import { AddAppointmentModal } from './Appointments';

// Builds the AddAppointmentModal's formData shape from a fetched appointment record.
// Time columns come back as "HH:mm:ss" from the backend; the form's TimePicker expects "HH:mm".
function appointmentToFormData(appointment) {
    const trimTime = (t) => (t ? t.slice(0, 5) : '');
    return {
        branch_id: appointment.branch_id ? String(appointment.branch_id) : '',
        resident_id: appointment.resident_id ? String(appointment.resident_id) : '',
        appointment_date: appointment.appointment_date ? appointment.appointment_date.split('T')[0] : '',
        appointment_time: trimTime(appointment.appointment_time),
        end_time: trimTime(appointment.end_time),
        appointment_type_id: appointment.appointment_type_id ? String(appointment.appointment_type_id) : '',
        provider_name: appointment.provider_name || '',
        location: appointment.location || '',
        description: appointment.description || '',
        status: appointment.status || 'scheduled',
        reported_by: appointment.reported_by ? String(appointment.reported_by) : '',
        notification_level: appointment.notification_level || 'low',
        healthcare_provider_id: appointment.healthcare_provider_id || null,
        consultant_name: appointment.healthcare_provider?.name || '',
        hospital_id: appointment.hospital_id || null,
        hospital_name: appointment.hospital?.name || '',
        other_provider: appointment.other_provider || '',
        specialty: appointment.specialty || '',
        specialty_id: appointment.specialty_id || null,
        specialty_name: appointment.specialty_lookup?.name || appointment.specialty || '',
        location_type: appointment.location_type || '',
        address_street1: appointment.address_street1 || '',
        address_street2: appointment.address_street2 || '',
        address_city: appointment.address_city || '',
        address_state: appointment.address_state || '',
        address_zip: appointment.address_zip || '',
        address_country: appointment.address_country || 'USA',
        latitude: appointment.latitude || null,
        longitude: appointment.longitude || null,
        phone: appointment.phone || '',
        phone_extension: appointment.phone_extension || '',
        driver: appointment.driver || '',
        pick_up_at: appointment.pick_up_at || '',
        depart_time: trimTime(appointment.depart_time),
        reason_for_appointment: appointment.reason_for_appointment || '',
        confidential_note: appointment.confidential_note || '',
        notes: appointment.notes || '',
    };
}

export default function AppointmentDetail() {
    const { id } = useParams();
    const navigate = useNavigate();
    const queryClient = useQueryClient();
    const [isEditing, setIsEditing] = useState(false);
    const [formData, setFormData] = useState(null);

    const { data: appointment, isLoading, error } = useQuery({
        queryKey: ['appointment-detail', id],
        queryFn: async () => {
            const response = await api.get(`/appointments/${id}`);
            return response.data;
        },
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

    const { data: appointmentTypes } = useQuery({
        queryKey: ['appointment-types'],
        queryFn: async () => {
            const response = await api.get('/appointment-types');
            return response.data?.data || response.data || [];
        },
        enabled: isEditing,
    });

    const { data: usersData } = useQuery({
        queryKey: ['users-list'],
        queryFn: async () => (await api.get('/users', { params: { per_page: 100 } })).data,
        enabled: isEditing,
    });

    const { data: branchesData } = useQuery({
        queryKey: ['branches-list'],
        queryFn: async () => (await api.get('/branches', { params: { per_page: 100 } })).data,
        enabled: isEditing,
    });

    const { data: residentsData } = useQuery({
        queryKey: ['residents-list', formData?.branch_id || 'all'],
        queryFn: async () => (await api.get('/residents', { params: { per_page: 200, ...(formData?.branch_id ? { branch_id: formData.branch_id } : {}) } })).data,
        enabled: isEditing,
    });

    const isSuperAdmin = currentUser?.role === 'super_admin';
    const isAdmin = currentUser?.role === 'administrator' || currentUser?.role === 'admin';
    const isFacilityAdmin = currentUser?.role === 'administrator';
    const isBranchAdmin = currentUser?.role === 'admin';
    const permissions = Array.isArray(currentUser?.permissions) ? currentUser.permissions : [];
    const canEdit = isSuperAdmin || isAdmin || permissions.includes('edit_appointments');

    const updateMutation = useMutation({
        mutationFn: async () => {
            const payload = { ...formData };
            delete payload.consultant_name;
            delete payload.hospital_name;
            delete payload.specialty_name;
            return await api.put(`/appointments/${id}`, payload);
        },
        onSuccess: () => {
            queryClient.invalidateQueries(['appointment-detail', id]);
            queryClient.invalidateQueries(['appointments']);
            toast.success('Appointment updated successfully', '', { isFormSubmission: true });
            setIsEditing(false);
        },
        onError: (err) => {
            logger.error('Error updating appointment:', err);
            toast.error(err.response?.data?.message || 'Failed to update appointment. Please try again.');
        },
    });

    const startEditing = () => {
        setFormData(appointmentToFormData(appointment));
        setIsEditing(true);
    };

    const formatDateTime = (date, time) => {
        if (!date) return 'N/A';
        const dateObj = new Date(date);
        const formattedDate = dateObj.toLocaleDateString('en-US', {
            weekday: 'short',
            year: 'numeric',
            month: 'short',
            day: 'numeric'
        });
        
        if (time) {
            // Time is in HH:mm:ss format, convert to 12-hour format
            const [hours, minutes] = time.split(':');
            const hour12 = hours % 12 || 12;
            const ampm = hours >= 12 ? 'PM' : 'AM';
            return `${formattedDate} at ${hour12}:${minutes} ${ampm}`;
        }
        
        return formattedDate;
    };

    const getStatusBadge = (status) => {
        const statusConfig = {
            scheduled: { bg: 'bg-gray-100', text: 'text-gray-800', label: 'Scheduled', icon: Calendar },
            confirmed: { bg: 'bg-blue-100', text: 'text-blue-800', label: 'Confirmed', icon: CheckCircle },
            in_progress: { bg: 'bg-yellow-100', text: 'text-yellow-800', label: 'In Progress', icon: AlertCircle },
            completed: { bg: 'bg-green-100', text: 'text-green-800', label: 'Completed', icon: CheckCircle },
            cancelled: { bg: 'bg-red-100', text: 'text-red-800', label: 'Cancelled', icon: XCircle },
            no_show: { bg: 'bg-orange-100', text: 'text-orange-800', label: 'No Show', icon: XCircle },
            rescheduled: { bg: 'bg-purple-100', text: 'text-purple-800', label: 'Rescheduled', icon: Calendar },
        };

        const config = statusConfig[status] || statusConfig.scheduled;
        const Icon = config.icon;

        return (
            <span className={`inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-sm font-medium ${config.bg} ${config.text}`}>
                <Icon className="w-4 h-4" />
                {config.label}
            </span>
        );
    };

    const handleDownloadDocument = async (doc) => {
        try {
            const response = await api.get(
                `/resident-documents/${doc.id}/download`,
                { responseType: 'blob' }
            );
            
            // Create a blob URL and trigger download
            const url = window.URL.createObjectURL(new Blob([response.data]));
            const link = window.document.createElement('a');
            link.href = url;
            link.setAttribute('download', doc.file_name || doc.document_name || 'document');
            window.document.body.appendChild(link);
            link.click();
            link.remove();
            window.URL.revokeObjectURL(url);
        } catch (error) {
            logger.error('Error downloading document:', error);
            alert(error.response?.data?.message || 'Failed to download document. Please try again.');
        }
    };

    const handleViewDocument = (doc) => {
        if (doc.file_path) {
            const url = `/storage/${doc.file_path}`;
            window.open(url, '_blank');
        }
    };

    if (isLoading) {
        return (
            <div className="text-center py-12">
                <div className="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-[var(--theme-primary)]"></div>
                <p className="mt-4 text-gray-600">Loading appointment details...</p>
            </div>
        );
    }

    if (error) {
        return (
            <div className="bg-red-50 border border-red-200 rounded-lg p-4">
                <p className="text-red-800 text-sm">{error.response?.data?.message || error.message || 'Failed to load appointment details'}</p>
                <Link 
                    to="/appointments/dashboard" 
                    className="mt-4 inline-block text-red-600 hover:text-red-800 text-sm font-medium"
                >
                    ← Back to Appointments Dashboard
                </Link>
            </div>
        );
    }

    if (!appointment) {
        return (
            <div className="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                <p className="text-yellow-800 text-sm">Appointment not found</p>
                <Link
                    to="/appointments/dashboard"
                    className="mt-4 inline-block text-yellow-600 hover:text-yellow-800 text-sm font-medium"
                >
                    ← Back to Appointments Dashboard
                </Link>
            </div>
        );
    }

    if (isEditing && formData) {
        return (
            <div>
                <div className="flex items-center gap-3 mb-6">
                    <button
                        type="button"
                        onClick={() => setIsEditing(false)}
                        className="px-3 py-2 rounded-lg border hover:bg-gray-50 text-gray-700 inline-flex items-center"
                    >
                        <ArrowLeft className="w-4 h-4 mr-2" /> Back
                    </button>
                    <h1 className="text-3xl font-bold text-gray-900">Edit Appointment</h1>
                </div>
                <div className="bg-white rounded-lg shadow">
                    <AddAppointmentModal
                        branches={branchesData?.data || []}
                        residents={residentsData?.data || []}
                        users={usersData?.data || []}
                        formData={formData}
                        setFormData={setFormData}
                        attachments={[]}
                        setAttachments={() => {}}
                        onClose={() => setIsEditing(false)}
                        onSubmit={() => updateMutation.mutate()}
                        isSubmitting={updateMutation.isPending}
                        mutation={updateMutation}
                        appointmentTypes={appointmentTypes}
                        currentUser={currentUser}
                        isFacilityAdmin={isFacilityAdmin}
                        isBranchAdmin={isBranchAdmin}
                        inModal={false}
                    />
                </div>
            </div>
        );
    }

    return (
        <div>
            {/* Header */}
            <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
                <div className="flex items-center space-x-3">
                    <Link 
                        to="/appointments/dashboard" 
                        className="px-3 py-2 rounded-lg border hover:bg-gray-50 text-gray-700 inline-flex items-center"
                    >
                        <ArrowLeft className="w-4 h-4 mr-2" /> Back
                    </Link>
                    <div>
                        <h1 className="text-3xl font-bold text-gray-900">Appointment Details</h1>
                        <p className="text-sm text-gray-600 mt-1">
                            {formatDateTime(appointment.appointment_date, appointment.appointment_time)}
                        </p>
                    </div>
                </div>
                <div className="flex items-center gap-3">
                    {getStatusBadge(appointment.status)}
                    {canEdit && (
                        <button
                            type="button"
                            onClick={startEditing}
                            className="px-3 py-2 rounded-lg bg-[var(--theme-primary)] text-[var(--theme-text-on-primary)] hover:bg-[var(--theme-primary-hover)] inline-flex items-center text-sm font-semibold"
                        >
                            <Edit className="w-4 h-4 mr-2" /> Edit
                        </button>
                    )}
                </div>
            </div>

            {/* Main Content Grid */}
            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                {/* Left Column - Main Details */}
                <div className="lg:col-span-2 space-y-6">
                    {/* Appointment Information */}
                    <div className="bg-white rounded-lg shadow p-6">
                        <h2 className="text-lg font-semibold text-gray-900 mb-4">Appointment Information</h2>
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div className="flex items-start space-x-3">
                                <Calendar className="w-5 h-5 text-gray-400 mt-0.5" />
                                <div>
                                    <p className="text-xs text-gray-500">Date</p>
                                    <p className="text-sm font-medium text-gray-900">
                                        {appointment.appointment_date 
                                            ? new Date(appointment.appointment_date).toLocaleDateString('en-US', {
                                                year: 'numeric',
                                                month: 'long',
                                                day: 'numeric'
                                            })
                                            : 'N/A'
                                        }
                                    </p>
                                </div>
                            </div>

                            {appointment.appointment_time && (
                                <div className="flex items-start space-x-3">
                                    <Clock className="w-5 h-5 text-gray-400 mt-0.5" />
                                    <div>
                                        <p className="text-xs text-gray-500">Time</p>
                                        <p className="text-sm font-medium text-gray-900">
                                            {(() => {
                                                const [hours, minutes] = appointment.appointment_time.split(':');
                                                const hour12 = hours % 12 || 12;
                                                const ampm = hours >= 12 ? 'PM' : 'AM';
                                                return `${hour12}:${minutes} ${ampm}`;
                                            })()}
                                        </p>
                                    </div>
                                </div>
                            )}

                            {appointment.location && (
                                <div className="flex items-start space-x-3">
                                    <MapPin className="w-5 h-5 text-gray-400 mt-0.5" />
                                    <div>
                                        <p className="text-xs text-gray-500">Location</p>
                                        <p className="text-sm font-medium text-gray-900">{appointment.location}</p>
                                    </div>
                                </div>
                            )}

                            {appointment.provider_name && (
                                <div className="flex items-start space-x-3">
                                    <Stethoscope className="w-5 h-5 text-gray-400 mt-0.5" />
                                    <div>
                                        <p className="text-xs text-gray-500">Provider</p>
                                        <p className="text-sm font-medium text-gray-900">{appointment.provider_name}</p>
                                    </div>
                                </div>
                            )}

                            {appointment.appointmentType && (
                                <div className="flex items-start space-x-3">
                                    <FileText className="w-5 h-5 text-gray-400 mt-0.5" />
                                    <div>
                                        <p className="text-xs text-gray-500">Type</p>
                                        <p className="text-sm font-medium text-gray-900">
                                            {appointment.appointmentType.name || appointment.appointment_type?.name || 'N/A'}
                                        </p>
                                    </div>
                                </div>
                            )}

                            {appointment.healthcare_provider && (
                                <div className="flex items-start space-x-3">
                                    <User className="w-5 h-5 text-gray-400 mt-0.5" />
                                    <div>
                                        <p className="text-xs text-gray-500">Consultant</p>
                                        <p className="text-sm font-medium text-gray-900">
                                            {appointment.healthcare_provider.name || 'N/A'}
                                        </p>
                                    </div>
                                </div>
                            )}

                            {appointment.hospital && (
                                <div className="flex items-start space-x-3">
                                    <Building2 className="w-5 h-5 text-gray-400 mt-0.5" />
                                    <div>
                                        <p className="text-xs text-gray-500">Hospital</p>
                                        <p className="text-sm font-medium text-gray-900">{appointment.hospital.name}</p>
                                    </div>
                                </div>
                            )}

                            {(appointment.specialty_lookup?.name || appointment.specialty) && (
                                <div className="flex items-start space-x-3">
                                    <Stethoscope className="w-5 h-5 text-gray-400 mt-0.5" />
                                    <div>
                                        <p className="text-xs text-gray-500">Specialty</p>
                                        <p className="text-sm font-medium text-gray-900">
                                            {appointment.specialty_lookup?.name || appointment.specialty}
                                        </p>
                                    </div>
                                </div>
                            )}

                            {appointment.branch && (
                                <div className="flex items-start space-x-3">
                                    <Building2 className="w-5 h-5 text-gray-400 mt-0.5" />
                                    <div>
                                        <p className="text-xs text-gray-500">Branch</p>
                                        <p className="text-sm font-medium text-gray-900">{appointment.branch.name}</p>
                                    </div>
                                </div>
                            )}
                        </div>

                        {appointment.description && (
                            <div className="mt-4 pt-4 border-t border-gray-200">
                                <p className="text-xs text-gray-500 mb-1">Description / Reason for Scheduling</p>
                                <p className="text-sm text-gray-700 whitespace-pre-wrap">{appointment.description}</p>
                            </div>
                        )}

                        {/* Reschedule Information */}
                        {appointment.status === 'rescheduled' && appointment.original_appointment_date && (
                            <div className="mt-4 pt-4 border-t border-gray-200">
                                <p className="text-xs text-gray-500 mb-2 font-semibold">Reschedule Information</p>
                                <div className="space-y-2">
                                    <div className="flex items-start space-x-3">
                                        <Calendar className="w-4 h-4 text-gray-400 mt-0.5 flex-shrink-0" />
                                        <div>
                                            <p className="text-xs text-gray-500">Original Scheduled Date</p>
                                            <p className="text-sm font-medium text-gray-900">
                                                {new Date(appointment.original_appointment_date).toLocaleDateString('en-US', {
                                                    year: 'numeric',
                                                    month: 'long',
                                                    day: 'numeric'
                                                })}
                                                {appointment.original_appointment_time && (
                                                    <span className="ml-2">
                                                        at {(() => {
                                                            const [hours, minutes] = appointment.original_appointment_time.split(':');
                                                            const hour12 = hours % 12 || 12;
                                                            const ampm = hours >= 12 ? 'PM' : 'AM';
                                                            return `${hour12}:${minutes} ${ampm}`;
                                                        })()}
                                                    </span>
                                                )}
                                            </p>
                                        </div>
                                    </div>
                                    <div className="flex items-start space-x-3">
                                        <Calendar className="w-4 h-4 text-gray-400 mt-0.5 flex-shrink-0" />
                                        <div>
                                            <p className="text-xs text-gray-500">New Scheduled Date</p>
                                            <p className="text-sm font-medium text-gray-900">
                                                {appointment.appointment_date 
                                                    ? new Date(appointment.appointment_date).toLocaleDateString('en-US', {
                                                        year: 'numeric',
                                                        month: 'long',
                                                        day: 'numeric'
                                                    })
                                                    : 'N/A'
                                                }
                                                {appointment.appointment_time && (
                                                    <span className="ml-2">
                                                        at {(() => {
                                                            const [hours, minutes] = appointment.appointment_time.split(':');
                                                            const hour12 = hours % 12 || 12;
                                                            const ampm = hours >= 12 ? 'PM' : 'AM';
                                                            return `${hour12}:${minutes} ${ampm}`;
                                                        })()}
                                                    </span>
                                                )}
                                            </p>
                                        </div>
                                    </div>
                                    {appointment.reschedule_reason && (
                                        <div className="mt-3 pt-3 border-t border-gray-100">
                                            <p className="text-xs text-gray-500 mb-1">Reason for Rescheduling</p>
                                            <p className="text-sm text-gray-700 whitespace-pre-wrap bg-gray-50 rounded p-2">{appointment.reschedule_reason}</p>
                                        </div>
                                    )}
                                </div>
                            </div>
                        )}
                    </div>

                    {/* Notes Section */}
                    {appointment.notes && (
                        <div className="bg-white rounded-lg shadow p-6">
                            <h2 className="text-lg font-semibold text-gray-900 mb-4">Notes</h2>
                            <div className="bg-gray-50 rounded-lg p-4">
                                <p className="text-sm text-gray-700 whitespace-pre-wrap">{appointment.notes}</p>
                            </div>
                        </div>
                    )}

                    {/* Documents Section */}
                    <div className="bg-white rounded-lg shadow p-6">
                        <h2 className="text-lg font-semibold text-gray-900 mb-4">
                            Documents {appointment.documents && appointment.documents.length > 0 && `(${appointment.documents.length})`}
                        </h2>
                        {appointment.documents && appointment.documents.length > 0 ? (
                            <div className="space-y-3">
                                {appointment.documents.map((doc) => (
                                    <div 
                                        key={doc.id}
                                        className="border border-gray-200 rounded-lg p-4 hover:border-[var(--theme-primary)] transition-colors"
                                    >
                                        <div className="flex items-start justify-between">
                                            <div className="flex-1">
                                                <div className="flex items-center gap-2 mb-2">
                                                    <FileText className="w-5 h-5 text-gray-400" />
                                                    <h3 className="text-sm font-medium text-gray-900">
                                                        {doc.document_name}
                                                    </h3>
                                                    {doc.document_type && (
                                                        <span className="px-2 py-0.5 bg-gray-100 text-gray-600 rounded text-xs">
                                                            {doc.document_type}
                                                        </span>
                                                    )}
                                                </div>
                                                {doc.notes && (
                                                    <p className="text-sm text-gray-600 mb-2">{doc.notes}</p>
                                                )}
                                                <div className="flex items-center gap-4 text-xs text-gray-500">
                                                    {doc.file_name && (
                                                        <span>File: {doc.file_name}</span>
                                                    )}
                                                    {doc.file_size_human && (
                                                        <span>Size: {doc.file_size_human}</span>
                                                    )}
                                                    {doc.uploaded_by && doc.uploadedBy && (
                                                        <span>Uploaded by: {doc.uploadedBy.name || 'N/A'}</span>
                                                    )}
                                                </div>
                                            </div>
                                            <div className="flex items-center gap-2 ml-4">
                                                <Tooltip content="View document" position="top">
                                                    <button
                                                        type="button"
                                                        onClick={() => handleViewDocument(doc)}
                                                        className="p-2 text-gray-600 hover:text-[var(--theme-primary)] hover:bg-gray-100 rounded-lg transition-colors"
                                                        aria-label="View document"
                                                    >
                                                        <Eye className="w-4 h-4" strokeWidth={2.25} />
                                                    </button>
                                                </Tooltip>
                                                <Tooltip content="Download document" position="top">
                                                    <button
                                                        type="button"
                                                        onClick={() => handleDownloadDocument(doc)}
                                                        className="p-2 text-gray-600 hover:text-[var(--theme-primary)] hover:bg-gray-100 rounded-lg transition-colors"
                                                        aria-label="Download document"
                                                    >
                                                        <Download className="w-4 h-4" strokeWidth={2.25} />
                                                    </button>
                                                </Tooltip>
                                            </div>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        ) : (
                            <div className="text-center py-8 bg-gray-50 rounded-lg">
                                <FileText className="w-12 h-12 text-gray-400 mx-auto mb-3" />
                                <p className="text-sm text-gray-600">No documents attached to this appointment</p>
                            </div>
                        )}
                    </div>
                </div>

                {/* Right Column - Resident Information */}
                <div className="space-y-6">
                    <div className="bg-white rounded-lg shadow p-6">
                        <h2 className="text-lg font-semibold text-gray-900 mb-4">Resident Information</h2>
                        {appointment.resident ? (
                            <div className="space-y-4">
                                <div>
                                    <p className="text-xs text-gray-500">Name</p>
                                    <p className="text-sm font-medium text-gray-900">
                                        {appointment.resident.first_name} {appointment.resident.last_name}
                                    </p>
                                </div>
                                {appointment.resident.date_of_birth && (
                                    <div>
                                        <p className="text-xs text-gray-500">Date of Birth</p>
                                        <p className="text-sm font-medium text-gray-900">
                                            {new Date(appointment.resident.date_of_birth).toLocaleDateString()}
                                        </p>
                                    </div>
                                )}
                                {appointment.resident.gender && (
                                    <div>
                                        <p className="text-xs text-gray-500">Gender</p>
                                        <p className="text-sm font-medium text-gray-900">{appointment.resident.gender}</p>
                                    </div>
                                )}
                                {appointment.resident.diagnosis && (
                                    <div>
                                        <p className="text-xs text-gray-500">Diagnosis</p>
                                        <p className="text-sm font-medium text-gray-900">{appointment.resident.diagnosis}</p>
                                    </div>
                                )}
                                {(appointment.resident.primary_care_doctor || appointment.resident.physician_name) && (
                                    <div>
                                        <p className="text-xs text-gray-500">Physician</p>
                                        <p className="text-sm font-medium text-gray-900">
                                            {appointment.resident.primary_care_doctor || appointment.resident.physician_name}
                                        </p>
                                    </div>
                                )}
                                <Link
                                    to={`/my-residents/${appointment.resident.id}`}
                                    className="inline-block mt-4 text-sm text-[var(--theme-primary)] hover:underline"
                                >
                                    View Resident Profile →
                                </Link>
                            </div>
                        ) : (
                            <p className="text-sm text-gray-600">Resident information not available</p>
                        )}
                    </div>

                    {/* Additional Information */}
                    <div className="bg-white rounded-lg shadow p-6">
                        <h2 className="text-lg font-semibold text-gray-900 mb-4">Additional Information</h2>
                        <div className="space-y-3 text-sm">
                            {appointment.created_by && appointment.createdBy && (
                                <div>
                                    <p className="text-gray-500">Created by</p>
                                    <p className="font-medium text-gray-900">{appointment.createdBy.name || 'N/A'}</p>
                                </div>
                            )}
                            {appointment.created_at && (
                                <div>
                                    <p className="text-gray-500">Created on</p>
                                    <p className="font-medium text-gray-900">
                                        {new Date(appointment.created_at).toLocaleDateString('en-US', {
                                            year: 'numeric',
                                            month: 'long',
                                            day: 'numeric',
                                            hour: '2-digit',
                                            minute: '2-digit'
                                        })}
                                    </p>
                                </div>
                            )}
                            {appointment.updated_at && (
                                <div>
                                    <p className="text-gray-500">Last updated</p>
                                    <p className="font-medium text-gray-900">
                                        {new Date(appointment.updated_at).toLocaleDateString('en-US', {
                                            year: 'numeric',
                                            month: 'long',
                                            day: 'numeric',
                                            hour: '2-digit',
                                            minute: '2-digit'
                                        })}
                                    </p>
                                </div>
                            )}
                            {appointment.next_appointment_date && (
                                <div>
                                    <p className="text-gray-500">Next Appointment</p>
                                    <p className="font-medium text-gray-900">
                                        {new Date(appointment.next_appointment_date).toLocaleDateString('en-US', {
                                            year: 'numeric',
                                            month: 'long',
                                            day: 'numeric'
                                        })}
                                    </p>
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}

