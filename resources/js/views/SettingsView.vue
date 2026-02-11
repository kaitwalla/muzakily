<script setup lang="ts">
import { computed, ref, onUnmounted } from 'vue';
import { useAuthStore } from '@/stores/auth';

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
        <section class="bg-gray-800 rounded-lg p-6 mb-6">
            <h2 class="text-xl font-semibold text-white mb-4">Profile</h2>
            <div v-if="saveError" class="mb-4 p-3 bg-red-500/20 border border-red-500 rounded-lg text-red-400 text-sm">
                {{ saveError }}
            </div>

            <div class="flex items-start gap-6">
                <!-- Avatar -->
                <div class="flex-shrink-0">
                    <div
                        class="relative w-24 h-24 rounded-full overflow-hidden bg-gray-700 cursor-pointer group focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 focus:ring-offset-gray-800"
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
                            class="w-full h-full flex items-center justify-center text-3xl font-bold text-gray-400"
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
                        <label class="block text-sm font-medium text-gray-400 mb-1">Name</label>
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
                                class="flex-1 bg-gray-700 border border-gray-600 rounded-lg px-3 py-1.5 text-white text-sm"
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
                                class="px-3 py-1.5 text-gray-400 hover:text-white text-sm"
                            >
                                Cancel
                            </button>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-400 mb-1">Email</label>
                        <p class="text-white">{{ authStore.user?.email ?? '-' }}</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Password Section -->
        <section class="bg-gray-800 rounded-lg p-6 mb-6">
            <h2 class="text-xl font-semibold text-white mb-4">Change Password</h2>
            <div v-if="passwordError" class="mb-4 p-3 bg-red-500/20 border border-red-500 rounded-lg text-red-400 text-sm">
                {{ passwordError }}
            </div>
            <div class="space-y-4 max-w-md">
                <div>
                    <label class="block text-sm font-medium text-gray-400 mb-1">Current Password</label>
                    <input
                        v-model="currentPassword"
                        type="password"
                        autocomplete="current-password"
                        class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white"
                    />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-400 mb-1">New Password</label>
                    <input
                        v-model="newPassword"
                        type="password"
                        autocomplete="new-password"
                        class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white"
                    />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-400 mb-1">Confirm New Password</label>
                    <input
                        v-model="confirmPassword"
                        type="password"
                        autocomplete="new-password"
                        class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white"
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

        <!-- Playback Section -->
        <section class="bg-gray-800 rounded-lg p-6">
            <h2 class="text-xl font-semibold text-white mb-4">Playback</h2>
            <div class="space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-white font-medium">Audio Quality</p>
                        <p class="text-gray-400 text-sm">Stream in the highest quality available</p>
                    </div>
                    <select
                        v-model="audioQuality"
                        :disabled="saving"
                        class="bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white disabled:opacity-50"
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
                        <p class="text-gray-400 text-sm">Smooth transition between songs</p>
                    </div>
                    <select
                        v-model.number="crossfade"
                        :disabled="saving"
                        class="bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-white disabled:opacity-50"
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
