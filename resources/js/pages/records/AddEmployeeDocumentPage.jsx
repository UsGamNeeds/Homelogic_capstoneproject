import React from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { useQueryClient } from '@tanstack/react-query';
import { useStaffMember } from '../../hooks/usePersonRecord';
import { EmployeeDocumentForm } from '../EmployeeDocuments';
import PersonBanner from '../../components/records/PersonBanner';

export default function AddEmployeeDocumentPage() {
    const { userId } = useParams();
    const navigate = useNavigate();
    const queryClient = useQueryClient();
    const { data: staffMember, isLoading } = useStaffMember(userId);

    return (
        <div className="space-y-6">
            <PersonBanner
                label="Adding document for"
                name={staffMember?.name}
                meta={staffMember?.role ? `Role: ${staffMember.role}` : undefined}
                changeTo="/team/employee-documents/add"
                isLoading={isLoading}
            />

            <div className="bg-white rounded-lg shadow p-6">
                <EmployeeDocumentForm
                    users={staffMember ? [staffMember] : []}
                    defaultUserId={userId}
                    hideUserSelect
                    inModal={false}
                    onClose={() => navigate('/team/employee-documents')}
                    onSuccess={() => {
                        queryClient.invalidateQueries(['employee-documents']);
                        navigate('/team/employee-documents');
                    }}
                />
            </div>
        </div>
    );
}
