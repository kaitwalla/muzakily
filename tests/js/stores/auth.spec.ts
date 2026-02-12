import { describe, it, expect, beforeEach, vi } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import { useAuthStore } from '@/stores/auth';
import * as authApi from '@/api/auth';
import * as client from '@/api/client';
import type { User } from '@/types/auth';

vi.mock('@/api/auth');
vi.mock('@/api/client');

const createMockUser = (overrides: Partial<User> = {}): User => ({
    id: 1,
    name: 'Test User',
    email: 'test@example.com',
    role: 'user',
    preferences: {
        theme: 'dark',
        default_view: 'grid',
    },
    created_at: '2024-01-01T00:00:00Z',
    updated_at: '2024-01-01T00:00:00Z',
    ...overrides,
});

describe('useAuthStore', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        vi.clearAllMocks();
    });

    describe('initial state', () => {
        it('should have null user initially', () => {
            const store = useAuthStore();
            expect(store.user).toBeNull();
        });

        it('should not be authenticated initially', () => {
            const store = useAuthStore();
            expect(store.isAuthenticated).toBe(false);
        });

        it('should not be admin initially', () => {
            const store = useAuthStore();
            expect(store.isAdmin).toBe(false);
        });

        it('should not be loading initially', () => {
            const store = useAuthStore();
            expect(store.loading).toBe(false);
        });

        it('should not be initialized initially', () => {
            const store = useAuthStore();
            expect(store.initialized).toBe(false);
        });
    });

    describe('computed properties', () => {
        it('should return isAuthenticated true when user is set', () => {
            const store = useAuthStore();
            store.user = createMockUser();
            expect(store.isAuthenticated).toBe(true);
        });

        it('should return isAdmin true when user role is admin', () => {
            const store = useAuthStore();
            store.user = createMockUser({ role: 'admin' });
            expect(store.isAdmin).toBe(true);
        });

        it('should return isAdmin false when user role is user', () => {
            const store = useAuthStore();
            store.user = createMockUser({ role: 'user' });
            expect(store.isAdmin).toBe(false);
        });
    });

    describe('initialize', () => {
        it('should skip initialization when no token exists', async () => {
            vi.mocked(client.getAuthToken).mockReturnValue(null);

            const store = useAuthStore();
            await store.initialize();

            expect(store.initialized).toBe(true);
            expect(store.user).toBeNull();
            expect(authApi.getCurrentUser).not.toHaveBeenCalled();
        });

        it('should fetch user when token exists', async () => {
            const mockUser = createMockUser();
            vi.mocked(client.getAuthToken).mockReturnValue('test-token');
            vi.mocked(authApi.getCurrentUser).mockResolvedValue(mockUser);

            const store = useAuthStore();
            await store.initialize();

            expect(store.initialized).toBe(true);
            expect(store.user).toEqual(mockUser);
        });

        it('should clear user when API call fails', async () => {
            vi.mocked(client.getAuthToken).mockReturnValue('test-token');
            vi.mocked(authApi.getCurrentUser).mockRejectedValue(new Error('Unauthorized'));

            const store = useAuthStore();
            await store.initialize();

            expect(store.initialized).toBe(true);
            expect(store.user).toBeNull();
        });

        it('should only initialize once', async () => {
            vi.mocked(client.getAuthToken).mockReturnValue('test-token');
            vi.mocked(authApi.getCurrentUser).mockResolvedValue(createMockUser());

            const store = useAuthStore();
            await store.initialize();
            await store.initialize();

            expect(authApi.getCurrentUser).toHaveBeenCalledTimes(1);
        });
    });

    describe('login', () => {
        it('should set user on successful login', async () => {
            const mockUser = createMockUser();
            vi.mocked(authApi.login).mockResolvedValue({
                user: mockUser,
                token: 'test-token',
            });

            const store = useAuthStore();
            await store.login({ email: 'test@example.com', password: 'password' });

            expect(store.user).toEqual(mockUser);
        });

        it('should set loading during login', async () => {
            vi.mocked(authApi.login).mockImplementation(async () => {
                return new Promise((resolve) => {
                    setTimeout(() => resolve({
                        user: createMockUser(),
                        token: 'test-token',
                    }), 10);
                });
            });

            const store = useAuthStore();
            const loginPromise = store.login({ email: 'test@example.com', password: 'password' });

            expect(store.loading).toBe(true);
            await loginPromise;
            expect(store.loading).toBe(false);
        });

        it('should reset loading on login error', async () => {
            vi.mocked(authApi.login).mockRejectedValue(new Error('Invalid credentials'));

            const store = useAuthStore();

            await expect(store.login({ email: 'test@example.com', password: 'wrong' }))
                .rejects.toThrow();

            expect(store.loading).toBe(false);
        });
    });

    describe('register', () => {
        it('should set user on successful registration', async () => {
            const mockUser = createMockUser();
            vi.mocked(authApi.register).mockResolvedValue({
                user: mockUser,
                token: 'test-token',
            });

            const store = useAuthStore();
            await store.register({
                name: 'Test User',
                email: 'test@example.com',
                password: 'password',
                password_confirmation: 'password',
            });

            expect(store.user).toEqual(mockUser);
        });
    });

    describe('logout', () => {
        it('should clear user on logout', async () => {
            vi.mocked(authApi.logout).mockResolvedValue();

            const store = useAuthStore();
            store.user = createMockUser();

            await store.logout();

            expect(store.user).toBeNull();
        });
    });

    describe('updateProfile', () => {
        it('should update user on profile update', async () => {
            const updatedUser = createMockUser({ name: 'Updated Name' });
            vi.mocked(authApi.updateProfile).mockResolvedValue(updatedUser);

            const store = useAuthStore();
            store.user = createMockUser();

            await store.updateProfile({ name: 'Updated Name' });

            expect(store.user).toEqual(updatedUser);
        });
    });

    describe('updatePreferences', () => {
        it('should call updateProfile with preferences', async () => {
            const updatedUser = createMockUser({
                preferences: { theme: 'light', default_view: 'list' },
            });
            vi.mocked(authApi.updateProfile).mockResolvedValue(updatedUser);

            const store = useAuthStore();
            store.user = createMockUser();

            await store.updatePreferences({ theme: 'light' });

            expect(authApi.updateProfile).toHaveBeenCalledWith({
                preferences: { theme: 'light' },
            });
        });
    });
});
