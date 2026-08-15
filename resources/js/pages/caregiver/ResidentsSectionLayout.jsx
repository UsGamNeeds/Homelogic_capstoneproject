import React from 'react';
import {
    Users,
    FileText,
} from 'lucide-react';
import SectionLayout from '../../components/SectionLayout';

const TABS = [
    { id: 'my-residents', label: 'Residents',     icon: Users,           path: '/my-residents' },
    { id: 'notes',        label: 'T-Logs', icon: FileText,       path: '/t-logs' },
];

export default function ResidentsSectionLayout() {
    return (
        <SectionLayout title="Residents" tabs={TABS} showTabBar />
    );
}
