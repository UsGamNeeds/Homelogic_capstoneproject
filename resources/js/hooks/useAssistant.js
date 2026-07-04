import { useMutation } from '@tanstack/react-query';
import api from '../services/api';

// POSTs a message to the assistant endpoint and returns { intent, answer, data }.
export function useAssistant() {
    return useMutation({
        mutationFn: async (message) => {
            const res = await api.post('/assistant/query', { message });
            // BaseApiController::success() wraps the payload as { data: ... }
            return res.data.data;
        },
    });
}
