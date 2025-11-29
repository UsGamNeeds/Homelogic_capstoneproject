import axios from 'axios';

// Public API instance (no authentication required)
const publicApi = axios.create({
    baseURL: '/api/public',
    headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
    },
    withCredentials: true,
});

// Add CSRF token to requests
publicApi.interceptors.request.use((config) => {
    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    if (token) {
        config.headers['X-CSRF-TOKEN'] = token;
    }
    
    // For FormData (file uploads), let browser set Content-Type automatically
    if (config.data instanceof FormData) {
        delete config.headers['Content-Type'];
    }
    
    return config;
});

export default publicApi;

