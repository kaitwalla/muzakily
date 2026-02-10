<script setup lang="ts">
import AppNavbar from '@/components/AppNavbar.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import PlayerBar from '@/components/PlayerBar.vue';
</script>

<template>
    <div class="min-h-screen bg-gray-900 text-white flex flex-col">
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
