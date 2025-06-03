import { createRouter, createWebHistory, RouteRecordRaw } from 'vue-router';
import { useAppStore } from '../stores/index';
import { useAuthStore } from '../stores/authStore';
import appSetting from '../app-setting';

import HomeView from '../views/index.vue';

const routes: RouteRecordRaw[] = [
    // dashboard
    {
        path: '/',
        name: 'home',
        component: HomeView,
        meta: { requiresAuth: true }
      },
    // apps
    {
        path: '/apps/chat',
        name: 'chat',
        component: () => import(/* webpackChunkName: "apps-chat" */ '../views/apps/chat.vue'),
        meta: { requiresAuth: true },
      },
    // authentication
    {
        path: '/auth/boxed-signin',
        name: 'boxed-signin',
        component: () => import(/* webpackChunkName: "auth-boxed-signin" */ '../views/auth/boxed-signin.vue'),
        meta: { layout: 'auth', guestOnly: true },
    },
    {
        path: '/auth/boxed-signup',
        name: 'boxed-signup',
        component: () => import(/* webpackChunkName: "auth-boxed-signup" */ '../views/auth/boxed-signup.vue'),
        meta: { layout: 'auth' },
    },
    {
        path: '/auth/boxed-lockscreen',
        name: 'boxed-lockscreen',
        component: () => import(/* webpackChunkName: "auth-boxed-lockscreen" */ '../views/auth/boxed-lockscreen.vue'),
        meta: { layout: 'auth' },
    },
    {
        path: '/auth/boxed-password-reset',
        name: 'boxed-password-reset',
        component: () => import(/* webpackChunkName: "auth-boxed-password-reset" */ '../views/auth/boxed-password-reset.vue'),
        meta: { layout: 'auth' },
    },
    {
        path: '/:pathMatch(.*)*', // Matches all unmatched paths
        name: 'NotFound',
        component: () => import('../views/pages/error404.vue'), // Your 404 component
        meta: { layout: 'auth' },
      }
];

const router = createRouter({
    history: createWebHistory(),
    linkExactActiveClass: 'active',
    routes,
    scrollBehavior(to, from, savedPosition) {
        if (savedPosition) {
            return savedPosition;
        } else {
            return { left: 0, top: 0 };
        }
    },
});

router.beforeEach(async (to, from, next) => {
    const store = useAppStore();
    const authStore = useAuthStore();

    // Set layout
    if (to?.meta?.layout == 'auth') {
        store.setMainLayout('auth');
    } else {
        store.setMainLayout('app');
    }

    // Check if auth system needs initialization
    if (!authStore.isInitialized) {
        await authStore.fetchUser();
    }

    // Handle protected routes
    if (to.meta.requiresAuth) {
        if (!authStore.isAuthenticated) {
            return {
                name: 'boxed-signin',
                query: { redirect: to.fullPath }, // Save redirect path
            };
        }
    }

    // Redirect authenticated users from guest routes
    if (to.meta.guestOnly && authStore.isAuthenticated) {
        return { name: 'home' };
    }
});

router.afterEach((to, from, next) => {
    appSetting.changeAnimation();
});
export default router;
