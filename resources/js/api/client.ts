import axios, { type AxiosInstance, type AxiosError } from 'axios';
import type { ApiError } from '@/types/api';

const TOKEN_KEY = 'auth_token';

export const apiClient: AxiosInstance = axios.create({
    baseURL: '/api/v1',
    headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    },
});

// Request interceptor: inject auth token
apiClient.interceptors.request.use(
    (config) => {
        const token = localStorage.getItem(TOKEN_KEY);
        if (token) {
            config.headers.Authorization = `Bearer ${token}`;
        }
        return config;
    },
    (error) => Promise.reject(error)
);

// Response interceptor: handle 401 errors
apiClient.interceptors.response.use(
    (response) => response,
    (error: AxiosError<ApiError>) => {
        const isAuthEndpoint = error.config?.url?.startsWith('/auth/');
        const isOnLoginPage = window.location.pathname === '/login';

        if (error.response?.status === 401 && !isAuthEndpoint && !isOnLoginPage) {
            localStorage.removeItem(TOKEN_KEY);
            window.location.href = '/login';
        }
        return Promise.reject(error);
    }
);

export function setAuthToken(token: string): void {
    localStorage.setItem(TOKEN_KEY, token);
}

export function clearAuthToken(): void {
    localStorage.removeItem(TOKEN_KEY);
}

export function getAuthToken(): string | null {
    return localStorage.getItem(TOKEN_KEY);
}
