<script setup lang="ts">
import AppNavbar from '@/components/AppNavbar.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import PlayerBar from '@/components/PlayerBar.vue';
import { useRemotePlayer } from '@/composables/useRemotePlayer';

// Initialize remote player for multi-device support
useRemotePlayer();
</script>

<template>
    <div class="h-screen bg-surface-950 text-surface-50 flex flex-col transition-colors duration-300">
        <AppNavbar />

        <div class="flex flex-1 overflow-hidden">
            <AppSidebar />

            <main class="flex-1 overflow-y-auto p-6">
                <slot />
            </main>
        </div>

        <PlayerBar />
    </div>
</template>
