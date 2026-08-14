import React from 'react';
import { ClipboardList, Heart, Moon, LayoutDashboard, Pill, Truck, Calendar, BarChart3, ClipboardCheck, Droplet } from 'lucide-react';
import SectionLayout from '../../components/SectionLayout';

// Order mirrors the per-resident hub's tabs (Medications, Vitals, Assessments,
// Appointments, Charts) so moving between a resident and the house-wide view does not
// reshuffle the same labels. Clinical-only tabs sit beside the tab they belong with:
// medication history and deliveries after Medications, sleep after Vitals.
const TABS = [
    { id: 'overview',           label: 'Overview',           icon: LayoutDashboard,  path: '/clinical' },
    { id: 'medications',        label: 'Medications',        icon: Pill,             path: '/medications',         extraPaths: ['/medications/residents', '/medications/dashboard', '/medications/report'] },
    { id: 'medication-history', label: 'Medication history', icon: ClipboardList,    path: '/medication-history' },
    { id: 'deliveries',         label: 'Deliveries',         icon: Truck,            path: '/medication-deliveries' },
    { id: 'vitals',             label: 'Vitals',             icon: Heart,            path: '/vitals',              extraPaths: ['/view-vitals'] },
    { id: 'sleep',              label: 'Sleep',              icon: Moon,             path: '/sleep',               extraPaths: ['/sleep-patterns'] },
    { id: 'assessments',        label: 'Assessments',        icon: ClipboardCheck,   path: '/assessments' },
    {
        id: 'appointments',
        label: 'Appointments',
        icon: Calendar,
        path: '/appointments',
        extraPaths: ['/appointments/dashboard', '/appointments/create'],
    },
    {
        id: 'blood-glucose-reports',
        label: 'Blood Glucose',
        icon: Droplet,
        path: '/blood-glucose-reports',
    },
    {
        id: 'charts',
        label: 'Charts',
        icon: BarChart3,
        path: '/charts',
        extraPaths: ['/charts/resident'],
    },
];

export default function ClinicalSectionLayout() {
    // The tab bar renders on every clinical route, including the /clinical overview.
    // It used to be hidden there, which made the section's own navigation vanish on
    // the one page a user is most likely to land on first.
    return (
        <SectionLayout title="Clinical" tabs={TABS} />
    );
}
