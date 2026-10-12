import { useQuery } from '@tanstack/react-query';
import api from '../services/api';

/** Fetch a single resident by id for "step 2" add-record pages (name banner + form seed). */
export function useResident(residentId) {
    return useQuery({
        queryKey: ['resident', residentId],
        queryFn: async () => {
            const response = await api.get(`/residents/${residentId}`);
            return response.data?.data ?? response.data;
        },
        enabled: !!residentId,
    });
}

/** Fetch a single staff member (User) by id — the Employee Documents analog of useResident. */
export function useStaffMember(userId) {
    return useQuery({
        queryKey: ['staff-member', userId],
        queryFn: async () => {
            const response = await api.get(`/users/${userId}`);
            return response.data?.data ?? response.data;
        },
        enabled: !!userId,
    });
}
