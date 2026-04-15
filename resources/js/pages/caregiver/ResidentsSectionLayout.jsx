import React from 'react';
import {
    LayoutDashboard,
    Users,
    ClipboardList,
    Calendar,
    BarChart3,
    FileText,
} from 'lucide-react';
import SectionLayout from '../../components/SectionLayout';

const TABS = [
    { id: 'overview',     label: 'Overview',      icon: LayoutDashboard, path: '/residents' },
    { id: 'my-residents', label: 'Residents',     icon: Users,           path: '/my-residents' },
    { id: 'assessments',  label: 'Assessments',   icon: ClipboardList,   path: '/assessments' },
    {
        id: 'appointments',
        label: 'Appointments',
        icon: Calendar,
        path: '/appointments/dashboard',
        extraPaths: ['/appointments'],
    },
    {
        id: 'charts',
        label: 'Charts',
        icon: BarChart3,
        path: '/charts',
        extraPaths: ['/charts/resident'],
    },
    { id: 'notes',        label: 'Progress notes', icon: FileText,       path: '/t-logs' },
];

export default function ResidentsSectionLayout() {
    return (
        <SectionLayout title="Residents" tabs={TABS} />
    );
}
