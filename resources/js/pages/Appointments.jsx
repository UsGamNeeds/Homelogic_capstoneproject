import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import api from '../services/api';
import { CheckCircle, XCircle } from 'lucide-react';

export default function Appointments() {
    const [dateFilter, setDateFilter] = useState('upcoming');
    const [statusFilter, setStatusFilter] = useState('all');

    const { data, isLoading, refetch } = useQuery({
        queryKey: ['appointments', dateFilter, statusFilter],
        queryFn: async () => {
            const response = await api.get('/v1/appointments', {
                params: {
                    date_filter: dateFilter,
                    status: statusFilter,
                    per_page: 15,
                },
            });
            return response.data;
        },
    });

    const handleStatusUpdate = async (id, status) => {
        try {
            await api.patch(`/v1/appointments/${id}/status`, { status });
            refetch();
        } catch (error) {
            console.error('Failed to update appointment status:', error);
        }
    };

    return (
        <div>
            <h1 className="text-3xl font-bold text-gray-900 mb-6">Appointments</h1>
            
            <div className="bg-white rounded-lg shadow p-6 mb-6">
                <h2 className="text-xl font-semibold text-gray-900 mb-2">Appointment Management</h2>
                <p className="text-gray-600 mb-6">View, filter, and update resident appointments.</p>
                
                <div className="flex flex-wrap gap-6">
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-2">Date:</label>
                        <div className="flex space-x-2">
                            <button
                                onClick={() => setDateFilter('upcoming')}
                                className={`px-4 py-2 rounded-lg text-sm font-medium transition-colors ${
                                    dateFilter === 'upcoming'
                                        ? 'bg-blue-600 text-white'
                                        : 'bg-white text-gray-700 border border-gray-300 hover:bg-gray-50'
                                }`}
                            >
                                Upcoming
                            </button>
                            <button
                                onClick={() => setDateFilter('past')}
                                className={`px-4 py-2 rounded-lg text-sm font-medium transition-colors ${
                                    dateFilter === 'past'
                                        ? 'bg-blue-600 text-white'
                                        : 'bg-white text-gray-700 border border-gray-300 hover:bg-gray-50'
                                }`}
                            >
                                Past
                            </button>
                        </div>
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-2">Status:</label>
                        <div className="flex space-x-2">
                            {['all', 'scheduled', 'completed', 'cancelled'].map((status) => (
                                <button
                                    key={status}
                                    onClick={() => setStatusFilter(status)}
                                    className={`px-4 py-2 rounded-lg text-sm font-medium transition-colors capitalize ${
                                        statusFilter === status
                                            ? 'bg-blue-600 text-white'
                                            : 'bg-white text-gray-700 border border-gray-300 hover:bg-gray-50'
                                    }`}
                                >
                                    {status}
                                </button>
                            ))}
                        </div>
                    </div>
                </div>
            </div>

            {isLoading ? (
                <div className="text-center py-12">Loading appointments...</div>
            ) : (
                <div className="space-y-4">
                    {data?.data?.map((appointment) => (
                        <div key={appointment.id} className="bg-white rounded-lg shadow p-6">
                            <div className="flex items-start justify-between">
                                <div className="flex-1">
                                    <div className="flex items-center space-x-3 mb-3">
                                        <h3 className="text-lg font-semibold text-gray-900">
                                            {appointment.appointment_type?.name || 'Appointment'}
                                        </h3>
                                        <span className={`px-3 py-1 rounded-full text-xs font-medium ${
                                            appointment.status === 'scheduled' ? 'bg-blue-100 text-blue-800' :
                                            appointment.status === 'completed' ? 'bg-green-100 text-green-800' :
                                            'bg-red-100 text-red-800'
                                        }`}>
                                            {appointment.status}
                                        </span>
                                    </div>
                                    <p className="text-gray-700 mb-1">
                                        <span className="font-medium">{appointment.resident?.first_name} {appointment.resident?.last_name}</span>
                                    </p>
                                    {appointment.healthcare_provider && (
                                        <p className="text-gray-600 mb-1">
                                            With {appointment.healthcare_provider.name}
                                        </p>
                                    )}
                                    <p className="text-gray-500 text-sm">
                                        {new Date(appointment.appointment_date).toLocaleString()}
                                    </p>
                                </div>
                                {appointment.status === 'scheduled' && (
                                    <div className="flex space-x-2">
                                        <button
                                            onClick={() => handleStatusUpdate(appointment.id, 'completed')}
                                            className="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors flex items-center space-x-2"
                                        >
                                            <CheckCircle className="w-4 h-4" />
                                            <span>Complete</span>
                                        </button>
                                        <button
                                            onClick={() => handleStatusUpdate(appointment.id, 'cancelled')}
                                            className="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors flex items-center space-x-2"
                                        >
                                            <XCircle className="w-4 h-4" />
                                            <span>Cancel</span>
                                        </button>
                                    </div>
                                )}
                            </div>
                        </div>
                    ))}
                </div>
            )}
        </div>
    );
}

