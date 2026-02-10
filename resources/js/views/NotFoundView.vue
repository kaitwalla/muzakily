<script setup lang="ts">
import { RouterLink } from 'vue-router';
</script>

<template>
    <div class="bg-gray-800 rounded-lg p-8 shadow-xl text-center">
        <h1 class="text-6xl font-bold text-white mb-4">404</h1>
        <p class="text-xl text-gray-400 mb-8">Page not found</p>
        <p class="text-gray-500 mb-8">
            The page you're looking for doesn't exist or has been moved.
        </p>
        <RouterLink
            to="/"
            class="inline-block py-3 px-6 bg-green-500 hover:bg-green-600 text-white font-semibold rounded-lg transition-colors"
        >
            Go Home
        </RouterLink>
    </div>
</template>
