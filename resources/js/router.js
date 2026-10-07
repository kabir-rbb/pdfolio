import { createRouter, createWebHistory } from 'vue-router';
import HomeView from './views/HomeView.vue';
import ToolView from './views/ToolView.vue';

export const router = createRouter({
    history: createWebHistory(),
    routes: [
        { path: '/', name: 'home', component: HomeView },
        { path: '/tool/:slug', name: 'tool', component: ToolView, props: true },
        { path: '/:pathMatch(.*)*', redirect: '/' },
    ],
    scrollBehavior() {
        return { top: 0 };
    },
});
