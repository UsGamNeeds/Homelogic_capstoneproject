import React, { useState, useMemo } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { useSearchParams, useNavigate } from 'react-router-dom';
import { Plus, Edit, Trash2, Eye, X, FileText, Calendar, User, Search, Filter } from 'lucide-react';
import api from '../services/api';
import Card from '../components/Card';
import { toast } from 'sonner';
import TLogForm from './TLogForm';

const NOTIFICATION_LEVEL_COLORS = {
    urgent: 'bg-red-100 text-red-800 border-red-300',
    high: 'bg-orange-100 text-orange-800 border-orange-300',
    medium: 'bg-yellow-100 text-yellow-800 border-yellow-300',
    low: 'bg-green-100 text-green-800 border-green-300',
};

const TYPE_COLORS = {
    health: 'bg-blue-100 text-blue-800',
    notes: 'bg-gray-100 text-gray-800',
    'follow-up': 'bg-purple-100 text-purple-800',
    behavior: 'bg-orange-100 text-orange-800',
    contacts: 'bg-cyan-100 text-cyan-800',
    general: 'bg-green-100 text-green-800',
};

export default function TLogs() {
    const queryClient = useQueryClient();
    const navigate = useNavigate();
    const [searchParams, setSearchParams] = useSearchParams();
    
    const [showForm, setShowForm] = useState(false);
    const [showViewModal, setShowViewModal] = useState(false);
    const [selectedTLog, setSelectedTLog] = useState(null);
    const [filters, setFilters] = useState({
        type: searchParams.get('type') || 'all',
        notification_level: searchParams.get('notification_level') || 'all',
        resident_id: searchParams.get('resident_id') || '',
        branch_id: searchParams.get('branch_id') || '',
        search: searchParams.get('search') || '',
        date_from: searchParams.get('date_from') || '',
        date_to: searchParams.get('date_to') || '',
    });

    // Fetch T-Logs
    const { data, isLoading, error, refetch } = useQuery({
        queryKey: ['t-logs', filters],
        queryFn: async () => {
            const params = { per_page: 50 };
            Object.keys(filters).forEach(key => {
                if (filters[key] && filters[key] !== 'all') {
                    params[key] = filters[key];
                }
            });
            const response = await api.get('/t-logs', { params });
            return response.data;
        },
        retry: 1,
    });

    // Fetch current user to check if caregiver
    const { data: currentUser } = useQuery({
        queryKey: ['current-user'],
        queryFn: async () => {
            const res = await api.get('/user');
            return res.data;
        },
        staleTime: 5 * 60 * 1000,
    });

    // Determine if user is a caregiver
    const isCaregiver = useMemo(() => {
        if (!currentUser) {
            return false;
        }

        const truthyValues = [
            currentUser.is_caregiver,
            currentUser.isCaregiver,
            currentUser.caregiver,
            currentUser.is_care_giver,
        ];

        const normalizeToBoolean = (value) => {
            if (typeof value === 'boolean') return value;
            if (typeof value === 'number') return value === 1;
            if (typeof value === 'string') {
                const normalized = value.trim().toLowerCase();
                return ['1', 'true', 'yes', 'y', 'caregiver', 'care_giver'].includes(normalized);
            }
            return false;
        };

        if (truthyValues.some(normalizeToBoolean)) {
            return true;
        }

        const candidateValues = [];
        const collectCandidate = (value) => {
            if (value !== null && value !== undefined && value !== '') {
                candidateValues.push(String(value));
            }
        };

        collectCandidate(currentUser.role);
        collectCandidate(currentUser.position);
        collectCandidate(currentUser.primary_role);
        collectCandidate(currentUser.job_title);

        const roles = currentUser.roles;
        if (Array.isArray(roles)) {
            roles.forEach((roleItem) => {
                if (!roleItem) return;
                if (typeof roleItem === 'string') {
                    collectCandidate(roleItem);
                } else {
                    collectCandidate(roleItem.name);
                    collectCandidate(roleItem.title);
                }
            });
        } else if (roles?.data && Array.isArray(roles.data)) {
            roles.data.forEach((roleItem) => {
                if (!roleItem) return;
                if (typeof roleItem === 'string') {
                    collectCandidate(roleItem);
                } else {
                    collectCandidate(roleItem.name);
                    collectCandidate(roleItem.title);
                }
            });
        }

        return candidateValues.some((value) => {
            const lower = value.toLowerCase().trim();
            if (!lower) {
                return false;
            }
            const normalized = lower.replace(/[\s_-]/g, '');
            if (normalized === 'caregiver') {
                return true;
            }
            return lower.includes('care') && lower.includes('giver');
        });
    }, [currentUser]);

    const caregiverBranchId = useMemo(() => {
        if (!isCaregiver) {
            return null;
        }
        return currentUser?.assigned_branch_id ? String(currentUser.assigned_branch_id) : null;
    }, [isCaregiver, currentUser?.assigned_branch_id]);

    // Fetch residents for filter (filtered by branch if caregiver, all residents for admins)
    const { data: residentsData } = useQuery({
        queryKey: ['residents-list', isCaregiver ? caregiverBranchId || 'none' : 'all'],
        queryFn: async () => {
            const params = { per_page: 100 };
            // For caregivers, only show residents from their assigned branch
            // For admins and other non-caregivers, show all residents (no branch_id filter)
            if (isCaregiver && caregiverBranchId) {
                params.branch_id = caregiverBranchId;
            }
            return (await api.get('/residents', { params })).data;
        },
        enabled: currentUser !== undefined, // Wait for user data to load
    });

    // Fetch branches for filter
    const { data: branchesData } = useQuery({
        queryKey: ['branches-list'],
        queryFn: async () => {
            const response = await api.get('/branches', { params: { per_page: 100 } });
            const branches = response.data?.data || response.data || [];
            return {
                ...response.data,
                data: branches.filter(b => b.is_active !== false)
            };
        },
    });

    const deleteMutation = useMutation({
        mutationFn: async (id) => {
            return await api.delete(`/t-logs/${id}`);
        },
        onSuccess: () => {
            queryClient.invalidateQueries(['t-logs']);
            toast.success('T-Log deleted successfully');
        },
        onError: (error) => {
            console.error('Error deleting T-Log:', error);
            toast.error(error.response?.data?.message || 'Failed to delete T-Log');
        },
    });

    const handleOpenForm = (tLog = null) => {
        setSelectedTLog(tLog);
        setShowForm(true);
    };

    const handleCloseForm = () => {
        setShowForm(false);
        setSelectedTLog(null);
    };

    const handleView = (tLog) => {
        setSelectedTLog(tLog);
        setShowViewModal(true);
    };

    const handleCloseView = () => {
        setShowViewModal(false);
        setSelectedTLog(null);
    };

    const handleDelete = (id) => {
        if (window.confirm('Are you sure you want to delete this T-Log?')) {
            deleteMutation.mutate(id);
        }
    };

    const handleFilterChange = (key, value) => {
        const newFilters = { ...filters, [key]: value };
        setFilters(newFilters);
        
        // Update URL params
        const newParams = new URLSearchParams();
        Object.keys(newFilters).forEach(k => {
            if (newFilters[k] && newFilters[k] !== 'all') {
                newParams.set(k, newFilters[k]);
            }
        });
        setSearchParams(newParams);
    };

    const tLogs = data?.data || [];
    const residents = residentsData?.data || [];
    const branches = branchesData?.data || [];

    // If form is open, show form as full page (like Expenses/Incidents form)
    if (showForm) {
        return (
            <TLogForm
                tLog={selectedTLog}
                onClose={handleCloseForm}
                onSuccess={() => {
                    queryClient.invalidateQueries(['t-logs']);
                    handleCloseForm();
                }}
            />
        );
    }

    return (
        <div className="space-y-6">
            <div className="flex justify-between items-center">
                <h1 className="text-2xl font-bold text-gray-900">T-Logs</h1>
                <button
                    onClick={() => handleOpenForm()}
                    className="flex items-center gap-2 px-4 py-2 bg-[var(--theme-primary)] text-white rounded-lg hover:bg-[var(--theme-primary-dark)] transition-colors"
                >
                    <Plus className="w-5 h-5" />
                    New T-Log
                </button>
            </div>

            {/* Filters */}
            <Card>
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Search</label>
                        <div className="relative">
                            <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 w-4 h-4" />
                            <input
                                type="text"
                                value={filters.search}
                                onChange={(e) => handleFilterChange('search', e.target.value)}
                                placeholder="Search T-Logs..."
                                className="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[var(--theme-primary)] focus:border-transparent"
                            />
                        </div>
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Type</label>
                        <select
                            value={filters.type}
                            onChange={(e) => handleFilterChange('type', e.target.value)}
                            className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[var(--theme-primary)] focus:border-transparent"
                        >
                            <option value="all">All Types</option>
                            <option value="health">Health</option>
                            <option value="notes">Notes</option>
                            <option value="follow-up">Follow-up</option>
                            <option value="behavior">Behavior</option>
                            <option value="contacts">Contacts</option>
                            <option value="general">General</option>
                        </select>
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Notification Level</label>
                        <select
                            value={filters.notification_level}
                            onChange={(e) => handleFilterChange('notification_level', e.target.value)}
                            className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[var(--theme-primary)] focus:border-transparent"
                        >
                            <option value="all">All Levels</option>
                            <option value="low">Low</option>
                            <option value="medium">Medium</option>
                            <option value="high">High</option>
                            <option value="urgent">Urgent</option>
                        </select>
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Resident</label>
                        <select
                            value={filters.resident_id}
                            onChange={(e) => handleFilterChange('resident_id', e.target.value)}
                            className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[var(--theme-primary)] focus:border-transparent"
                        >
                            <option value="">All Residents</option>
                            {residents.map((resident) => (
                                <option key={resident.id} value={resident.id}>
                                    {resident.name || `${resident.first_name} ${resident.last_name}`}
                                </option>
                            ))}
                        </select>
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Date From</label>
                        <input
                            type="date"
                            value={filters.date_from}
                            onChange={(e) => handleFilterChange('date_from', e.target.value)}
                            className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[var(--theme-primary)] focus:border-transparent"
                        />
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Date To</label>
                        <input
                            type="date"
                            value={filters.date_to}
                            onChange={(e) => handleFilterChange('date_to', e.target.value)}
                            className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[var(--theme-primary)] focus:border-transparent"
                        />
                    </div>
                </div>
            </Card>

            {/* T-Logs List */}
            {isLoading ? (
                <Card>
                    <div className="text-center py-8">Loading...</div>
                </Card>
            ) : error ? (
                <Card>
                    <div className="text-center py-8 text-red-600">Error loading T-Logs</div>
                </Card>
            ) : tLogs.length === 0 ? (
                <Card>
                    <div className="text-center py-8 text-gray-500">No T-Logs found</div>
                </Card>
            ) : (
                <div className="space-y-4">
                    {tLogs.map((tLog) => (
                        <Card key={tLog.id} className="hover:shadow-md transition-shadow">
                            <div className="flex justify-between items-start">
                                <div className="flex-1">
                                    <div className="flex items-center gap-3 mb-2">
                                        <h3 className="text-lg font-semibold text-gray-900">{tLog.summary}</h3>
                                        <div className="flex gap-2 flex-wrap">
                                            {tLog.types?.map((type) => (
                                                <span
                                                    key={type}
                                                    className={`px-2 py-1 text-xs font-medium rounded ${TYPE_COLORS[type] || 'bg-gray-100 text-gray-800'}`}
                                                >
                                                    {type.charAt(0).toUpperCase() + type.slice(1)}
                                                </span>
                                            ))}
                                        </div>
                                        <span
                                            className={`px-2 py-1 text-xs font-medium rounded border ${NOTIFICATION_LEVEL_COLORS[tLog.notification_level] || 'bg-gray-100 text-gray-800'}`}
                                        >
                                            {tLog.notification_level || 'low'}
                                        </span>
                                    </div>
                                    
                                    {tLog.description && (
                                        <p className="text-gray-600 mb-3 line-clamp-2">{tLog.description}</p>
                                    )}

                                    <div className="flex items-center gap-4 text-sm text-gray-500">
                                        {tLog.resident && (
                                            <div className="flex items-center gap-1">
                                                <User className="w-4 h-4" />
                                                <span>{tLog.resident.name || `${tLog.resident.first_name} ${tLog.resident.last_name}`}</span>
                                            </div>
                                        )}
                                        {tLog.reported_on && (
                                            <div className="flex items-center gap-1">
                                                <Calendar className="w-4 h-4" />
                                                <span>{new Date(tLog.reported_on).toLocaleDateString()}</span>
                                            </div>
                                        )}
                                        {tLog.attachments && tLog.attachments.length > 0 && (
                                            <div className="flex items-center gap-1">
                                                <FileText className="w-4 h-4" />
                                                <span>{tLog.attachments.length} attachment(s)</span>
                                            </div>
                                        )}
                                    </div>
                                </div>

                                <div className="flex gap-2 ml-4">
                                    <button
                                        onClick={() => handleView(tLog)}
                                        className="p-2 text-gray-600 hover:bg-gray-100 rounded-lg transition-colors"
                                        title="View"
                                    >
                                        <Eye className="w-5 h-5" />
                                    </button>
                                    <button
                                        onClick={() => handleOpenForm(tLog)}
                                        className="p-2 text-[var(--theme-primary)] hover:bg-[var(--theme-primary-bg)] rounded-lg transition-colors"
                                        title="Edit"
                                    >
                                        <Edit className="w-5 h-5" />
                                    </button>
                                    <button
                                        onClick={() => handleDelete(tLog.id)}
                                        className="p-2 text-red-600 hover:bg-red-50 rounded-lg transition-colors"
                                        title="Delete"
                                    >
                                        <Trash2 className="w-5 h-5" />
                                    </button>
                                </div>
                            </div>
                        </Card>
                    ))}
                </div>
            )}

            {/* Pagination */}
            {data && data.last_page > 1 && (
                <div className="flex justify-center gap-2">
                    <button
                        onClick={() => handleFilterChange('page', data.current_page - 1)}
                        disabled={data.current_page === 1}
                        className="px-4 py-2 border border-gray-300 rounded-lg disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        Previous
                    </button>
                    <span className="px-4 py-2">
                        Page {data.current_page} of {data.last_page}
                    </span>
                    <button
                        onClick={() => handleFilterChange('page', data.current_page + 1)}
                        disabled={data.current_page === data.last_page}
                        className="px-4 py-2 border border-gray-300 rounded-lg disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        Next
                    </button>
                </div>
            )}

            {/* View Modal */}
            {showViewModal && selectedTLog && (
                <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
                    <div className="bg-white rounded-lg max-w-3xl w-full max-h-[90vh] overflow-y-auto">
                        <div className="sticky top-0 bg-white border-b border-gray-200 px-6 py-4 flex justify-between items-center">
                            <h2 className="text-xl font-bold text-gray-900">T-Log Details</h2>
                            <button
                                onClick={handleCloseView}
                                className="p-2 hover:bg-gray-100 rounded-lg transition-colors"
                            >
                                <X className="w-5 h-5" />
                            </button>
                        </div>
                        <div className="p-6 space-y-4">
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">Summary</label>
                                <p className="text-gray-900">{selectedTLog.summary}</p>
                            </div>
                            
                            {selectedTLog.description && (
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">Description</label>
                                    <p className="text-gray-900 whitespace-pre-wrap">{selectedTLog.description}</p>
                                </div>
                            )}

                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">Types</label>
                                    <div className="flex gap-2 flex-wrap">
                                        {selectedTLog.types?.map((type) => (
                                            <span
                                                key={type}
                                                className={`px-2 py-1 text-xs font-medium rounded ${TYPE_COLORS[type] || 'bg-gray-100 text-gray-800'}`}
                                            >
                                                {type.charAt(0).toUpperCase() + type.slice(1)}
                                            </span>
                                        ))}
                                    </div>
                                </div>

                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">Notification Level</label>
                                    <span
                                        className={`inline-block px-2 py-1 text-xs font-medium rounded border ${NOTIFICATION_LEVEL_COLORS[selectedTLog.notification_level] || 'bg-gray-100 text-gray-800'}`}
                                    >
                                        {selectedTLog.notification_level || 'low'}
                                    </span>
                                </div>

                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">Resident</label>
                                    <p className="text-gray-900">
                                        {selectedTLog.resident?.name || `${selectedTLog.resident?.first_name} ${selectedTLog.resident?.last_name}` || 'N/A'}
                                    </p>
                                </div>

                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">Branch</label>
                                    <p className="text-gray-900">{selectedTLog.branch?.name || 'N/A'}</p>
                                </div>

                                {selectedTLog.reporter && (
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1">Reporter</label>
                                        <p className="text-gray-900">{selectedTLog.reporter.name || selectedTLog.reporter.email}</p>
                                    </div>
                                )}

                                {selectedTLog.reported_on && (
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1">Reported On</label>
                                        <p className="text-gray-900">{new Date(selectedTLog.reported_on).toLocaleString()}</p>
                                    </div>
                                )}
                            </div>

                            {selectedTLog.attachments && selectedTLog.attachments.length > 0 && (
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-2">Attachments</label>
                                    <div className="space-y-2">
                                        {selectedTLog.attachments.map((attachment) => (
                                            <a
                                                key={attachment.id}
                                                href={attachment.file_url}
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                className="flex items-center gap-2 p-2 border border-gray-200 rounded-lg hover:bg-gray-50"
                                            >
                                                <FileText className="w-5 h-5 text-gray-400" />
                                                <span className="text-sm text-gray-900">{attachment.file_name}</span>
                                                <span className="text-xs text-gray-500 ml-auto">{attachment.file_size_human}</span>
                                            </a>
                                        ))}
                                    </div>
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}

