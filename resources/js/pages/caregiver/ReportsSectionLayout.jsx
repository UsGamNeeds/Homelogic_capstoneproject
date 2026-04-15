import React from 'react';
import {
    LayoutDashboard,
    TrendingUp,
    Activity,
    Wrench,
    Building2,
} from 'lucide-react';
import SectionLayout from '../../components/SectionLayout';

const TABS = [
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
        id: 'administrative',
        label: 'Administrative',
        icon: Building2,
        path: '/reports/charts',
        extraPaths: [
            '/reports/resident-charts',
            '/reports/staff-charts',
            '/reports/pharmacy',
        ],
    },
];

export default function ReportsSectionLayout() {
    return (
        <SectionLayout title="Reports" tabs={TABS} />
    );
}
