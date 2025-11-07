import React, { useState } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api from '../services/api';
import { Calendar, Edit, ArrowLeft, CheckCircle } from 'lucide-react';

export default function CreateAppointment() {
    const { residentId } = useParams();
    const navigate = useNavigate();
    const queryClient = useQueryClient();
    const [additionalDetails, setAdditionalDetails] = useState('');

    // Fetch resident data
    const { data: residentData, isLoading: residentLoading } = useQuery({
        queryKey: ['resident', residentId],
        queryFn: async () => {
            const response = await api.get(`/residents/${residentId}`);
            return response.data;
        },
        enabled: !!residentId,
    });

    // Fetch appointments for this resident
    const { data: appointmentsData, isLoading: appointmentsLoading, refetch } = useQuery({
        queryKey: ['appointments', residentId],
        queryFn: async () => {
            const response = await api.get('/appointments', { 
                params: { 
                    resident_id: residentId,
                    per_page: 100 
                } 
            });
            return response.data;
        },
        enabled: !!residentId,
    });

    // Submit additional details mutation
    const submitMutation = useMutation({
        mutationFn: async () => {
            const payload = {
                resident_id: parseInt(residentId),
                branch_id: residentData?.branch_id || '',
                appointment_date: new Date().toISOString().split('T')[0],
                appointment_time: null,
                provider_name: null,
                location: null,
                description: additionalDetails || null,
                notes: additionalDetails || null,
                status: 'scheduled',
            };
            
            return await api.post('/appointments', payload);
        },
        onSuccess: () => {
            queryClient.invalidateQueries(['appointments', residentId]);
            setAdditionalDetails('');
            refetch();
        },
        onError: (error) => {
            console.error('Error creating appointment:', error);
        },
    });

    const handleSubmit = (e) => {
        e.preventDefault();
        submitMutation.mutate();
    };

    if (residentLoading) {
        return (
            <div className="flex items-center justify-center min-h-screen">
                <div className="text-center">
                    <div className="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-[#2D5016]"></div>
                    <p className="mt-4 text-gray-600">Loading...</p>
                </div>
            </div>
        );
    }

    return (
        <div className="space-y-6">
            {/* Header */}
            <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <div className="flex items-center justify-between">
                    <div className="flex items-center space-x-4">
                        <button
                            onClick={() => navigate('/appointments')}
                            className="p-2 hover:bg-gray-100 rounded-lg transition-colors"
                            title="Back to Appointments"
                        >
                            <ArrowLeft className="w-5 h-5 text-gray-600" />
                        </button>
                        <div>
                            <h2 className="text-xl font-semibold text-gray-900">
                                Schedule Appointment
                            </h2>
                            <p className="text-sm text-gray-500">
                                {residentData?.first_name} {residentData?.last_name}
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            {/* Additional Details Form */}
            <div className="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <h3 className="text-lg font-semibold text-gray-900 mb-4">Additional Details (optional)</h3>
                <form onSubmit={handleSubmit}>
                    <textarea
                        value={additionalDetails}
                        onChange={(e) => setAdditionalDetails(e.target.value)}
                        placeholder="Enter any additional details..."
                        rows={4}
                        className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#2D5016] focus:border-transparent resize-none"
                    />
                    <div className="flex justify-center mt-4">
                        <button
                            type="submit"
                            disabled={submitMutation.isPending}
                            className="px-6 py-2 bg-gray-200 text-black font-bold rounded-lg hover:bg-gray-300 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            {submitMutation.isPending ? 'Submitting...' : 'Submit'}
                        </button>
                    </div>
                </form>
            </div>

            {/* Appointment History Grid */}
            <div className="bg-white rounded-lg shadow-sm border border-gray-200">
                <div className="px-6 py-4 border-b border-gray-200">
                    <h2 className="text-lg font-semibold text-gray-900">Appointment History</h2>
                </div>
                <div className="p-6">
                    {appointmentsLoading ? (
                        <div className="text-center py-12">
                            <div className="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-[#2D5016]"></div>
                            <p className="mt-4 text-gray-600">Loading appointments...</p>
                        </div>
                    ) : appointmentsData?.data?.length > 0 ? (
                        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                            {appointmentsData.data.map((appointment) => {
                                if (!appointment) return null;
                                
                                const date = appointment.appointment_date ? new Date(appointment.appointment_date) : null;
                                const dateStr = date && !isNaN(date.getTime()) ? date.toLocaleDateString('en-US', { 
                                    month: 'long', 
                                    day: 'numeric',
                                    year: 'numeric' 
                                }) : 'N/A';
                                
                                let timeStr = '';
                                if (appointment.appointment_time) {
                                    try {
                                        const timeParts = appointment.appointment_time.split(':');
                                        if (timeParts.length >= 2) {
                                            const hours = parseInt(timeParts[0]) || 0;
                                            const minutes = timeParts[1] || '00';
                                            const hour12 = hours % 12 || 12;
                                            const ampm = hours >= 12 ? 'PM' : 'AM';
                                            timeStr = `${hour12}:${minutes} ${ampm}`;
                                        }
                                    } catch (err) {
                                        console.error('Error parsing appointment time:', err);
                                    }
                                }
                                
                                const nextApptDate = appointment.next_appointment_date ? new Date(appointment.next_appointment_date) : null;
                                const nextApptDateStr = nextApptDate && !isNaN(nextApptDate.getTime()) ? nextApptDate.toLocaleDateString('en-US', { 
                                    month: 'long', 
                                    day: 'numeric',
                                    year: 'numeric' 
                                }) : 'N/A';
                                
                                return (
                                    <div key={appointment.id} className="bg-white rounded-lg shadow-md hover:shadow-lg transition-shadow border border-gray-200 p-5">
                                        <div className="flex items-start justify-between mb-3">
                                            <div className="flex-1">
                                                <h4 className="text-lg font-semibold text-gray-900 mb-1">
                                                    {appointment.resident?.first_name} {appointment.resident?.last_name}
                                                </h4>
                                                <div className="flex items-center space-x-2 text-sm text-gray-600">
                                                    <Calendar className="w-4 h-4" />
                                                    <span>{dateStr}</span>
                                                    {timeStr && <span className="text-gray-500">• {timeStr}</span>}
                                                </div>
                                            </div>
                                            <span className={`px-2 py-1 rounded-full text-xs font-medium ${
                                                appointment.status === 'scheduled' ? 'bg-amber-100 text-amber-800' :
                                                appointment.status === 'confirmed' ? 'bg-green-100 text-green-800' :
                                                appointment.status === 'completed' ? 'bg-emerald-100 text-emerald-800' :
                                                appointment.status === 'cancelled' ? 'bg-red-100 text-red-800' :
                                                'bg-gray-100 text-gray-800'
                                            }`}>
                                                {appointment.status?.charAt(0).toUpperCase() + appointment.status?.slice(1)}
                                            </span>
                                        </div>
                                        
                                        <div className="space-y-2 mb-4">
                                            <div className="text-sm text-gray-600">
                                                <span className="font-medium">Type:</span> {appointment.appointment_type?.name || appointment.appointmentType?.name || 'Other'}
                                            </div>
                                            {(appointment.description || appointment.provider_name) && (
                                                <div className="text-sm text-gray-600">
                                                    <span className="font-medium">Details:</span> {appointment.description || appointment.provider_name || '-'}
                                                </div>
                                            )}
                                            {nextApptDateStr !== 'N/A' && (
                                                <div className="text-sm text-gray-600">
                                                    <span className="font-medium">Next Appointment:</span> {nextApptDateStr}
                                                </div>
                                            )}
                                        </div>
                                        
                                        <div className="flex items-center justify-end">
                                            <button
                                                onClick={() => {
                                                    // Navigate to edit appointment or open modal
                                                    navigate(`/appointments?edit=${appointment.id}`);
                                                }}
                                                className="text-[#2D5016] hover:text-[#1a3009] p-2 hover:bg-gray-100 rounded-lg transition-colors"
                                                title="Edit"
                                            >
                                                <Edit className="w-5 h-5" />
                                            </button>
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    ) : (
                        <div className="text-center py-12">
                            <Calendar className="w-16 h-16 text-gray-300 mx-auto mb-4" />
                            <p className="text-gray-900 text-lg font-semibold mb-2">No Appointments Found</p>
                            <p className="text-gray-500 text-sm">No appointments found for this resident.</p>
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
}

