import React from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { useQueryClient } from '@tanstack/react-query';
import { useResident } from '../../hooks/usePersonRecord';
import { VitalSignForm } from '../Vitals';
import PersonBanner from '../../components/records/PersonBanner';

export default function AddVitalPage() {
    const { residentId } = useParams();
    const navigate = useNavigate();
    const queryClient = useQueryClient();
    const { data: resident, isLoading } = useResident(residentId);

    const fullName = resident ? [resident.first_name, resident.last_name].filter(Boolean).join(' ') : '';

    return (
        <div className="space-y-6">
            <PersonBanner
                label="Recording vitals for"
                name={fullName}
                meta={resident?.branch?.name ? `Branch: ${resident.branch.name}` : undefined}
                changeTo="/vitals/add"
                isLoading={isLoading}
            />

            <div className="bg-white rounded-lg shadow p-6">
                <VitalSignForm
                    residents={resident ? [resident] : []}
                    branches={resident?.branch ? [resident.branch] : []}
                    initialResidentId={residentId}
                    hideResidentSelect
                    inModal={false}
                    onClose={() => navigate('/vitals')}
                    onSuccess={() => {
                        queryClient.invalidateQueries(['vitals']);
                        navigate('/vitals');
                    }}
                />
            </div>
        </div>
    );
}
