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

    const [formData, setFormData] = useState({
        branch_id: '',
        resident_id: residentId || '',
        appointment_date: new Date().toISOString().split('T')[0],
        appointment_time: '',
        appointment_type_id: '',
        provider_name: '',
        location: '',
        description: '',
        status: 'scheduled',
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

    const isFacilityAdmin = (currentUser?.role || '').toLowerCase().trim() === 'administrator';
    const isBranchAdmin = (currentUser?.role || '').toLowerCase().trim() === 'admin';

    const createMutation = useMutation({
        mutationFn: async () => {
            const payload = {
                resident_id: parseInt(formData.resident_id),
                appointment_date: formData.appointment_date,
                appointment_time: formData.appointment_time || null,
                provider_name: formData.provider_name || null,
                location: formData.location || null,
                description: formData.description || null,
                status: formData.status || 'scheduled',
            };
            return await api.post('/appointments', payload);
        },
        onSuccess: () => {
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
                    formData={formData}
                    setFormData={setFormData}
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
