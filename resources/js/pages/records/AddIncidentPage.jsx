import React, { useState } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { useForm } from 'react-hook-form';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { toast } from 'sonner';
import api from '../../services/api';
import { offlinePost } from '../../services/offlineApi';
import { useResident } from '../../hooks/usePersonRecord';
import { IncidentForm } from '../Incidents';
import PersonBanner from '../../components/records/PersonBanner';
import logger from '../../utils/logger';

export default function AddIncidentPage() {
    const { residentId } = useParams();
    const navigate = useNavigate();
    const queryClient = useQueryClient();
    const { data: resident, isLoading } = useResident(residentId);
    const [attachments, setAttachments] = useState([]);

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

    const { data: usersData } = useQuery({
        queryKey: ['users-list'],
        queryFn: async () => (await api.get('/users', { params: { per_page: 100 } })).data,
    });
    const users = usersData?.data || [];

    const isCaregiver = ['caregiver', 'care_giver', 'nurse', 'registered_nurse', 'licensed_nurse'].includes(
        (currentUser?.role || '').toLowerCase().trim(),
    );

    const methods = useForm({
        defaultValues: {
            resident_id: residentId || '',
            branch_id: resident?.branch_id || '',
            incident_type: '',
            description: '',
            incident_date: new Date().toISOString().slice(0, 16),
            location: '',
            severity: 'low',
            priority: 'medium',
            status: 'open',
            action_taken: '',
            witnesses: '',
            follow_up: '',
            assigned_to: '',
        },
    });

    // Once the resident record (with branch_id) has loaded, seed branch_id into the form
    React.useEffect(() => {
        if (resident?.branch_id) {
            methods.setValue('branch_id', resident.branch_id);
        }
    }, [resident?.branch_id, methods]);

    const createMutation = useMutation({
        mutationFn: async (formDataToSend) => {
            const hasFiles = formDataToSend instanceof FormData && Array.from(formDataToSend.values()).some((v) => v instanceof File);

            if (hasFiles) {
                if (!navigator.onLine) {
                    throw new Error('File uploads require an internet connection. Please connect to the internet and try again.');
                }
                return await api.post('/incidents', formDataToSend, {
                    headers: { 'Content-Type': 'multipart/form-data' },
                });
            }

            const data = formDataToSend instanceof FormData ? Object.fromEntries(formDataToSend.entries()) : formDataToSend;
            const result = await offlinePost('/incidents', data);
            if (!result.online) {
                return { data: result.data };
            }
            return result;
        },
        onSuccess: (response) => {
            queryClient.invalidateQueries(['incidents']);
            if (response.data?.offline) {
                toast.success('Incident saved offline - will sync when online', '', { isFormSubmission: true });
            } else {
                toast.success('Incident created successfully', '', { isFormSubmission: true });
            }
            navigate('/incidents');
        },
        onError: (error) => {
            logger.error('Error creating incident:', error);
            if (error.message && error.message.includes('internet connection')) {
                toast.error(error.message);
            } else if (error.response?.status === 413) {
                toast.error('File size too large. Maximum file size is 2MB per file, and total request size is 8MB. Please reduce file sizes and try again.');
            } else if (error.response?.status === 422) {
                const errors = error.response?.data?.errors;
                if (errors) {
                    const firstError = Object.values(errors)[0];
                    toast.error(Array.isArray(firstError) ? firstError[0] : firstError);
                } else {
                    toast.error(error.response?.data?.message || 'Validation failed');
                }
            } else if (error.response?.status === 500) {
                toast.error(error.response?.data?.message || 'Server error occurred. Please try again or contact support.');
            } else {
                toast.error(error.response?.data?.message || 'Failed to create incident');
            }
        },
    });

    const fullName = resident ? [resident.first_name, resident.last_name].filter(Boolean).join(' ') : '';

    return (
        <div className="space-y-6">
            <PersonBanner
                label="Reporting incident for"
                name={fullName}
                meta={resident?.branch?.name ? `Branch: ${resident.branch.name}` : undefined}
                changeTo="/incidents/add"
                isLoading={isLoading}
            />

            <IncidentForm
                record={null}
                branches={resident?.branch ? [resident.branch] : []}
                residents={resident ? [resident] : []}
                users={users}
                attachments={attachments}
                setAttachments={setAttachments}
                currentUser={currentUser}
                isCaregiver={isCaregiver}
                onClose={() => navigate('/incidents')}
                onSuccess={() => navigate('/incidents')}
                createMutation={createMutation}
                updateMutation={{ isPending: false, mutate: () => {} }}
                methods={methods}
                branchId={resident?.branch_id || ''}
                hideResidentSelect
                inModal={false}
            />
        </div>
    );
}
