import React from 'react';
import { useQuery } from '@tanstack/react-query';
import api from '../services/api';
import { Users, Calendar, Activity, UserCheck, ClipboardList, AlertCircle } from 'lucide-react';

export default function Dashboard() {
    const { data: stats, isLoading, error } = useQuery({
        queryKey: ['dashboard-stats'],
        queryFn: async () => {
            try {
                const response = await api.get('/dashboard/stats');
                return response.data;
            } catch (err) {
                console.error('Dashboard API error:', err);
                return {
                    total_residents: 0,
                    today_appointments: 0,
                    today_vitals: 0,
                    total_staff: 0,
                };
            }
        },
        retry: false,
    });

    // Check if caregiver dashboard or admin dashboard
    const isCaregiver = stats?.user_type === 'caregiver';
    
    // Define different stat cards based on user type
    const statCards = isCaregiver ? [
        {
            title: 'My Residents',
            value: stats?.assigned_residents || 0,
            icon: Users,
            color: 'bg-blue-500',
            description: 'Assigned to me',
        },
        {
            title: "Today's Appointments",
            value: stats?.todays_appointments || 0,
            icon: Calendar,
            color: 'bg-green-500',
            description: 'Scheduled meetings',
        },
        {
            title: 'Pending Assessments',
            value: stats?.pending_assessments || 0,
            icon: ClipboardList,
            color: 'bg-yellow-500',
            description: 'Awaiting completion',
        },
        {
            title: 'Vitals Recorded',
            value: stats?.today_vitals || 0,
            icon: Activity,
            color: 'bg-purple-500',
            description: 'Today',
        },
        {
            title: 'Leave Requests',
            value: stats?.pending_leave_requests || 0,
            icon: AlertCircle,
            color: 'bg-indigo-500',
            description: 'Pending approval',
        },
        {
            title: 'Weekly Appointments',
            value: stats?.week_appointments || 0,
            icon: Calendar,
            color: 'bg-emerald-500',
            description: 'Next 7 days',
        },
    ] : [
        {
            title: 'Total Residents',
            value: stats?.total_residents || 0,
            icon: Users,
            color: 'bg-blue-500',
        },
        {
            title: "Today's Appointments",
            value: stats?.today_appointments || 0,
            icon: Calendar,
            color: 'bg-green-500',
        },
        {
            title: 'Today Vitals',
            value: stats?.today_vitals || 0,
            icon: Activity,
            color: 'bg-purple-500',
        },
        {
            title: 'Total Staff',
            value: stats?.total_staff || 0,
            icon: UserCheck,
            color: 'bg-orange-500',
        },
    ];

    return (
        <div style={{ padding: '20px' }}>
            <h1 className="text-3xl font-bold text-gray-900 mb-6">
                {isCaregiver ? 'Caregiver Dashboard' : 'Dashboard'}
            </h1>
            
            {error && (
                <div className="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-6">
                    <p className="text-yellow-800 text-sm">
                        Note: API connection failed. Showing default values. Please check authentication.
                    </p>
                </div>
            )}
            
            {isLoading && (
                <div className="text-center py-12">
                    <div className="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
                    <p className="mt-4 text-gray-600">Loading dashboard data...</p>
                </div>
            )}
            
            <div className={`grid grid-cols-1 md:grid-cols-2 ${isCaregiver ? 'lg:grid-cols-3' : 'lg:grid-cols-4'} gap-6`}>
                {statCards.map((card, index) => {
                    const Icon = card.icon;
                    return (
                        <div key={index} className="bg-white rounded-lg shadow p-6">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-gray-600 text-sm font-medium">{card.title}</p>
                                    <p className="text-3xl font-bold text-gray-900 mt-2">{card.value}</p>
                                    {card.description && (
                                        <p className="text-gray-500 text-xs mt-1">{card.description}</p>
                                    )}
                                </div>
                                <div className={`${card.color} p-3 rounded-lg`}>
                                    <Icon className="w-6 h-6 text-white" />
                                </div>
                            </div>
                        </div>
                    );
                })}
            </div>
        </div>
    );
}

