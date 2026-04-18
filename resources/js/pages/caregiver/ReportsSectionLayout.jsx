import React from 'react';
import {
    LayoutDashboard,
    TrendingUp,
    Activity,
    Wrench,
    Building2,
} from 'lucide-react';
import { useQuery } from '@tanstack/react-query';
import SectionLayout from '../../components/SectionLayout';
import { currentUserQueryOptions } from '../../queries/currentUser';
import { isCaregiverRole } from '../../utils/userRoles';

const ALL_TABS = [
    { id: 'overview', label: 'Overview', icon: LayoutDashboard, path: '/reports', exact: true },
    { id: 'analytics', label: 'Analytics', icon: TrendingUp, path: '/reports/analytics' },
    {
        id: 'clinical',
        label: 'Clinical',
        icon: Activity,
        path: '/reports/vitals-charts',
        extraPaths: [
            '/reports/vitals-reports',
            '/reports/vitals-history',
            '/reports/assessment-charts',
            '/reports/appointments-charts',
            '/reports/sleep-charts',
            '/reports/care-logs',
            '/reports/inspection-package',
        ],
    },
    {
        id: 'operations',
        label: 'Operations',
        icon: Wrench,
        path: '/reports/housekeeping',
        extraPaths: [
            '/reports/grocery-status',
            '/reports/fire-drills',
            '/reports/incidents',
        ],
    },
    {
        // Admin-only: staff performance charts, pharmacy reports, full resident charts
        id: 'administrative',
        label: 'Administrative',
        icon: Building2,
        path: '/reports/charts',
        adminOnly: true,
        extraPaths: [
            '/reports/resident-charts',
            '/reports/staff-charts',
            '/reports/pharmacy',
        ],
    },
];

export default function ReportsSectionLayout() {
    const { data: currentUser } = useQuery(currentUserQueryOptions);
    const isCaregiver = isCaregiverRole(currentUser?.role);

    // Caregivers see all report tabs EXCEPT Administrative (staff charts, pharmacy, full resident charts).
    // They can still see Clinical and Operations reports, scoped to their branch by the backend.
    const tabs = isCaregiver
        ? ALL_TABS.filter(t => !t.adminOnly)
        : ALL_TABS;

    return (
        <SectionLayout title="Reports" tabs={tabs} />
    );
}
