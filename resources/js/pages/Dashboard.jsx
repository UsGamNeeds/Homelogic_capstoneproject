import React from 'react';
import { useQuery } from '@tanstack/react-query';
import { useNavigate } from 'react-router-dom';
import api from '../services/api';
import { 
    Users, Calendar, Activity, UserCheck, ClipboardList, AlertCircle, 
    TrendingUp, Clock, CheckCircle, FileText, Heart, Pill, Moon,
    ArrowRight, Sparkles
} from 'lucide-react';

export default function Dashboard() {
    const navigate = useNavigate();
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

    const isCaregiver = stats?.user_type === 'caregiver';
    const currentHour = new Date().getHours();
    const greeting = currentHour < 12 ? 'Good Morning' : currentHour < 18 ? 'Good Afternoon' : 'Good Evening';
    
    // Define stat cards based on user type with gradients and modern styling
    const statCards = isCaregiver ? [
        {
            title: 'My Residents',
            value: stats?.assigned_residents || 0,
            icon: Users,
            gradient: 'from-blue-500 to-blue-600',
            iconBg: 'bg-blue-100',
            iconColor: 'text-blue-600',
            description: 'Assigned to me',
            link: '/administration/residents',
            trend: 'positive'
        },
        {
            title: "Today's Appointments",
            value: stats?.todays_appointments || 0,
            icon: Calendar,
            gradient: 'from-emerald-500 to-emerald-600',
            iconBg: 'bg-emerald-100',
            iconColor: 'text-emerald-600',
            description: 'Scheduled meetings',
            link: '/appointments',
            trend: 'positive'
        },
        {
            title: 'Pending Assessments',
            value: stats?.pending_assessments || 0,
            icon: ClipboardList,
            gradient: 'from-amber-500 to-amber-600',
            iconBg: 'bg-amber-100',
            iconColor: 'text-amber-600',
            description: 'Awaiting completion',
            link: '/assessments',
            trend: stats?.pending_assessments > 0 ? 'warning' : 'positive'
        },
        {
            title: 'Vitals Recorded',
            value: stats?.today_vitals || 0,
            icon: Activity,
            gradient: 'from-purple-500 to-purple-600',
            iconBg: 'bg-purple-100',
            iconColor: 'text-purple-600',
            description: 'Today',
            link: '/vitals',
            trend: 'positive'
        },
        {
            title: 'Leave Requests',
            value: stats?.pending_leave_requests || 0,
            icon: AlertCircle,
            gradient: 'from-indigo-500 to-indigo-600',
            iconBg: 'bg-indigo-100',
            iconColor: 'text-indigo-600',
            description: 'Pending approval',
            link: '/administration/leave-requests',
            trend: stats?.pending_leave_requests > 0 ? 'warning' : 'positive'
        },
        {
            title: 'Weekly Appointments',
            value: stats?.week_appointments || 0,
            icon: Calendar,
            gradient: 'from-teal-500 to-teal-600',
            iconBg: 'bg-teal-100',
            iconColor: 'text-teal-600',
            description: 'Next 7 days',
            link: '/appointments',
            trend: 'positive'
        },
    ] : [
        {
            title: 'Total Residents',
            value: stats?.total_residents || 0,
            icon: Users,
            gradient: 'from-blue-500 to-blue-600',
            iconBg: 'bg-blue-100',
            iconColor: 'text-blue-600',
            link: '/administration/residents',
        },
        {
            title: "Today's Appointments",
            value: stats?.today_appointments || 0,
            icon: Calendar,
            gradient: 'from-emerald-500 to-emerald-600',
            iconBg: 'bg-emerald-100',
            iconColor: 'text-emerald-600',
            link: '/appointments',
        },
        {
            title: 'Today Vitals',
            value: stats?.today_vitals || 0,
            icon: Activity,
            gradient: 'from-purple-500 to-purple-600',
            iconBg: 'bg-purple-100',
            iconColor: 'text-purple-600',
            link: '/vitals',
        },
        {
            title: 'Total Staff',
            value: stats?.total_staff || 0,
            icon: UserCheck,
            gradient: 'from-orange-500 to-orange-600',
            iconBg: 'bg-orange-100',
            iconColor: 'text-orange-600',
            link: '/administration/users',
        },
    ];

    return (
        <div className="min-h-screen bg-gradient-to-br from-[#F5F5DC] to-[#E6E6D4]">
            {/* Hero Section */}
            <div className="relative overflow-hidden bg-gradient-to-r from-[#2D5016] via-[#4a7a2a] to-[#2D5016]">
                <div className="absolute inset-0 opacity-20">
                    <div className="absolute inset-0" style={{
                        backgroundImage: `url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.05'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E")`
                    }}></div>
                </div>
                <div className="relative px-6 py-12">
                    <div className="max-w-7xl mx-auto">
                        <div className="flex items-center space-x-3 mb-4">
                            <Sparkles className="w-8 h-8 text-white animate-pulse" />
                            <h1 className="text-4xl font-bold text-white">
                                {greeting}! 👋
                            </h1>
                        </div>
                        <p className="text-xl text-green-100 mb-2">
                            {isCaregiver ? 'Welcome to your Care Dashboard' : 'Welcome to the Admin Dashboard'}
                        </p>
                        <p className="text-green-50">
                            {isCaregiver 
                                ? 'Here\'s an overview of your care activities and priorities for today.'
                                : 'Monitor and manage your care facility operations from here.'}
                        </p>
                    </div>
                </div>
            </div>

            {/* Main Content */}
            <div className="max-w-7xl mx-auto px-6 py-8">
                {error && (
                    <div className="mb-6 bg-white rounded-xl shadow-lg border-l-4 border-amber-500 p-4">
                        <div className="flex items-center space-x-3">
                            <AlertCircle className="w-5 h-5 text-amber-600" />
                            <p className="text-amber-800 text-sm">
                                Note: API connection failed. Showing default values. Please check authentication.
                            </p>
                        </div>
                    </div>
                )}
                
                {isLoading && (
                    <div className="text-center py-20">
                        <div className="inline-block animate-spin rounded-full h-12 w-12 border-4 border-[#2D5016] border-t-transparent"></div>
                        <p className="mt-4 text-[#8B4513] text-lg font-medium">Loading dashboard data...</p>
                    </div>
                )}
                
                {!isLoading && (
                    <>
                        {/* Stat Cards Grid */}
                        <div className={`grid grid-cols-1 md:grid-cols-2 ${isCaregiver ? 'lg:grid-cols-3' : 'lg:grid-cols-4'} gap-6 mb-8`}>
                            {statCards.map((card, index) => {
                                const Icon = card.icon;
                                return (
                                    <div
                                        key={index}
                                        onClick={() => card.link && navigate(card.link)}
                                        className="group relative bg-white rounded-2xl shadow-md hover:shadow-xl transition-all duration-300 overflow-hidden cursor-pointer border border-gray-100"
                                    >
                                        {/* Gradient decoration */}
                                        <div className={`absolute top-0 left-0 right-0 h-1 bg-gradient-to-r ${card.gradient}`}></div>
                                        
                                        {/* Content */}
                                        <div className="p-6">
                                            <div className="flex items-start justify-between mb-4">
                                                <div className="flex-1">
                                                    <p className="text-[#8B4513] text-sm font-semibold uppercase tracking-wide mb-1">
                                                        {card.title}
                                                    </p>
                                                    <div className="flex items-baseline space-x-2">
                                                        <p className="text-4xl font-bold text-[#2D5016]">
                                                            {card.value}
                                                        </p>
                                                        {card.trend === 'warning' && (
                                                            <AlertCircle className="w-5 h-5 text-amber-500" />
                                                        )}
                                                    </div>
                                                    {card.description && (
                                                        <p className="text-gray-500 text-xs mt-2 flex items-center">
                                                            <Clock className="w-3 h-3 mr-1" />
                                                            {card.description}
                                                        </p>
                                                    )}
                                                </div>
                                                <div className={`${card.iconBg} p-3 rounded-xl group-hover:scale-110 transition-transform duration-300`}>
                                                    <Icon className={`w-6 h-6 ${card.iconColor}`} />
                                                </div>
                                            </div>
                                            
                                            {/* Hover effect */}
                                            {card.link && (
                                                <div className="flex items-center text-[#2D5016] text-sm font-medium opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                                                    <span>View details</span>
                                                    <ArrowRight className="w-4 h-4 ml-2 transform group-hover:translate-x-1 transition-transform" />
                                                </div>
                                            )}
                                        </div>
                                    </div>
                                );
                            })}
                        </div>

                        {/* Quick Actions Section */}
                        <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                            {/* Today's Tasks */}
                            <div className="bg-white rounded-2xl shadow-lg border border-gray-100 overflow-hidden">
                                <div className="bg-gradient-to-r from-[#2D5016] to-[#4a7a2a] px-6 py-4">
                                    <div className="flex items-center space-x-3">
                                        <CheckCircle className="w-6 h-6 text-white" />
                                        <h2 className="text-xl font-bold text-white">Quick Actions</h2>
                                    </div>
                                </div>
                                <div className="p-6">
                                    <div className="space-y-3">
                                        <button
                                            onClick={() => navigate('/assessments')}
                                            className="w-full flex items-center justify-between p-4 bg-gradient-to-r from-amber-50 to-amber-100 rounded-xl hover:shadow-md transition-all duration-300 group border border-amber-200"
                                        >
                                            <div className="flex items-center space-x-3">
                                                <ClipboardList className="w-5 h-5 text-amber-600" />
                                                <span className="text-gray-900 font-medium">Complete Assessments</span>
                                            </div>
                                            <ArrowRight className="w-5 h-5 text-amber-600 transform group-hover:translate-x-1 transition-transform" />
                                        </button>
                                        
                                        <button
                                            onClick={() => navigate('/vitals')}
                                            className="w-full flex items-center justify-between p-4 bg-gradient-to-r from-purple-50 to-purple-100 rounded-xl hover:shadow-md transition-all duration-300 group border border-purple-200"
                                        >
                                            <div className="flex items-center space-x-3">
                                                <Heart className="w-5 h-5 text-purple-600" />
                                                <span className="text-gray-900 font-medium">Record Vital Signs</span>
                                            </div>
                                            <ArrowRight className="w-5 h-5 text-purple-600 transform group-hover:translate-x-1 transition-transform" />
                                        </button>
                                        
                                        <button
                                            onClick={() => navigate('/medications')}
                                            className="w-full flex items-center justify-between p-4 bg-gradient-to-r from-red-50 to-red-100 rounded-xl hover:shadow-md transition-all duration-300 group border border-red-200"
                                        >
                                            <div className="flex items-center space-x-3">
                                                <Pill className="w-5 h-5 text-red-600" />
                                                <span className="text-gray-900 font-medium">Administer Medications</span>
                                            </div>
                                            <ArrowRight className="w-5 h-5 text-red-600 transform group-hover:translate-x-1 transition-transform" />
                                        </button>
                                        
                                        <button
                                            onClick={() => navigate('/sleep')}
                                            className="w-full flex items-center justify-between p-4 bg-gradient-to-r from-indigo-50 to-indigo-100 rounded-xl hover:shadow-md transition-all duration-300 group border border-indigo-200"
                                        >
                                            <div className="flex items-center space-x-3">
                                                <Moon className="w-5 h-5 text-indigo-600" />
                                                <span className="text-gray-900 font-medium">Log Sleep Records</span>
                                            </div>
                                            <ArrowRight className="w-5 h-5 text-indigo-600 transform group-hover:translate-x-1 transition-transform" />
                                        </button>
                                    </div>
                                </div>
                            </div>

                            {/* Recent Activity */}
                            <div className="bg-white rounded-2xl shadow-lg border border-gray-100 overflow-hidden">
                                <div className="bg-gradient-to-r from-[#2D5016] to-[#4a7a2a] px-6 py-4">
                                    <div className="flex items-center space-x-3">
                                        <TrendingUp className="w-6 h-6 text-white" />
                                        <h2 className="text-xl font-bold text-white">System Overview</h2>
                                    </div>
                                </div>
                                <div className="p-6">
                                    <div className="space-y-4">
                                        <div className="flex items-center justify-between p-4 bg-blue-50 rounded-xl border border-blue-200">
                                            <div className="flex items-center space-x-3">
                                                <div className="p-2 bg-blue-500 rounded-lg">
                                                    <Users className="w-5 h-5 text-white" />
                                                </div>
                                                <div>
                                                    <p className="text-sm text-gray-600">Total Residents</p>
                                                    <p className="text-2xl font-bold text-[#2D5016]">
                                                        {stats?.total_residents || 0}
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div className="flex items-center justify-between p-4 bg-emerald-50 rounded-xl border border-emerald-200">
                                            <div className="flex items-center space-x-3">
                                                <div className="p-2 bg-emerald-500 rounded-lg">
                                                    <Calendar className="w-5 h-5 text-white" />
                                                </div>
                                                <div>
                                                    <p className="text-sm text-gray-600">Upcoming Appointments</p>
                                                    <p className="text-2xl font-bold text-[#2D5016]">
                                                        {stats?.upcoming_appointments || stats?.week_appointments || 0}
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div className="flex items-center justify-between p-4 bg-orange-50 rounded-xl border border-orange-200">
                                            <div className="flex items-center space-x-3">
                                                <div className="p-2 bg-orange-500 rounded-lg">
                                                    <UserCheck className="w-5 h-5 text-white" />
                                                </div>
                                                <div>
                                                    <p className="text-sm text-gray-600">Active Staff</p>
                                                    <p className="text-2xl font-bold text-[#2D5016]">
                                                        {stats?.total_staff || 0}
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </>
                )}
            </div>
        </div>
    );
}
