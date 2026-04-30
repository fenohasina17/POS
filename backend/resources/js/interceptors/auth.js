import axios from 'axios';
import router from '../router';

// Create axios instance
const api = axios.create({
    baseURL: '/api',
    headers: {
        'Content-Type': 'application/json',
    }
});

// Request interceptor to add token
api.interceptors.request.use(
    (config) => {
        const token = localStorage.getItem('auth_token');
        if (token) {
            config.headers.Authorization = `Bearer ${token}`;
        }
        return config;
    },
    (error) => {
        return Promise.reject(error);
    }
);

// Response interceptor to handle 401 errors
api.interceptors.response.use(
    (response) => {
        return response;
    },
    (error) => {
        if (error.response && error.response.status === 401) {
            // Clear invalid token
            localStorage.removeItem('auth_token');
            localStorage.removeItem('user');

            // Redirect to login page
            if (router.currentRoute.value.name !== 'login') {
                router.push({ name: 'login' });
            }
        }
        return Promise.reject(error);
    }
);

export default api;
