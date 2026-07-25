import React from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { useQueryClient } from '@tanstack/react-query';
import { useResident } from '../../hooks/usePersonRecord';
import { BloodGlucoseReportForm } from '../BloodGlucoseReports';
import PersonBanner from '../../components/records/PersonBanner';

export default function AddBloodGlucoseReportPage() {
    const { residentId } = useParams();
    const navigate = useNavigate();
    const queryClient = useQueryClient();
    const { data: resident, isLoading } = useResident(residentId);

    const fullName = resident ? [resident.first_name, resident.last_name].filter(Boolean).join(' ') : '';

    return (
        <div className="space-y-6">
            <PersonBanner
                label="Blood glucose report for"
                name={fullName}
                meta={resident?.branch?.name ? `Branch: ${resident.branch.name}` : undefined}
                changeTo="/blood-glucose-reports/add"
                isLoading={isLoading}
            />

            <div className="bg-white rounded-lg shadow p-6">
                <BloodGlucoseReportForm
                    residents={resident ? [resident] : []}
                    defaultResidentId={residentId}
                    hideResidentSelect
                    inModal={false}
                    onClose={() => navigate('/blood-glucose-reports')}
                    onSuccess={() => {
                        queryClient.invalidateQueries(['blood-glucose-reports']);
                        navigate('/blood-glucose-reports');
                    }}
                />
            </div>
        </div>
    );
}
