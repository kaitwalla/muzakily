import { createRouter, createWebHistory, type RouteRecordRaw } from 'vue-router';
import { useAuthStore } from '@/stores/auth';

const routes: RouteRecordRaw[] = [
    {
        path: '/login',
        name: 'login',
        component: () => import('@/views/LoginView.vue'),
        meta: {
            layout: 'auth',
            requiresGuest: true,
        },
    },
    {
        path: '/',
        name: 'home',
        component: () => import('@/views/HomeView.vue'),
        meta: {
            requiresAuth: true,
        },
    },
    {
        path: '/songs',
        name: 'songs',
        component: () => import('@/views/SongsView.vue'),
        meta: {
            requiresAuth: true,
        },
    },
    {
        path: '/songs/incomplete',
        name: 'incomplete-songs',
        component: () => import('@/views/IncompleteSongsView.vue'),
        meta: {
            requiresAuth: true,
        },
    },
    {
        path: '/albums',
        name: 'albums',
        component: () => import('@/views/AlbumsView.vue'),
        meta: {
            requiresAuth: true,
        },
    },
    {
        path: '/albums/:slug',
        name: 'album-detail',
        component: () => import('@/views/AlbumDetailView.vue'),
        meta: {
            requiresAuth: true,
        },
    },
    {
        path: '/artists',
        name: 'artists',
        component: () => import('@/views/ArtistsView.vue'),
        meta: {
            requiresAuth: true,
        },
    },
    {
        path: '/artists/:slug',
        name: 'artist-detail',
        component: () => import('@/views/ArtistDetailView.vue'),
        meta: {
            requiresAuth: true,
        },
    },
    {
        path: '/playlists',
        name: 'playlists',
        component: () => import('@/views/PlaylistsView.vue'),
        meta: {
            requiresAuth: true,
        },
    },
    {
        path: '/playlists/:slug',
        name: 'playlist-detail',
        component: () => import('@/views/PlaylistDetailView.vue'),
        meta: {
            requiresAuth: true,
        },
    },
    {
        path: '/search',
        name: 'search',
        component: () => import('@/views/SearchView.vue'),
        meta: {
            requiresAuth: true,
        },
    },
    {
        path: '/settings',
        name: 'settings',
        component: () => import('@/views/SettingsView.vue'),
        meta: {
            requiresAuth: true,
        },
    },
    {
        path: '/upload',
        name: 'upload',
        component: () => import('@/views/UploadView.vue'),
        meta: {
            requiresAuth: true,
        },
    },
    {
        path: '/:pathMatch(.*)*',
        name: 'not-found',
        component: () => import('@/views/NotFoundView.vue'),
        meta: {
            layout: 'auth',
        },
    },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
});

router.beforeEach(async (to, _from, next) => {
    const authStore = useAuthStore();

    // Initialize auth state if not done
    if (!authStore.initialized) {
        await authStore.initialize();
    }

    const requiresAuth = to.meta.requiresAuth === true;
    const requiresGuest = to.meta.requiresGuest === true;

    if (requiresAuth && !authStore.isAuthenticated) {
        next({ name: 'login', query: { redirect: to.fullPath } });
    } else if (requiresGuest && authStore.isAuthenticated) {
        next({ name: 'home' });
    } else {
        next();
    }
});

export default router;
