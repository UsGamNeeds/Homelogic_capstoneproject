import { useMutation, useQuery } from '@tanstack/react-query';
import api from '../services/api';

// POSTs a message to the assistant endpoint and returns { intent, answer, data, suggestions, resident }.
export function useAssistant() {
    return useMutation({
        mutationFn: async ({ message, contextResidentId }) => {
            const res = await api.post('/assistant/query', {
                message,
                context_resident_id: contextResidentId ?? null,
            });
            // BaseApiController::success() wraps the payload as { data: ... }
            return res.data.data;
        },
    });
}

// Fetches resident-aware starter chips for the empty state. Only runs when enabled.
export function useStarterSuggestions(enabled) {
    return useQuery({
        queryKey: ['assistant', 'starter-suggestions'],
        queryFn: async () => {
            const res = await api.get('/assistant/suggestions');
            return res.data.data.suggestions ?? [];
        },
        enabled,
        staleTime: 5 * 60 * 1000,
    });
}
