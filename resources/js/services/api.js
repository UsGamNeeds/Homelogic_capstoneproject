import axios from 'axios';

const api = axios.create({
    baseURL: '/api/v1',
    headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
    },
    withCredentials: true,
});

// Add CSRF token to requests
api.interceptors.request.use((config) => {
    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    if (token) {
        config.headers['X-CSRF-TOKEN'] = token;
    }
    
    // Add auth token if available
    const authToken = localStorage.getItem('auth_token');
    if (authToken) {
        config.headers['Authorization'] = `Bearer ${authToken}`;
    }
    
    // For FormData (file uploads), let browser set Content-Type automatically
    if (config.data instanceof FormData) {
        delete config.headers['Content-Type'];
    }
    
    return config;
});

// Handle 401 responses (unauthorized)
api.interceptors.response.use(
    (response) => response,
    (error) => {
        if (error.response?.status === 401) {
            // Don't redirect if we're on a public page
            const currentPath = window.location.pathname;
            const publicPaths = ['/app/login', '/app/staff/clock-in', '/staff/clock-in'];
            const isPublicPath = publicPaths.some(path => currentPath === path || currentPath.startsWith(path));
            
            // Clear token
            localStorage.removeItem('auth_token');
            localStorage.removeItem('user_name');
            
            // Only redirect if not on a public path
            if (!isPublicPath && currentPath !== '/app/login') {
                window.location.href = '/app/login';
            }
        }
        return Promise.reject(error);
    }
);

export default api;

