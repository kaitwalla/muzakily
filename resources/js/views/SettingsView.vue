<script setup lang="ts">
import { computed, ref, onMounted, onUnmounted } from 'vue';
import { useAuthStore } from '@/stores/auth';
import { listTokens, createToken, revokeToken } from '@/api/auth';
import { getStats } from '@/api/stats';
import type { ApiToken, NewApiToken } from '@/types/auth';
import type { LibraryStats } from '@/api/stats';

const authStore = useAuthStore();
const saving = ref(false);

const saveError = ref<string | null>(null);
const successMessage = ref<string | null>(null);

// Profile editing
const editingName = ref(false);
const nameInput = ref('');
const avatarInput = ref<HTMLInputElement | null>(null);

// Password change
const currentPassword = ref('');
const newPassword = ref('');
const confirmPassword = ref('');
const passwordError = ref<string | null>(null);
const changingPassword = ref(false);

// Timer cleanup
let successTimeout: ReturnType<typeof setTimeout> | null = null;

onUnmounted(() => {
    if (successTimeout) clearTimeout(successTimeout);
});

function startEditingName(): void {
    nameInput.value = authStore.user?.name ?? '';
    editingName.value = true;
}

function cancelEditingName(): void {
    editingName.value = false;
    nameInput.value = '';
}

async function saveName(): Promise<void> {
    if (saving.value || !nameInput.value.trim()) return;
    saving.value = true;
    saveError.value = null;
    try {
        await authStore.updateProfile({ name: nameInput.value.trim() });
        editingName.value = false;
        nameInput.value = '';
        showSuccess('Name updated successfully');
    } catch {
        saveError.value = 'Failed to save name';
    } finally {
        saving.value = false;
    }
}

function triggerAvatarUpload(): void {
    avatarInput.value?.click();
}

function handleAvatarKeydown(event: KeyboardEvent): void {
    if (event.key === 'Enter' || event.key === ' ') {
        event.preventDefault();
        triggerAvatarUpload();
    }
}

async function handleAvatarChange(event: Event): Promise<void> {
    const target = event.target as HTMLInputElement;
    const file = target.files?.[0];
    if (!file) return;

    saving.value = true;
    saveError.value = null;
    try {
        await authStore.updateProfile({ avatar: file });
        showSuccess('Avatar updated successfully');
    } catch {
        saveError.value = 'Failed to upload avatar';
    } finally {
        saving.value = false;
        target.value = '';
    }
}

async function changePassword(): Promise<void> {
    if (changingPassword.value) return;

    passwordError.value = null;

    if (!currentPassword.value || !newPassword.value || !confirmPassword.value) {
        passwordError.value = 'All password fields are required';
        return;
    }

    if (newPassword.value !== confirmPassword.value) {
        passwordError.value = 'New passwords do not match';
        return;
    }

    if (newPassword.value.length < 8) {
        passwordError.value = 'Password must be at least 8 characters';
        return;
    }

    changingPassword.value = true;
    try {
        await authStore.updateProfile({
            current_password: currentPassword.value,
            password: newPassword.value,
            password_confirmation: confirmPassword.value,
        });
        currentPassword.value = '';
        newPassword.value = '';
        confirmPassword.value = '';
        showSuccess('Password changed successfully');
    } catch {
        passwordError.value = 'Failed to change password. Please check your current password.';
    } finally {
        changingPassword.value = false;
    }
}

function showSuccess(message: string): void {
    if (successTimeout) clearTimeout(successTimeout);
    successMessage.value = message;
    successTimeout = setTimeout(() => {
        successMessage.value = null;
        successTimeout = null;
    }, 3000);
}

const audioQuality = computed({
    get: () => authStore.user?.preferences?.audio_quality ?? 'auto',
    set: async (value: string) => {
        if (saving.value) return;
        saving.value = true;
        saveError.value = null;
        try {
            await authStore.updatePreferences({ audio_quality: value as 'auto' | 'high' | 'normal' | 'low' });
        } catch {
            saveError.value = 'Failed to save audio quality setting';
        } finally {
            saving.value = false;
        }
    },
});

const crossfade = computed({
    get: () => authStore.user?.preferences?.crossfade ?? 0,
    set: async (value: number) => {
        if (saving.value) return;
        saving.value = true;
        saveError.value = null;
        try {
            await authStore.updatePreferences({ crossfade: value as 0 | 3 | 5 | 10 });
        } catch {
            saveError.value = 'Failed to save crossfade setting';
        } finally {
            saving.value = false;
        }
    },
});

const userInitial = computed(() => {
    return authStore.user?.name?.charAt(0).toUpperCase() ?? '?';
});

// Library stats
const stats = ref<LibraryStats | null>(null);

function formatDuration(seconds: number): string {
    const h = Math.floor(seconds / 3600);
    const m = Math.floor((seconds % 3600) / 60);
    if (h >= 24) {
        const days = Math.floor(h / 24);
        const hours = h % 24;
        return hours > 0 ? `${days}d ${hours}h` : `${days}d`;
    }
    return h > 0 ? `${h}h ${m}m` : `${m}m`;
}

function formatSize(bytes: number): string {
    if (bytes >= 1_073_741_824) return `${(bytes / 1_073_741_824).toFixed(1)} GB`;
    if (bytes >= 1_048_576) return `${(bytes / 1_048_576).toFixed(0)} MB`;
    return `${(bytes / 1024).toFixed(0)} KB`;
}

// API Tokens
const tokens = ref<ApiToken[]>([]);
const newTokenName = ref('');
const creatingToken = ref(false);
const tokenError = ref<string | null>(null);
const revealedToken = ref<NewApiToken | null>(null);
const copiedTokenId = ref<number | null>(null);

onMounted(async () => {
    await Promise.allSettled([
        listTokens().then(t => { tokens.value = t; }),
        getStats().then(s => { stats.value = s; }),
    ]);
});

async function handleCreateToken(): Promise<void> {
    if (creatingToken.value || !newTokenName.value.trim()) return;
    creatingToken.value = true;
    tokenError.value = null;
    try {
        const created = await createToken(newTokenName.value.trim());
        revealedToken.value = created;
        tokens.value.unshift(created);
        newTokenName.value = '';
    } catch {
        tokenError.value = 'Failed to create token';
    } finally {
        creatingToken.value = false;
    }
}

async function handleRevokeToken(id: number): Promise<void> {
    try {
        await revokeToken(id);
        tokens.value = tokens.value.filter(t => t.id !== id);
        if (revealedToken.value?.id === id) revealedToken.value = null;
    } catch {
        tokenError.value = 'Failed to revoke token';
    }
}

async function copyToClipboard(text: string, id: number): Promise<void> {
    await navigator.clipboard.writeText(text);
    copiedTokenId.value = id;
    setTimeout(() => { copiedTokenId.value = null; }, 2000);
}

function formatDate(iso: string): string {
    return new Date(iso).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
}
</script>

<template>
    <div class="max-w-2xl">
        <h1 class="text-3xl font-bold text-white mb-6">Settings</h1>

        <!-- Success message -->
        <div
            v-if="successMessage"
            class="mb-4 p-3 bg-green-500/20 border border-green-500 rounded-lg text-green-400 text-sm"
        >
            {{ successMessage }}
        </div>

        <!-- Profile Section -->
        <section class="bg-surface-800 rounded-lg p-6 mb-6">
            <h2 class="text-xl font-semibold text-white mb-4">Profile</h2>
            <div v-if="saveError" class="mb-4 p-3 bg-red-500/20 border border-red-500 rounded-lg text-red-400 text-sm">
                {{ saveError }}
            </div>

            <div class="flex items-start gap-6">
                <!-- Avatar -->
                <div class="flex-shrink-0">
                    <div
                        class="relative w-24 h-24 rounded-full overflow-hidden bg-surface-700 cursor-pointer group focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 focus:ring-offset-surface-800"
                        role="button"
                        tabindex="0"
                        aria-label="Upload avatar"
                        @click="triggerAvatarUpload"
                        @keydown="handleAvatarKeydown"
                    >
                        <img
                            v-if="authStore.user?.avatar_url"
                            :src="authStore.user.avatar_url"
                            :alt="authStore.user.name"
                            class="w-full h-full object-cover"
                        />
                        <div
                            v-else
                            class="w-full h-full flex items-center justify-center text-3xl font-bold text-surface-400"
                        >
                            {{ userInitial }}
                        </div>
                        <!-- Hover overlay -->
                        <div
                            class="absolute inset-0 bg-black/50 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity"
                        >
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke-width="1.5"
                                stroke="currentColor"
                                class="w-8 h-8 text-white"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M6.827 6.175A2.31 2.31 0 015.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 00-1.134-.175 2.31 2.31 0 01-1.64-1.055l-.822-1.316a2.192 2.192 0 00-1.736-1.039 48.774 48.774 0 00-5.232 0 2.192 2.192 0 00-1.736 1.039l-.821 1.316z"
                                />
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M16.5 12.75a4.5 4.5 0 11-9 0 4.5 4.5 0 019 0zM18.75 10.5h.008v.008h-.008V10.5z"
                                />
                            </svg>
                        </div>
                    </div>
                    <input
                        ref="avatarInput"
                        type="file"
                        accept="image/*"
                        class="hidden"
                        @change="handleAvatarChange"
                    />
                </div>

                <!-- Name and Email -->
                <div class="flex-1 space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-surface-400 mb-1">Name</label>
                        <div v-if="!editingName" class="flex items-center gap-2">
                            <p class="text-white">{{ authStore.user?.name ?? '-' }}</p>
                            <button
                                @click="startEditingName"
                                class="text-primary-400 hover:text-primary-300 text-sm"
                            >
                                Edit
                            </button>
                        </div>
                        <div v-else class="flex items-center gap-2">
                            <input
                                v-model="nameInput"
                                type="text"
                                class="flex-1 bg-surface-700 border border-surface-600 rounded-lg px-3 py-1.5 text-white text-sm"
                                @keyup.enter="saveName"
                                @keyup.escape="cancelEditingName"
                            />
                            <button
                                @click="saveName"
                                :disabled="saving"
                                class="px-3 py-1.5 bg-primary-500 hover:bg-primary-600 text-white text-sm font-medium rounded-lg disabled:opacity-50"
                            >
                                Save
                            </button>
                            <button
                                @click="cancelEditingName"
                                class="px-3 py-1.5 text-surface-400 hover:text-white text-sm"
                            >
                                Cancel
                            </button>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-surface-400 mb-1">Email</label>
                        <p class="text-white">{{ authStore.user?.email ?? '-' }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-surface-400 mb-1">User ID</label>
                        <div class="flex items-center gap-2">
                            <p class="text-surface-300 font-mono text-sm">{{ authStore.user?.uuid ?? '-' }}</p>
                            <button
                                v-if="authStore.user?.uuid"
                                @click="copyToClipboard(authStore.user!.uuid, -1)"
                                class="text-primary-400 hover:text-primary-300 text-xs"
                            >
                                {{ copiedTokenId === -1 ? 'Copied!' : 'Copy' }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Library Stats Section -->
        <section class="bg-surface-800 rounded-lg p-6 mb-6">
            <h2 class="text-xl font-semibold text-white mb-4">Library</h2>
            <div v-if="stats" class="grid grid-cols-2 gap-4">
                <div class="bg-surface-700 rounded-lg p-4">
                    <p class="text-surface-400 text-sm">Songs</p>
                    <p class="text-white text-2xl font-semibold mt-1">{{ stats.songs.toLocaleString() }}</p>
                </div>
                <div class="bg-surface-700 rounded-lg p-4">
                    <p class="text-surface-400 text-sm">Albums</p>
                    <p class="text-white text-2xl font-semibold mt-1">{{ stats.albums.toLocaleString() }}</p>
                </div>
                <div class="bg-surface-700 rounded-lg p-4">
                    <p class="text-surface-400 text-sm">Artists</p>
                    <p class="text-white text-2xl font-semibold mt-1">{{ stats.artists.toLocaleString() }}</p>
                </div>
                <div class="bg-surface-700 rounded-lg p-4">
                    <p class="text-surface-400 text-sm">Playlists</p>
                    <p class="text-white text-2xl font-semibold mt-1">{{ stats.playlists.toLocaleString() }}</p>
                </div>
                <div class="bg-surface-700 rounded-lg p-4">
                    <p class="text-surface-400 text-sm">Total Duration</p>
                    <p class="text-white text-2xl font-semibold mt-1">{{ formatDuration(stats.total_duration) }}</p>
                </div>
                <div class="bg-surface-700 rounded-lg p-4">
                    <p class="text-surface-400 text-sm">Storage Used</p>
                    <p class="text-white text-2xl font-semibold mt-1">{{ formatSize(stats.total_size) }}</p>
                </div>
            </div>
            <div v-else class="text-surface-500 text-sm">Loading...</div>
        </section>

        <!-- Password Section -->
        <section class="bg-surface-800 rounded-lg p-6 mb-6">
            <h2 class="text-xl font-semibold text-white mb-4">Change Password</h2>
            <div v-if="passwordError" class="mb-4 p-3 bg-red-500/20 border border-red-500 rounded-lg text-red-400 text-sm">
                {{ passwordError }}
            </div>
            <div class="space-y-4 max-w-md">
                <div>
                    <label class="block text-sm font-medium text-surface-400 mb-1">Current Password</label>
                    <input
                        v-model="currentPassword"
                        type="password"
                        autocomplete="current-password"
                        class="w-full bg-surface-700 border border-surface-600 rounded-lg px-4 py-2 text-white"
                    />
                </div>
                <div>
                    <label class="block text-sm font-medium text-surface-400 mb-1">New Password</label>
                    <input
                        v-model="newPassword"
                        type="password"
                        autocomplete="new-password"
                        class="w-full bg-surface-700 border border-surface-600 rounded-lg px-4 py-2 text-white"
                    />
                </div>
                <div>
                    <label class="block text-sm font-medium text-surface-400 mb-1">Confirm New Password</label>
                    <input
                        v-model="confirmPassword"
                        type="password"
                        autocomplete="new-password"
                        class="w-full bg-surface-700 border border-surface-600 rounded-lg px-4 py-2 text-white"
                    />
                </div>
                <button
                    @click="changePassword"
                    :disabled="changingPassword"
                    class="px-6 py-2 bg-primary-500 hover:bg-primary-600 text-white font-semibold rounded-lg transition-colors disabled:opacity-50"
                >
                    {{ changingPassword ? 'Changing...' : 'Change Password' }}
                </button>
            </div>
        </section>

        <!-- API Tokens Section -->
        <section class="bg-surface-800 rounded-lg p-6 mb-6">
            <h2 class="text-xl font-semibold text-white mb-1">API Tokens</h2>
            <p class="text-surface-400 text-sm mb-4">Use these tokens to authenticate the muzakily companion or other API clients.</p>

            <div v-if="tokenError" class="mb-4 p-3 bg-red-500/20 border border-red-500 rounded-lg text-red-400 text-sm">
                {{ tokenError }}
            </div>

            <!-- Newly created token — show once -->
            <div v-if="revealedToken" class="mb-4 p-4 bg-green-500/10 border border-green-500/50 rounded-lg">
                <p class="text-green-400 text-sm font-medium mb-2">Token created — copy it now, it won't be shown again.</p>
                <div class="flex items-center gap-2">
                    <code class="flex-1 bg-surface-900 rounded px-3 py-2 text-green-300 font-mono text-xs break-all">{{ revealedToken.token }}</code>
                    <button
                        @click="copyToClipboard(revealedToken!.token, revealedToken!.id)"
                        class="flex-shrink-0 px-3 py-2 bg-green-600 hover:bg-green-500 text-white text-xs font-medium rounded-lg transition-colors"
                    >
                        {{ copiedTokenId === revealedToken.id ? 'Copied!' : 'Copy' }}
                    </button>
                </div>
            </div>

            <!-- Create token form -->
            <div class="flex items-center gap-2 mb-6 max-w-md">
                <input
                    v-model="newTokenName"
                    type="text"
                    placeholder="Token name (e.g. Mac Companion)"
                    class="flex-1 bg-surface-700 border border-surface-600 rounded-lg px-3 py-2 text-white text-sm placeholder:text-surface-500"
                    @keyup.enter="handleCreateToken"
                />
                <button
                    @click="handleCreateToken"
                    :disabled="creatingToken || !newTokenName.trim()"
                    class="flex-shrink-0 px-4 py-2 bg-primary-500 hover:bg-primary-600 text-white text-sm font-medium rounded-lg transition-colors disabled:opacity-50"
                >
                    {{ creatingToken ? 'Creating...' : 'Create' }}
                </button>
            </div>

            <!-- Token list -->
            <div v-if="tokens.length > 0" class="space-y-2">
                <div
                    v-for="token in tokens"
                    :key="token.id"
                    class="flex items-center justify-between px-4 py-3 bg-surface-700 rounded-lg"
                >
                    <div>
                        <div class="flex items-center gap-2">
                            <p class="text-white text-sm font-medium">{{ token.name }}</p>
                            <span v-if="token.is_current" class="text-xs px-1.5 py-0.5 bg-primary-500/20 text-primary-400 rounded">current session</span>
                        </div>
                        <p class="text-surface-400 text-xs mt-0.5">
                            Created {{ formatDate(token.created_at) }}
                            <span v-if="token.last_used_at"> · Last used {{ formatDate(token.last_used_at) }}</span>
                        </p>
                    </div>
                    <button
                        @click="handleRevokeToken(token.id)"
                        class="text-surface-400 hover:text-red-400 text-sm transition-colors"
                    >
                        Revoke
                    </button>
                </div>
            </div>
            <p v-else class="text-surface-500 text-sm">No tokens yet.</p>
        </section>

        <!-- Playback Section -->
        <section class="bg-surface-800 rounded-lg p-6">
            <h2 class="text-xl font-semibold text-white mb-4">Playback</h2>
            <div class="space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-white font-medium">Audio Quality</p>
                        <p class="text-surface-400 text-sm">Stream in the highest quality available</p>
                    </div>
                    <select
                        v-model="audioQuality"
                        :disabled="saving"
                        class="bg-surface-700 border border-surface-600 rounded-lg px-4 py-2 text-white disabled:opacity-50"
                    >
                        <option value="auto">Auto</option>
                        <option value="high">High</option>
                        <option value="normal">Normal</option>
                        <option value="low">Low</option>
                    </select>
                </div>
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-white font-medium">Crossfade</p>
                        <p class="text-surface-400 text-sm">Smooth transition between songs</p>
                    </div>
                    <select
                        v-model.number="crossfade"
                        :disabled="saving"
                        class="bg-surface-700 border border-surface-600 rounded-lg px-4 py-2 text-white disabled:opacity-50"
                    >
                        <option :value="0">Off</option>
                        <option :value="3">3 seconds</option>
                        <option :value="5">5 seconds</option>
                        <option :value="10">10 seconds</option>
                    </select>
                </div>
            </div>
        </section>
    </div>
</template>
