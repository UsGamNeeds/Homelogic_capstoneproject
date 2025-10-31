import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import api from '../services/api';
import { Search } from 'lucide-react';

export default function Residents() {
    const [search, setSearch] = useState('');
    
    const { data, isLoading } = useQuery({
        queryKey: ['residents', search],
        queryFn: async () => {
            const response = await api.get('/v1/residents', {
                params: { search, per_page: 12 },
            });
            return response.data;
        },
    });

    return (
        <div>
            <h1 className="text-3xl font-bold text-gray-900 mb-6">Residents</h1>
            
            <div className="bg-white rounded-lg shadow p-6 mb-6">
                <h2 className="text-xl font-semibold text-gray-900 mb-2">All Residents</h2>
                <p className="text-gray-600 mb-4">Search and view details for all residents in the facility.</p>
                
                <div className="relative">
                    <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 w-5 h-5 text-gray-400" />
                    <input
                        type="text"
                        placeholder="Search by name or room number..."
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        className="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    />
                </div>
            </div>

            {isLoading ? (
                <div className="text-center py-12">Loading residents...</div>
            ) : (
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    {data?.data?.map((resident) => (
                        <div key={resident.id} className="bg-white rounded-lg shadow p-6">
                            <h3 className="text-lg font-semibold text-gray-900 mb-4">{resident.first_name} {resident.last_name}</h3>
                            <div className="space-y-2 text-sm">
                                <div className="flex justify-between">
                                    <span className="text-gray-600">Room:</span>
                                    <span className="font-medium">{resident.room_number || 'N/A'}</span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-gray-600">DOB:</span>
                                    <span className="font-medium">
                                        {resident.date_of_birth ? new Date(resident.date_of_birth).toLocaleDateString() : 'N/A'}
                                    </span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-gray-600">Admission:</span>
                                    <span className="font-medium">
                                        {resident.admission_date ? new Date(resident.admission_date).toLocaleDateString() : 'N/A'}
                                    </span>
                                </div>
                                {resident.allergies && (
                                    <div className="flex justify-between">
                                        <span className="text-gray-600">Allergies:</span>
                                        <span className="font-medium">{resident.allergies}</span>
                                    </div>
                                )}
                                {resident.primary_diagnosis && (
                                    <div className="flex justify-between">
                                        <span className="text-gray-600">Diagnosis:</span>
                                        <span className="font-medium">{resident.primary_diagnosis}</span>
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

