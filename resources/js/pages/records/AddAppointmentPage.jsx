import React, { useState } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import api from '../../services/api';
import logger from '../../utils/logger';
import { useResident } from '../../hooks/usePersonRecord';
import { AddAppointmentModal } from '../Appointments';
import PersonBanner from '../../components/records/PersonBanner';

export default function AddAppointmentPage() {
    const { residentId } = useParams();
    const navigate = useNavigate();
    const queryClient = useQueryClient();
    const { data: resident, isLoading } = useResident(residentId);
    const [attachments, setAttachments] = useState([]);

    const [formData, setFormData] = useState({
        branch_id: '',
        resident_id: residentId || '',
        appointment_date: new Date().toISOString().split('T')[0],
        appointment_time: '',
        end_time: '',
        appointment_type_id: '',
        provider_name: '',
        location: '',
        description: '',
        status: 'scheduled',
        reported_by: '',
        notification_level: 'low',
        healthcare_provider_id: null,
        consultant_name: '',
        hospital_id: null,
        hospital_name: '',
        other_provider: '',
        specialty: '',
        specialty_id: null,
        specialty_name: '',
        location_type: '',
        address_street1: '',
        address_street2: '',
        address_city: '',
        address_state: '',
        address_zip: '',
        address_country: 'USA',
        latitude: null,
        longitude: null,
        phone: '',
        phone_extension: '',
        driver: '',
        pick_up_at: '',
        depart_time: '',
        reason_for_appointment: '',
        confidential_note: '',
        notes: '',
    });

    React.useEffect(() => {
        if (residentId) {
            setFormData((prev) => ({ ...prev, resident_id: residentId }));
        }
    }, [residentId]);

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
    });

    const { data: usersData } = useQuery({
        queryKey: ['users-list'],
        queryFn: async () => (await api.get('/users', { params: { per_page: 100 } })).data,
    });
    const users = usersData?.data || [];

    const isFacilityAdmin = (currentUser?.role || '').toLowerCase().trim() === 'administrator';
    const isBranchAdmin = (currentUser?.role || '').toLowerCase().trim() === 'admin';

    const createMutation = useMutation({
        mutationFn: async () => {
            const payload = {
                resident_id: parseInt(formData.resident_id),
                appointment_date: formData.appointment_date,
                appointment_time: formData.appointment_time || null,
                end_time: formData.end_time || null,
                provider_name: formData.provider_name || null,
                location: formData.location || null,
                description: formData.description || null,
                status: formData.status || 'scheduled',
                reported_by: formData.reported_by ? parseInt(formData.reported_by) : null,
                notification_level: formData.notification_level || 'low',
                healthcare_provider_id: formData.healthcare_provider_id || null,
                hospital_id: formData.hospital_id || null,
                other_provider: formData.other_provider || null,
                specialty: formData.specialty || null,
                specialty_id: formData.specialty_id || null,
                location_type: formData.location_type || null,
                address_street1: formData.address_street1 || null,
                address_street2: formData.address_street2 || null,
                address_city: formData.address_city || null,
                address_state: formData.address_state || null,
                address_zip: formData.address_zip || null,
                address_country: formData.address_country || null,
                latitude: formData.latitude || null,
                longitude: formData.longitude || null,
                phone: formData.phone || null,
                phone_extension: formData.phone_extension || null,
                driver: formData.driver || null,
                pick_up_at: formData.pick_up_at || null,
                depart_time: formData.depart_time || null,
                reason_for_appointment: formData.reason_for_appointment || null,
                confidential_note: formData.confidential_note || null,
                notes: formData.notes || null,
            };
            return await api.post('/appointments', payload);
        },
        onSuccess: async (response) => {
            const appointmentId = response?.data?.id;

            if (appointmentId && attachments.length > 0) {
                for (const file of attachments) {
                    const fd = new FormData();
                    fd.append('file', file);
                    try {
                        await api.post(`/appointments/${appointmentId}/attachments`, fd, {
                            headers: { 'Content-Type': 'multipart/form-data' },
                        });
                    } catch (err) {
                        logger.error('Failed to upload appointment attachment:', err);
                        toast.error(`Appointment created, but "${file.name}" failed to upload.`);
                    }
                }
            }

            queryClient.invalidateQueries(['appointments']);
            toast.success('Appointment created successfully', '', { isFormSubmission: true });
            navigate('/appointments');
        },
        onError: (error) => {
            logger.error('Error creating appointment:', error);
            toast.error(error.response?.data?.message || 'Failed to create appointment. Please try again.');
        },
    });

    const fullName = resident ? [resident.first_name, resident.last_name].filter(Boolean).join(' ') : '';

    return (
        <div className="space-y-6">
            <PersonBanner
                label="Scheduling appointment for"
                name={fullName}
                meta={resident?.branch?.name ? `Branch: ${resident.branch.name}` : undefined}
                changeTo="/appointments/add"
                isLoading={isLoading}
            />

            <div className="bg-white rounded-lg shadow">
                <AddAppointmentModal
                    branches={resident?.branch ? [resident.branch] : []}
                    residents={resident ? [resident] : []}
                    users={users}
                    formData={formData}
                    setFormData={setFormData}
                    attachments={attachments}
                    setAttachments={setAttachments}
                    onClose={() => navigate('/appointments')}
                    onSubmit={() => createMutation.mutate()}
                    isSubmitting={createMutation.isPending}
                    mutation={createMutation}
                    isPreFilled
                    appointmentTypes={appointmentTypes}
                    currentUser={currentUser}
                    isFacilityAdmin={isFacilityAdmin}
                    isBranchAdmin={isBranchAdmin}
                    selectedBranchId={resident?.branch_id}
                    inModal={false}
                />
            </div>
        </div>
    );
}
