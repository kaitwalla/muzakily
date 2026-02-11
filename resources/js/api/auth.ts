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
    // Use FormData if avatar is present
    if (data.avatar) {
        const formData = new FormData();
        formData.append('avatar', data.avatar);
        if (data.name) formData.append('name', data.name);
        if (data.current_password) formData.append('current_password', data.current_password);
        if (data.password) formData.append('password', data.password);
        if (data.password_confirmation) formData.append('password_confirmation', data.password_confirmation);
        if (data.preferences) {
            Object.entries(data.preferences).forEach(([key, value]) => {
                if (value !== undefined) {
                    formData.append(`preferences[${key}]`, String(value));
                }
            });
        }

        // Use POST with _method override for multipart/form-data
        formData.append('_method', 'PATCH');
        const response = await apiClient.post<{ data: User }>('/auth/me', formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        return response.data.data;
    }

    const response = await apiClient.patch<{ data: User }>('/auth/me', data);
    return response.data.data;
}
