import { Outlet, Link, useLocation } from 'react-router-dom';
import { 
    LayoutDashboard, 
    Calendar, 
    Building2, 
    Users, 
    FileText, 
    Bell,
    Monitor,
    RefreshCw,
    Maximize2,
    User,
    LogOut
} from 'lucide-react';
import { useState } from 'react';

const navigation = [
    { name: 'Dashboard', icon: LayoutDashboard, path: '/dashboard' },
    { name: 'Appointments', icon: Calendar, path: '/appointments' },
    { name: 'Organization', icon: Building2, path: '/organization' },
    { name: 'Residents', icon: Users, path: '/residents' },
    { name: 'Reports', icon: FileText, path: '/reports' },
];

export default function Layout() {
    const location = useLocation();
    const [userMenuOpen, setUserMenuOpen] = useState(false);

    return (
        <div className="flex h-screen bg-gray-50">
            {/* Sidebar */}
            <aside className="w-64 bg-slate-800 text-white flex flex-col">
                {/* Logo */}
                <div className="p-6 border-b border-slate-700">
                    <div className="flex items-center space-x-3">
                        <div className="w-10 h-10 bg-blue-500 rounded-full flex items-center justify-center">
                            <span className="text-white font-bold text-lg">C</span>
                        </div>
                        <span className="text-xl font-semibold">CareLink</span>
                    </div>
                </div>

                {/* Navigation */}
                <nav className="flex-1 p-4 space-y-2">
                    {navigation.map((item) => {
                        const Icon = item.icon;
                        const isActive = location.pathname === item.path;
                        return (
                            <Link
                                key={item.name}
                                to={item.path}
                                className={`flex items-center space-x-3 px-4 py-3 rounded-lg transition-colors ${
                                    isActive
                                        ? 'bg-blue-600 text-white'
                                        : 'text-gray-300 hover:bg-slate-700'
                                }`}
                            >
                                <Icon className="w-5 h-5" />
                                <span>{item.name}</span>
                            </Link>
                        );
                    })}
                </nav>

                {/* User Profile at bottom */}
                <div className="p-4 border-t border-slate-700">
                    <div className="flex items-center space-x-3">
                        <div className="w-10 h-10 bg-slate-600 rounded-full flex items-center justify-center">
                            <User className="w-6 h-6 text-gray-300" />
                        </div>
                        <div className="flex-1">
                            <p className="text-sm font-medium">Jessica Jones</p>
                            <Link to="/profile" className="text-xs text-gray-400 hover:text-white">
                                View profile
                            </Link>
                        </div>
                    </div>
                </div>
            </aside>

            {/* Main Content */}
            <div className="flex-1 flex flex-col overflow-hidden">
                {/* Top Bar */}
                <header className="bg-white border-b border-gray-200 px-6 py-4 flex items-center justify-between">
                    <h1 className="text-xl font-semibold text-gray-900">Healthcare Management System</h1>
                    <div className="flex items-center space-x-4">
                        <button className="p-2 hover:bg-gray-100 rounded-lg">
                            <Monitor className="w-5 h-5 text-gray-600" />
                        </button>
                        <button className="p-2 hover:bg-gray-100 rounded-lg">
                            <RefreshCw className="w-5 h-5 text-gray-600" />
                        </button>
                        <button className="p-2 hover:bg-gray-100 rounded-lg">
                            <Maximize2 className="w-5 h-5 text-gray-600" />
                        </button>
                        <button className="relative p-2 hover:bg-gray-100 rounded-lg">
                            <Bell className="w-5 h-5 text-gray-600" />
                            <span className="absolute top-1 right-1 w-2 h-2 bg-red-500 rounded-full"></span>
                        </button>
                        <div className="relative">
                            <button
                                onClick={() => setUserMenuOpen(!userMenuOpen)}
                                className="w-10 h-10 rounded-full bg-gray-200 hover:bg-gray-300 flex items-center justify-center"
                            >
                                <User className="w-6 h-6 text-gray-600" />
                            </button>
                            {userMenuOpen && (
                                <div className="absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg border border-gray-200 py-1 z-50">
                                    <Link
                                        to="/profile"
                                        className="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100"
                                    >
                                        Profile
                                    </Link>
                                    <button
                                        onClick={() => {
                                            // Handle logout
                                            window.location.href = '/admin/logout';
                                        }}
                                        className="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 flex items-center space-x-2"
                                    >
                                        <LogOut className="w-4 h-4" />
                                        <span>Sign out</span>
                                    </button>
                                </div>
                            )}
                        </div>
                    </div>
                </header>

                {/* Page Content */}
                <main className="flex-1 overflow-y-auto bg-gray-50 p-6">
                    <Outlet />
                </main>
            </div>
        </div>
    );
}

