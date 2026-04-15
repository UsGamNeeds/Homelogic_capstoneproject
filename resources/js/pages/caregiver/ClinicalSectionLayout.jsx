import React from 'react';
import { useLocation } from 'react-router-dom';
import { ClipboardList, Heart, Moon, LayoutDashboard, Pill, Truck } from 'lucide-react';
import SectionLayout from '../../components/SectionLayout';

const TABS = [
    { id: 'overview',            label: 'Overview',            icon: LayoutDashboard, path: '/clinical' },
    { id: 'medication-history',  label: 'Medication history',  icon: ClipboardList,   path: '/medication-history' },
    { id: 'vitals',              label: 'Vitals',              icon: Heart,           path: '/vitals',             extraPaths: ['/view-vitals'] },
    { id: 'sleep',               label: 'Sleep',               icon: Moon,            path: '/sleep',              extraPaths: ['/sleep-patterns'] },
    { id: 'medications',         label: 'Medications',         icon: Pill,            path: '/medications' },
    { id: 'deliveries',          label: 'Deliveries',          icon: Truck,           path: '/medication-deliveries' },
];

export default function ClinicalSectionLayout() {
    const { pathname } = useLocation();
    const showTabBar = pathname !== '/clinical';

    return (
        <SectionLayout title="Clinical" tabs={TABS} showTabBar={showTabBar} />
    );
}
