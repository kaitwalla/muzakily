export interface UserPreferences {
    audio_quality?: 'auto' | 'high' | 'normal' | 'low';
    crossfade?: 0 | 3 | 5 | 10;
}

export interface User {
    id: number;
    uuid: string;
    name: string;
    email: string;
    role: string;
    preferences: UserPreferences;
    created_at: string;
}

export interface UpdateProfileRequest {
    name?: string;
    preferences?: Partial<UserPreferences>;
}

export interface LoginRequest {
    email: string;
    password: string;
}

export interface RegisterRequest {
    name: string;
    email: string;
    password: string;
    password_confirmation: string;
}

export interface AuthResponse {
    user: User;
    token: string;
}
