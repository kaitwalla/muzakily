import { apiClient, setAuthToken, clearAuthToken } from './client';
import type { User, LoginRequest, RegisterRequest, AuthResponse } from '@/types/auth';

export async function login(credentials: LoginRequest): Promise<AuthResponse> {
    const response = await apiClient.post<AuthResponse>('/auth/login', credentials);
    setAuthToken(response.data.token);
    return response.data;
}

export async function register(data: RegisterRequest): Promise<AuthResponse> {
    const response = await apiClient.post<AuthResponse>('/auth/register', data);
    setAuthToken(response.data.token);
    return response.data;
}

export async function logout(): Promise<void> {
    try {
        await apiClient.post('/auth/logout');
    } finally {
        clearAuthToken();
    }
}

export async function getCurrentUser(): Promise<User> {
    const response = await apiClient.get<{ data: User }>('/auth/user');
    return response.data.data;
}
