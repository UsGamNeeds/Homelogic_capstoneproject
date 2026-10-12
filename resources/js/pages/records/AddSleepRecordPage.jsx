import React from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import api from '../../services/api';
import { useResident } from '../../hooks/usePersonRecord';
import { SleepRecordForm } from '../Sleep';
import PersonBanner from '../../components/records/PersonBanner';

export default function AddSleepRecordPage() {
    const { residentId } = useParams();
    const navigate = useNavigate();
    const queryClient = useQueryClient();
    const { data: resident, isLoading } = useResident(residentId);

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

    const isCaregiver = ['caregiver', 'care_giver', 'nurse', 'registered_nurse', 'licensed_nurse'].includes(
        (currentUser?.role || '').toLowerCase().trim(),
    );
    const isFacilityAdmin = (currentUser?.role || '').toLowerCase().trim() === 'administrator';
    const isBranchAdmin = (currentUser?.role || '').toLowerCase().trim() === 'admin';

    const fullName = resident ? [resident.first_name, resident.last_name].filter(Boolean).join(' ') : '';

    return (
        <div className="space-y-6">
            <PersonBanner
                label="Recording sleep record for"
                name={fullName}
                meta={resident?.branch?.name ? `Branch: ${resident.branch.name}` : undefined}
                changeTo="/sleep/add"
                isLoading={isLoading}
            />

            <div className="bg-white rounded-lg shadow p-6">
                <SleepRecordForm
                    residents={resident ? [resident] : []}
                    isCaregiver={isCaregiver}
                    caregiverBranchId={isCaregiver ? currentUser?.assigned_branch_id : null}
                    caregiverBranchName={isCaregiver ? resident?.branch?.name : null}
                    currentUser={currentUser}
                    isFacilityAdmin={isFacilityAdmin}
                    isBranchAdmin={isBranchAdmin}
                    defaultResidentId={residentId}
                    hideResidentSelect
                    inModal={false}
                    onClose={() => navigate('/sleep')}
                    onSuccess={() => {
                        queryClient.invalidateQueries(['sleep-records']);
                        queryClient.invalidateQueries(['sleep-records-calendar']);
                        navigate('/sleep');
                    }}
                />
            </div>
        </div>
    );
}
