import { apiClient, setAuthToken, clearAuthToken } from './client';
import type { User, LoginRequest, RegisterRequest, AuthResponse, UpdateProfileRequest } from '@/types/auth';

export async function login(credentials: LoginRequest): Promise<AuthResponse> {
    const response = await apiClient.post<{ data: AuthResponse }>('/auth/login', credentials);
    setAuthToken(response.data.data.token);
    return response.data.data;
}

export async function register(data: RegisterRequest): Promise<AuthResponse> {
    const response = await apiClient.post<{ data: AuthResponse }>('/auth/register', data);
    setAuthToken(response.data.data.token);
    return response.data.data;
}

export async function logout(): Promise<void> {
    try {
        await apiClient.post('/auth/logout');
    } finally {
        clearAuthToken();
    }
}

export async function getCurrentUser(): Promise<User> {
    const response = await apiClient.get<{ data: User }>('/auth/me');
    return response.data.data;
}

export async function updateProfile(data: UpdateProfileRequest): Promise<User> {
    const response = await apiClient.patch<{ data: User }>('/auth/me', data);
    return response.data.data;
}
