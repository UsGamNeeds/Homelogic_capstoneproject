import React from 'react';
import {
    Calendar, Clock, CheckCircle, AlertCircle,
    ChevronRight, Activity, Pill, User,
    MapPin, Phone, FileText, Sparkles, Heart, ClipboardList,
    AlertTriangle, Flame, ShoppingCart, ArrowRight
} from 'lucide-react';
import { useNavigate } from 'react-router-dom';
import SectionCard from '../SectionCard';
import { slideInUp, shouldAnimate } from '../../utils/animationPresets';

const ACTIONABLE_ICONS = {
    assessment: ClipboardList,
    appointment: Calendar,
    medication: Pill,
    fire_drill: Flame,
    inventory: ShoppingCart,
    leave_request: User,
};

const PRIORITY_STYLES = {
    urgent: {
        bar: 'bg-red-500',
        badge: 'bg-red-50 text-red-700 border border-red-200',
        icon: 'text-red-500',
        label: 'Urgent',
    },
    soon: {
        bar: 'bg-amber-400',
        badge: 'bg-amber-50 text-amber-700 border border-amber-200',
        icon: 'text-amber-500',
        label: 'Soon',
    },
    info: {
        bar: 'bg-blue-400',
        badge: 'bg-blue-50 text-blue-700 border border-blue-200',
        icon: 'text-blue-500',
        label: 'Info',
    },
};

export default function CaregiverDashboard({
    user,
    stats,
    todaysSchedule = [],
    upcomingEvents = [],
    actionableItems = [],
}) {
    const navigate = useNavigate();
    const currentHour = new Date().getHours();
    const greeting = currentHour < 12 ? 'Good Morning' : currentHour < 18 ? 'Good Afternoon' : 'Good Evening';

    // Group schedule by time status
    const getScheduleStatus = (timeStr, isCompleted = false) => {
        if (isCompleted) return 'past';
        if (!timeStr) return 'upcoming';
        const now = new Date();
        const [hours, minutes] = timeStr.split(':').map(Number);
        const scheduleTime = new Date();
        scheduleTime.setHours(hours, minutes, 0);

        const diff = (scheduleTime - now) / (1000 * 60); // diff in minutes

        if (diff < -60) return 'overdue';  // More than 60 mins ago and not completed
        if (diff < -30) return 'past';     // 30–60 mins ago
        if (diff >= -30 && diff <= 30) return 'current'; // Within 30 mins window
        return 'upcoming';
    };

    return (
        <div className="space-y-6">
            {/* Header Section */}
            <div
                className="bg-gradient-to-br from-[var(--theme-primary)] to-[var(--theme-primary-dark)] rounded-xl shadow-sm p-6 text-white"
                role="banner"
                aria-label="Dashboard greeting"
            >
                <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-bold mb-1">
                            {greeting}, {user?.first_name || 'Caregiver'} 👋
                        </h1>
                        <p className="text-white/80 text-sm">
                            {new Date().toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' })}
                            {user?.branch?.name ? ` · ${user.branch.name}` : ''}
                        </p>
                    </div>
                    <div className="flex items-center gap-3">
                        <div className="bg-white/20 backdrop-blur-sm px-4 py-2 rounded-lg flex items-center gap-2 border border-white/30">
                            <div className="w-2 h-2 rounded-full bg-green-400 animate-pulse"></div>
                            <span className="text-sm font-medium">On Shift</span>
                        </div>
                        <button
                            onClick={() => navigate('/appointments')}
                            className="bg-white text-[var(--theme-primary)] px-4 py-2 rounded-lg text-sm font-semibold transition-colors hover:bg-white/90 shadow-md"
                        >
                            View Calendar
                        </button>
                    </div>
                </div>
            </div>

            {/* Quick Stats Row */}
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <StatCard
                    title="My Residents"
                    value={stats?.assigned_residents || 0}
                    icon={User}
                    onClick={() => navigate('/my-residents')}
                />
                <StatCard
                    title="Appointments"
                    value={stats?.todays_appointments || 0}
                    icon={Calendar}
                    onClick={() => navigate('/appointments')}
                />
                <StatCard
                    title="Medications Due"
                    value={stats?.medication_reminders?.length || 0}
                    icon={Pill}
                    urgent={(stats?.medication_reminders?.length || 0) > 0}
                    onClick={() => navigate('/medications')}
                />
                <StatCard
                    title="Pending Tasks"
                    value={stats?.pending_assessments || 0}
                    icon={ClipboardList}
                    urgent={(stats?.pending_assessments || 0) > 0}
                    onClick={() => navigate('/assessments')}
                />
            </div>

            {/* Needs Attention — exception queue */}
            {actionableItems.length > 0 && (
                <NeedsAttentionCard items={actionableItems} navigate={navigate} />
            )}

            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                {/* Left Column: Today's Schedule */}
                <div className="lg:col-span-2" role="region" aria-label="Today's schedule">
                    <SectionCard
                        title="Today's Schedule"
                        headerRight={
                            <span className="text-sm text-gray-500 bg-gray-100 px-3 py-1 rounded-full">
                                {new Date().toLocaleDateString(undefined, { weekday: 'long', month: 'long', day: 'numeric' })}
                            </span>
                        }
                    >
                        {todaysSchedule.length > 0 ? (
                            <div className="space-y-0">
                                {todaysSchedule.map((item, index) => {
                                    const status = getScheduleStatus(item.time_24h, item.is_completed);
                                    const isLast = index === todaysSchedule.length - 1;

                                    return (
                                        <div key={item.id} className="relative pl-8 pb-6 group">
                                            {/* Timeline Line */}
                                            {!isLast && (
                                                <div className="absolute left-[11px] top-8 bottom-0 w-0.5 bg-gray-200 group-hover:bg-gray-300 transition-colors"></div>
                                            )}

                                            {/* Timeline Dot */}
                                            <div className={`absolute left-0 top-1.5 w-6 h-6 rounded-full border-2 flex items-center justify-center z-10 bg-white
                                                ${status === 'overdue' ? 'border-red-400 bg-red-50' :
                                                  status === 'current' ? 'border-[var(--theme-primary)] shadow-[0_0_0_4px_rgba(var(--theme-primary-rgb),0.2)]' :
                                                  status === 'past' ? 'border-gray-300 bg-gray-50' : 'border-[var(--theme-primary)]'}`}
                                            >
                                                {status === 'past' ? (
                                                    <div className="w-2.5 h-2.5 rounded-full bg-gray-300" />
                                                ) : status === 'overdue' ? (
                                                    <div className="w-2.5 h-2.5 rounded-full bg-red-400 animate-pulse" />
                                                ) : (
                                                    <div className={`w-2.5 h-2.5 rounded-full ${status === 'current' ? 'bg-[var(--theme-primary)] animate-pulse' : 'bg-[var(--theme-primary)]'}`} />
                                                )}
                                            </div>

                                            {/* Content Card */}
                                            <div className={`relative p-4 rounded-lg border transition-all duration-200 hover:shadow-md cursor-pointer
                                                ${status === 'overdue' ? 'bg-red-50 border-red-200' :
                                                  status === 'current' ? 'bg-[var(--theme-primary-bg-light)] border-[var(--theme-primary)]/20' :
                                                  status === 'past' ? 'bg-gray-50 border-gray-200 opacity-75' : 'bg-white border-gray-200 hover:border-[var(--theme-primary)]/30'}`}
                                                onClick={() => item.link && navigate(item.link)}
                                            >
                                                <div className="flex items-start justify-between gap-4">
                                                    <div className="flex-1">
                                                        <div className="flex items-center gap-2 mb-1">
                                                            <span className={`text-sm font-semibold ${status === 'overdue' ? 'text-red-600' : status === 'current' ? 'text-[var(--theme-primary)]' : 'text-gray-900'}`}>
                                                                {item.time}
                                                            </span>
                                                            {status === 'overdue' && (
                                                                <span className="px-1.5 py-0.5 rounded text-[10px] font-semibold uppercase tracking-wider bg-red-100 text-red-700">
                                                                    Overdue
                                                                </span>
                                                            )}
                                                            <span className={`px-2 py-0.5 rounded text-[10px] font-semibold uppercase tracking-wider
                                                                ${item.type === 'medication' ? 'bg-green-100 text-green-700' :
                                                                    item.type === 'appointment' ? 'bg-blue-100 text-blue-700' :
                                                                        item.type === 'vitals' ? 'bg-purple-100 text-purple-700' : 'bg-gray-100 text-gray-700'}`}
                                                            >
                                                                {item.category || item.type}
                                                            </span>
                                                        </div>
                                                        <h3 className="font-semibold text-gray-900">{item.title}</h3>
                                                        <p className="text-sm text-gray-600 mt-0.5 flex items-center gap-1.5">
                                                            <User className="w-3.5 h-3.5" />
                                                            {item.resident_name}
                                                        </p>
                                                        {item.location && (
                                                            <p className="text-xs text-gray-500 mt-1 flex items-center gap-1.5">
                                                                <MapPin className="w-3 h-3" />
                                                                {item.location}
                                                            </p>
                                                        )}
                                                    </div>

                                                    {item.link && (
                                                        <ChevronRight className="w-5 h-5 text-gray-400 group-hover:text-[var(--theme-primary)] transition-colors flex-shrink-0" />
                                                    )}
                                                </div>
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>
                        ) : (
                            <div className="flex flex-col items-center justify-center py-12 text-center text-gray-500">
                                <div className="w-16 h-16 bg-gray-50 rounded-full flex items-center justify-center mb-4">
                                    <Sparkles className="w-8 h-8 text-gray-300" />
                                </div>
                                <h3 className="text-lg font-medium text-gray-900">All Clear!</h3>
                                <p className="text-sm max-w-xs mx-auto mt-1">No scheduled tasks or appointments remaining for today.</p>
                            </div>
                        )}
                    </SectionCard>
                </div>

                {/* Right Column: Upcoming & Quick Actions */}
                <div className="space-y-6">
                    {/* Upcoming Events */}
                    <SectionCard
                        title="Upcoming Events"
                        actionLabel="View All"
                        onAction={() => navigate('/events')}
                    >
                        {upcomingEvents.length > 0 ? (
                            <div className="divide-y divide-gray-200">
                                {upcomingEvents.slice(0, 5).map((event) => (
                                    <div key={event.id} className="p-3 hover:bg-gray-50 transition-colors rounded-lg cursor-pointer" onClick={() => event.link && navigate(event.link)}>
                                        <div className="flex gap-3">
                                            <div className={`flex-shrink-0 w-12 h-12 rounded-lg flex flex-col items-center justify-center
                                                ${event.color === 'orange' ? 'bg-orange-50 text-orange-600' :
                                                    event.color === 'blue' ? 'bg-blue-50 text-blue-600' : 'bg-gray-50 text-gray-600'}`}
                                            >
                                                <span className="text-xs font-bold uppercase">{new Date(event.date).toLocaleDateString(undefined, { month: 'short' })}</span>
                                                <span className="text-lg font-bold leading-none">{new Date(event.date).getDate()}</span>
                                            </div>
                                            <div className="flex-1 min-w-0">
                                                <h4 className="text-sm font-semibold text-gray-900 truncate">{event.title}</h4>
                                                <p className="text-xs text-gray-500 mt-0.5 line-clamp-1">{event.description}</p>
                                                <div className="flex items-center gap-2 mt-1.5">
                                                    {event.time && (
                                                        <span className="text-[10px] bg-gray-100 text-gray-600 px-1.5 py-0.5 rounded font-medium">
                                                            {event.time}
                                                        </span>
                                                    )}
                                                    <span className="text-[10px] text-gray-400">{event.branch}</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        ) : (
                            <div className="py-8 text-center">
                                <div className="w-12 h-12 bg-gray-50 rounded-full flex items-center justify-center mx-auto mb-3">
                                    <Calendar className="w-6 h-6 text-gray-300" aria-hidden="true" />
                                </div>
                                <p className="text-sm font-medium text-gray-900">Nothing coming up</p>
                                <p className="text-xs text-gray-400 mt-1">Enjoy the quiet — no events ahead.</p>
                            </div>
                        )}
                    </SectionCard>

                    {/* Quick Actions */}
                    <SectionCard title="Quick Actions">
                        <div className="grid grid-cols-2 gap-3">
                            <QuickAction
                                label="Record Vitals"
                                icon={Heart}
                                onClick={() => navigate('/vitals')}
                            />
                            <QuickAction
                                label="New Incident"
                                icon={AlertCircle}
                                onClick={() => navigate('/incidents')}
                            />
                            <QuickAction
                                label="Administer Meds"
                                icon={Pill}
                                onClick={() => navigate('/medications')}
                            />
                            <QuickAction
                                label="Progress notes"
                                icon={FileText}
                                onClick={() => navigate('/t-logs')}
                            />
                        </div>
                    </SectionCard>
                </div>
            </div>
        </div>
    );
}

function NeedsAttentionCard({ items, navigate }) {
    const cardRef = React.useRef(null);

    React.useEffect(() => {
        if (cardRef.current && shouldAnimate()) {
            slideInUp(cardRef.current, { duration: 320, delay: 80 });
        }
    }, []);

    return (
        <section
            ref={cardRef}
            aria-label="Needs attention"
            className="rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden"
        >
            <div className="flex items-center justify-between px-5 py-3.5 border-b border-gray-100">
                <div className="flex items-center gap-2">
                    <AlertTriangle className="w-4 h-4 text-amber-500" aria-hidden="true" />
                    <h2 className="text-sm font-semibold text-gray-900">Needs Attention</h2>
                </div>
                <span className="text-xs text-gray-400" aria-live="polite">
                    {items.length} item{items.length > 1 ? 's' : ''}
                </span>
            </div>
            <ul className="divide-y divide-gray-50" role="list">
                {items.map((item) => {
                    const style = PRIORITY_STYLES[item.priority] || PRIORITY_STYLES.info;
                    const Icon = ACTIONABLE_ICONS[item.type] || AlertCircle;
                    return (
                        <li key={item.id}>
                            <button
                                type="button"
                                onClick={() => item.link && navigate(item.link)}
                                className="relative w-full flex items-center gap-4 px-5 py-3.5 text-left hover:bg-gray-50 focus-visible:bg-gray-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-[var(--theme-primary)] transition-colors group"
                                aria-label={`${item.title}${item.description ? ` — ${item.description}` : ''}, priority: ${style.label}`}
                            >
                                <div className={`absolute left-0 top-0 bottom-0 w-1 ${style.bar}`} aria-hidden="true" />
                                <div className={`p-2 rounded-lg ${style.badge}`} aria-hidden="true">
                                    <Icon className={`w-4 h-4 ${style.icon}`} />
                                </div>
                                <div className="flex-1 min-w-0">
                                    <p className="text-sm font-semibold text-gray-900 truncate">{item.title}</p>
                                    {item.description && (
                                        <p className="text-xs text-gray-500 mt-0.5 truncate">{item.description}</p>
                                    )}
                                </div>
                                <span className={`hidden sm:inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide ${style.badge}`} aria-hidden="true">
                                    {style.label}
                                </span>
                                <ArrowRight className="w-4 h-4 text-gray-300 group-hover:text-[var(--theme-primary)] group-focus-visible:text-[var(--theme-primary)] transition-colors flex-shrink-0" aria-hidden="true" />
                            </button>
                        </li>
                    );
                })}
            </ul>
        </section>
    );
}

function StatCard({ title, value, icon: Icon, onClick, urgent }) {
    return (
        <button
            type="button"
            onClick={onClick}
            className={`relative w-full text-left bg-white rounded-xl shadow-sm border p-5 hover:shadow-md transition-all duration-200 group overflow-hidden
                focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--theme-primary)] focus-visible:ring-offset-2
                ${urgent ? 'border-red-200 hover:border-red-300' : 'border-gray-200 hover:border-[var(--theme-primary)]/30'}`}
        >
            {urgent && <div className="absolute top-0 left-0 right-0 h-0.5 bg-red-500" aria-hidden="true" />}
            <div className="flex items-center justify-between mb-3">
                <div className={`p-2.5 rounded-lg group-hover:scale-110 transition-transform duration-200
                    ${urgent ? 'bg-red-50 text-red-600' : 'bg-[var(--theme-primary-bg)] text-[var(--theme-primary)]'}`}
                    aria-hidden="true"
                >
                    <Icon className="w-5 h-5" />
                </div>
                <ChevronRight className={`w-4 h-4 transition-colors ${urgent ? 'text-red-300 group-hover:text-red-500' : 'text-gray-300 group-hover:text-[var(--theme-primary)]'}`} aria-hidden="true" />
            </div>
            <div>
                <p className="text-sm font-medium text-gray-500">{title}</p>
                <p className={`text-2xl font-bold mt-1 ${urgent && value > 0 ? 'text-red-600' : 'text-gray-900'}`} aria-label={`${value} ${title}`}>{value}</p>
            </div>
            <p className="text-xs text-[var(--theme-primary)] font-medium opacity-0 group-hover:opacity-100 group-focus-visible:opacity-100 transition-opacity mt-1.5" aria-hidden="true">
                View details →
            </p>
        </button>
    );
}

function QuickAction({ label, icon: Icon, onClick }) {
    return (
        <button
            type="button"
            onClick={onClick}
            aria-label={label}
            className="bg-[var(--theme-primary)] hover:bg-[var(--theme-primary-hover)] text-[var(--theme-text-on-primary)] p-4 rounded-lg flex flex-col items-center justify-center gap-2 transition-all duration-200 hover:shadow-md shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-2 focus-visible:ring-offset-[var(--theme-primary)] active:scale-95"
        >
            <Icon className="w-5 h-5" aria-hidden="true" />
            <span className="text-xs font-semibold">{label}</span>
        </button>
    );
}
