import React, { useState, useEffect } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api from '../services/api';
import { 
    Clock, 
    User, 
    Users, 
    MapPin, 
    Calendar, 
    AlertCircle,
    CheckCircle,
    XCircle,
    RefreshCw,
    TrendingUp,
    Timer,
    ArrowRight
} from 'lucide-react';
import SectionCard from '../components/SectionCard';
import EmptyState from '../components/ui/EmptyState';

// Helper function to calculate time difference in minutes
const getTimeDifference = (startTime) => {
    if (!startTime) return 0;
    const start = new Date(startTime);
    const now = new Date();
    return Math.floor((now - start) / (1000 * 60)); // Return minutes
};

// Helper function to format duration
const formatDuration = (minutes) => {
    if (minutes < 60) {
        return `${minutes}m`;
    }
    const hours = Math.floor(minutes / 60);
    const mins = minutes % 60;
    return mins > 0 ? `${hours}h ${mins}m` : `${hours}h`;
};

// Progress bar component
const ProgressBar = ({ value, max, color = 'var(--theme-primary)', label }) => {
    const percentage = max > 0 ? Math.min((value / max) * 100, 100) : 0;
    
    return (
        <div className="w-full">
            {label && (
                <div className="flex justify-between items-center mb-1">
                    <span className="text-xs font-medium text-gray-600">{label}</span>
                    <span className="text-xs font-semibold" style={{ color }}>{formatDuration(value)}</span>
                </div>
            )}
            <div className="w-full h-2 bg-gray-200 rounded-full overflow-hidden">
                <div
                    className="h-full rounded-full transition-all duration-300"
                    style={{
                        width: `${percentage}%`,
                        backgroundColor: color,
                    }}
                />
            </div>
        </div>
    );
};

export default function CheckInDashboard() {
    const queryClient = useQueryClient();
    const [refreshInterval, setRefreshInterval] = useState(30000); // 30 seconds

    // Fetch active staff clock-ins
    const { data: activeClockIns, isLoading: clockInsLoading } = useQuery({
        queryKey: ['staff-clock-ins-active'],
        queryFn: async () => {
            const response = await api.get('/staff/clock-ins', {
                params: { 
                    is_active: true,
                    per_page: 100 
                }
            });
            // Handle paginated response
            return response.data?.data || response.data || [];
        },
        refetchInterval: refreshInterval,
        staleTime: 10000,
    });

    // Fetch active resident sign-outs
    const { data: activeSignOuts, isLoading: signOutsLoading } = useQuery({
        queryKey: ['residents-sign-outs-active'],
        queryFn: async () => {
            const response = await api.get('/residents/sign-outs/active', {
                params: { per_page: 100 }
            });
            // Handle paginated response
            return response.data?.data || response.data || [];
        },
        refetchInterval: refreshInterval,
        staleTime: 10000,
    });

    // Fetch active visitors
    const { data: activeVisitors, isLoading: visitorsLoading } = useQuery({
        queryKey: ['visitors-active'],
        queryFn: async () => {
            const response = await api.get('/visitors/active');
            // Handle paginated response
            return response.data?.data || response.data || [];
        },
        refetchInterval: refreshInterval,
        staleTime: 10000,
    });

    // Calculate stats
    const stats = React.useMemo(() => {
        const clockIns = activeClockIns || [];
        const signOuts = activeSignOuts || [];
        const visitors = activeVisitors || [];

        return {
            totalStaff: clockIns.length,
            totalResidents: signOuts.length,
            totalVisitors: visitors.length,
            overdueResidents: signOuts.filter(so => {
                if (!so.expected_return_at) return false;
                return new Date(so.expected_return_at) < new Date();
            }).length,
        };
    }, [activeClockIns, activeSignOuts, activeVisitors]);

    // Auto-refresh time calculations every minute
    useEffect(() => {
        const interval = setInterval(() => {
            queryClient.invalidateQueries(['staff-clock-ins-active']);
            queryClient.invalidateQueries(['residents-sign-outs-active']);
            queryClient.invalidateQueries(['visitors-active']);
        }, 60000); // Refresh every minute

        return () => clearInterval(interval);
    }, [queryClient]);

    const handleRefresh = () => {
        queryClient.invalidateQueries(['staff-clock-ins-active']);
        queryClient.invalidateQueries(['residents-sign-outs-active']);
        queryClient.invalidateQueries(['visitors-active']);
    };

    const isLoading = clockInsLoading || signOutsLoading || visitorsLoading;

    return (
        <div className="space-y-6">
            {/* Header */}
            <header 
                className="rounded-3xl p-6 text-white shadow-lg" 
                style={{ 
                    background: `linear-gradient(to right, var(--theme-primary), var(--theme-primary-light), var(--theme-primary))`
                }}
            >
                <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <div>
                        <p className="text-sm font-medium uppercase tracking-wide" style={{ color: 'var(--theme-text-on-primary)' }}>
                            Check-In/Check-Out Dashboard
                        </p>
                        <h1 className="text-3xl font-semibold">Activity Monitor</h1>
                        <p className="mt-2 max-w-2xl text-sm" style={{ color: 'var(--theme-text-on-primary)' }}>
                            Real-time tracking of staff clock-ins, resident sign-outs, and visitor check-ins
                        </p>
                    </div>
                    <button
                        onClick={handleRefresh}
                        className="inline-flex items-center gap-2 rounded-2xl bg-white/20 px-5 py-3 text-sm font-semibold shadow-inner transition hover:bg-white/25"
                        style={{ color: 'var(--theme-text-on-primary)' }}
                    >
                        <RefreshCw className="h-4 w-4" />
                        Refresh
                    </button>
                </div>
            </header>

            {/* Stats Cards */}
            <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                <SectionCard>
                    <div className="flex items-center justify-between">
                        <div>
                            <p className="text-sm text-gray-600">Staff Clocked In</p>
                            <p className="text-3xl font-bold mt-1" style={{ color: 'var(--theme-primary)' }}>
                                {stats.totalStaff}
                            </p>
                        </div>
                        <div className="w-12 h-12 rounded-full flex items-center justify-center" style={{ backgroundColor: 'var(--theme-primary-bg)' }}>
                            <Clock className="w-6 h-6" style={{ color: 'var(--theme-primary)' }} />
                        </div>
                    </div>
                </SectionCard>

                <SectionCard>
                    <div className="flex items-center justify-between">
                        <div>
                            <p className="text-sm text-gray-600">Residents Out</p>
                            <p className="text-3xl font-bold mt-1" style={{ color: 'var(--theme-primary)' }}>
                                {stats.totalResidents}
                            </p>
                        </div>
                        <div className="w-12 h-12 rounded-full flex items-center justify-center" style={{ backgroundColor: 'var(--theme-primary-bg)' }}>
                            <Users className="w-6 h-6" style={{ color: 'var(--theme-primary)' }} />
                        </div>
                    </div>
                </SectionCard>

                <SectionCard>
                    <div className="flex items-center justify-between">
                        <div>
                            <p className="text-sm text-gray-600">Active Visitors</p>
                            <p className="text-3xl font-bold mt-1" style={{ color: 'var(--theme-primary)' }}>
                                {stats.totalVisitors}
                            </p>
                        </div>
                        <div className="w-12 h-12 rounded-full flex items-center justify-center" style={{ backgroundColor: 'var(--theme-primary-bg)' }}>
                            <User className="w-6 h-6" style={{ color: 'var(--theme-primary)' }} />
                        </div>
                    </div>
                </SectionCard>

                <SectionCard>
                    <div className="flex items-center justify-between">
                        <div>
                            <p className="text-sm text-gray-600">Overdue Returns</p>
                            <p className="text-3xl font-bold mt-1 text-red-600">
                                {stats.overdueResidents}
                            </p>
                        </div>
                        <div className="w-12 h-12 rounded-full bg-red-100 flex items-center justify-center">
                            <AlertCircle className="w-6 h-6 text-red-600" />
                        </div>
                    </div>
                </SectionCard>
            </div>

            {/* Staff Clock-Ins Section */}
            <SectionCard>
                <div className="flex items-center justify-between mb-6">
                    <div className="flex items-center gap-3">
                        <div className="w-10 h-10 rounded-xl flex items-center justify-center" style={{ backgroundColor: 'var(--theme-primary-bg)' }}>
                            <Clock className="w-5 h-5" style={{ color: 'var(--theme-primary)' }} />
                        </div>
                        <div>
                            <h2 className="text-xl font-semibold text-gray-900">Staff Clocked In</h2>
                            <p className="text-sm text-gray-600">Active staff members currently on duty</p>
                        </div>
                    </div>
                    <span className="px-3 py-1 rounded-full text-sm font-semibold" style={{ backgroundColor: 'var(--theme-primary-bg)', color: 'var(--theme-primary)' }}>
                        {activeClockIns?.length || 0}
                    </span>
                </div>

                {isLoading ? (
                    <div className="text-center py-12">
                        <div className="animate-spin rounded-full h-8 w-8 border-b-2 mx-auto" style={{ borderColor: 'var(--theme-primary)' }}></div>
                        <p className="text-gray-600 mt-4">Loading...</p>
                    </div>
                ) : !activeClockIns || activeClockIns.length === 0 ? (
                    <EmptyState
                        icon={Clock}
                        title="No Staff Clocked In"
                        description="No staff members are currently clocked in."
                    />
                ) : (
                    <div className="space-y-4">
                        {activeClockIns.map((clockIn) => {
                            const minutesLoggedIn = getTimeDifference(clockIn.clock_in_at);
                            const maxMinutes = 8 * 60; // 8 hours max for progress bar
                            
                            return (
                                <div 
                                    key={clockIn.id} 
                                    className="p-5 rounded-xl border border-gray-200 bg-white hover:shadow-md transition-shadow"
                                >
                                    <div className="flex items-start justify-between gap-4">
                                        <div className="flex-1">
                                            <div className="flex items-center gap-3 mb-2">
                                                <div className="w-10 h-10 rounded-full flex items-center justify-center" style={{ backgroundColor: 'var(--theme-primary-bg)' }}>
                                                    <User className="w-5 h-5" style={{ color: 'var(--theme-primary)' }} />
                                                </div>
                                                <div>
                                                    <h3 className="font-semibold text-gray-900">
                                                        {clockIn.staff?.name || 'Unknown Staff'}
                                                    </h3>
                                                    <p className="text-sm text-gray-600">
                                                        {clockIn.branch?.name || 'No Branch'}
                                                    </p>
                                                </div>
                                            </div>
                                            
                                            <div className="mt-3">
                                                <ProgressBar
                                                    value={minutesLoggedIn}
                                                    max={maxMinutes}
                                                    color="var(--theme-primary)"
                                                    label={`Logged in for ${formatDuration(minutesLoggedIn)}`}
                                                />
                                            </div>
                                            
                                            <div className="mt-3 flex items-center gap-4 text-xs text-gray-500">
                                                <span className="flex items-center gap-1">
                                                    <Clock className="w-3 h-3" />
                                                    Clocked in: {new Date(clockIn.clock_in_at).toLocaleTimeString()}
                                                </span>
                                                {clockIn.clock_method && (
                                                    <span className="px-2 py-1 rounded bg-gray-100 text-gray-700">
                                                        {clockIn.clock_method === 'public' ? 'Public' : 'Authenticated'}
                                                    </span>
                                                )}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                )}
            </SectionCard>

            {/* Resident Sign-Outs Section */}
            <SectionCard>
                <div className="flex items-center justify-between mb-6">
                    <div className="flex items-center gap-3">
                        <div className="w-10 h-10 rounded-xl flex items-center justify-center" style={{ backgroundColor: 'var(--theme-primary-bg)' }}>
                            <Users className="w-5 h-5" style={{ color: 'var(--theme-primary)' }} />
                        </div>
                        <div>
                            <h2 className="text-xl font-semibold text-gray-900">Residents Out</h2>
                            <p className="text-sm text-gray-600">Residents currently signed out</p>
                        </div>
                    </div>
                    <span className="px-3 py-1 rounded-full text-sm font-semibold" style={{ backgroundColor: 'var(--theme-primary-bg)', color: 'var(--theme-primary)' }}>
                        {activeSignOuts?.length || 0}
                    </span>
                </div>

                {isLoading ? (
                    <div className="text-center py-12">
                        <div className="animate-spin rounded-full h-8 w-8 border-b-2 mx-auto" style={{ borderColor: 'var(--theme-primary)' }}></div>
                        <p className="text-gray-600 mt-4">Loading...</p>
                    </div>
                ) : !activeSignOuts || activeSignOuts.length === 0 ? (
                    <EmptyState
                        icon={Users}
                        title="No Residents Out"
                        description="All residents are currently in the facility."
                    />
                ) : (
                    <div className="space-y-4">
                        {activeSignOuts.map((signOut) => {
                            const minutesOut = getTimeDifference(signOut.sign_out_at);
                            const expectedReturn = signOut.expected_return_at ? new Date(signOut.expected_return_at) : null;
                            const now = new Date();
                            const isOverdue = expectedReturn && now > expectedReturn;
                            const minutesUntilReturn = expectedReturn 
                                ? Math.max(0, Math.floor((expectedReturn - now) / (1000 * 60)))
                                : null;
                            
                            return (
                                <div 
                                    key={signOut.id} 
                                    className={`p-5 rounded-xl border-2 transition-shadow ${
                                        isOverdue 
                                            ? 'border-red-300 bg-red-50' 
                                            : 'border-gray-200 bg-white hover:shadow-md'
                                    }`}
                                >
                                    <div className="flex items-start justify-between gap-4">
                                        <div className="flex-1">
                                            <div className="flex items-center gap-3 mb-2">
                                                <div className="w-10 h-10 rounded-full flex items-center justify-center" style={{ backgroundColor: 'var(--theme-primary-bg)' }}>
                                                    <Users className="w-5 h-5" style={{ color: 'var(--theme-primary)' }} />
                                                </div>
                                                <div className="flex-1">
                                                    <div className="flex items-center gap-2">
                                                        <h3 className="font-semibold text-gray-900">
                                                            {signOut.resident?.name || 'Unknown Resident'}
                                                        </h3>
                                                        {isOverdue && (
                                                            <span className="px-2 py-1 rounded-full text-xs font-semibold bg-red-100 text-red-800 flex items-center gap-1">
                                                                <AlertCircle className="w-3 h-3" />
                                                                Overdue
                                                            </span>
                                                        )}
                                                    </div>
                                                    <p className="text-sm text-gray-600">
                                                        {signOut.destination || 'No destination'}
                                                    </p>
                                                </div>
                                            </div>
                                            
                                            <div className="mt-3">
                                                {expectedReturn ? (
                                                    <ProgressBar
                                                        value={minutesOut}
                                                        max={minutesOut + minutesUntilReturn}
                                                        color={isOverdue ? '#EF4444' : 'var(--theme-primary)'}
                                                        label={
                                                            isOverdue 
                                                                ? `Out for ${formatDuration(minutesOut)} (Overdue by ${formatDuration(Math.abs(minutesUntilReturn))})`
                                                                : `Out for ${formatDuration(minutesOut)} • Returns in ${formatDuration(minutesUntilReturn)}`
                                                        }
                                                    />
                                                ) : (
                                                    <ProgressBar
                                                        value={minutesOut}
                                                        max={minutesOut + 60} // Default 1 hour buffer
                                                        color="var(--theme-primary)"
                                                        label={`Out for ${formatDuration(minutesOut)}`}
                                                    />
                                                )}
                                            </div>
                                            
                                            <div className="mt-3 flex flex-wrap items-center gap-4 text-xs text-gray-500">
                                                <span className="flex items-center gap-1">
                                                    <Calendar className="w-3 h-3" />
                                                    Signed out: {new Date(signOut.sign_out_at).toLocaleString()}
                                                </span>
                                                {expectedReturn && (
                                                    <span className={`flex items-center gap-1 ${isOverdue ? 'text-red-600 font-semibold' : ''}`}>
                                                        <ArrowRight className="w-3 h-3" />
                                                        Expected: {new Date(expectedReturn).toLocaleString()}
                                                    </span>
                                                )}
                                                {signOut.purpose && (
                                                    <span className="px-2 py-1 rounded bg-gray-100 text-gray-700">
                                                        {signOut.purpose}
                                                    </span>
                                                )}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                )}
            </SectionCard>

            {/* Active Visitors Section */}
            <SectionCard>
                <div className="flex items-center justify-between mb-6">
                    <div className="flex items-center gap-3">
                        <div className="w-10 h-10 rounded-xl flex items-center justify-center" style={{ backgroundColor: 'var(--theme-primary-bg)' }}>
                            <User className="w-5 h-5" style={{ color: 'var(--theme-primary)' }} />
                        </div>
                        <div>
                            <h2 className="text-xl font-semibold text-gray-900">Active Visitors</h2>
                            <p className="text-sm text-gray-600">Visitors currently checked in</p>
                        </div>
                    </div>
                    <span className="px-3 py-1 rounded-full text-sm font-semibold" style={{ backgroundColor: 'var(--theme-primary-bg)', color: 'var(--theme-primary)' }}>
                        {activeVisitors?.length || 0}
                    </span>
                </div>

                {isLoading ? (
                    <div className="text-center py-12">
                        <div className="animate-spin rounded-full h-8 w-8 border-b-2 mx-auto" style={{ borderColor: 'var(--theme-primary)' }}></div>
                        <p className="text-gray-600 mt-4">Loading...</p>
                    </div>
                ) : !activeVisitors || activeVisitors.length === 0 ? (
                    <EmptyState
                        icon={User}
                        title="No Active Visitors"
                        description="No visitors are currently checked in."
                    />
                ) : (
                    <div className="space-y-4">
                        {activeVisitors.map((visitor) => {
                            const minutesCheckedIn = getTimeDifference(visitor.check_in_at);
                            const expectedDuration = visitor.expected_duration_minutes || 60; // Default 1 hour
                            const isOverdue = minutesCheckedIn > expectedDuration;
                            
                            return (
                                <div 
                                    key={visitor.id} 
                                    className={`p-5 rounded-xl border-2 transition-shadow ${
                                        isOverdue 
                                            ? 'border-amber-300 bg-amber-50' 
                                            : 'border-gray-200 bg-white hover:shadow-md'
                                    }`}
                                >
                                    <div className="flex items-start justify-between gap-4">
                                        <div className="flex-1">
                                            <div className="flex items-center gap-3 mb-2">
                                                <div className="w-10 h-10 rounded-full flex items-center justify-center" style={{ backgroundColor: 'var(--theme-primary-bg)' }}>
                                                    <User className="w-5 h-5" style={{ color: 'var(--theme-primary)' }} />
                                                </div>
                                                <div className="flex-1">
                                                    <div className="flex items-center gap-2">
                                                        <h3 className="font-semibold text-gray-900">
                                                            {visitor.first_name} {visitor.last_name}
                                                        </h3>
                                                        {isOverdue && (
                                                            <span className="px-2 py-1 rounded-full text-xs font-semibold bg-amber-100 text-amber-800">
                                                                Overdue
                                                            </span>
                                                        )}
                                                    </div>
                                                    <p className="text-sm text-gray-600">
                                                        Visiting: {visitor.visiting_resident?.name || visitor.visiting_staff?.name || 'N/A'}
                                                    </p>
                                                    <p className="text-xs text-gray-500 mt-1">
                                                        {visitor.visit_purpose}
                                                    </p>
                                                </div>
                                            </div>
                                            
                                            <div className="mt-3">
                                                <ProgressBar
                                                    value={minutesCheckedIn}
                                                    max={expectedDuration}
                                                    color={isOverdue ? '#F59E0B' : 'var(--theme-primary)'}
                                                    label={`Checked in for ${formatDuration(minutesCheckedIn)}`}
                                                />
                                            </div>
                                            
                                            <div className="mt-3 flex flex-wrap items-center gap-4 text-xs text-gray-500">
                                                <span className="flex items-center gap-1">
                                                    <Clock className="w-3 h-3" />
                                                    Checked in: {new Date(visitor.check_in_at).toLocaleTimeString()}
                                                </span>
                                                {visitor.branch && (
                                                    <span className="flex items-center gap-1">
                                                        <MapPin className="w-3 h-3" />
                                                        {visitor.branch.name}
                                                    </span>
                                                )}
                                                {visitor.expected_duration_minutes && (
                                                    <span className="px-2 py-1 rounded bg-gray-100 text-gray-700">
                                                        Expected: {formatDuration(visitor.expected_duration_minutes)}
                                                    </span>
                                                )}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                )}
            </SectionCard>
        </div>
    );
}

