<script setup>
import { onMounted, ref } from 'vue';
import { RouterLink } from 'vue-router';
import ToolIcon from '../components/ToolIcon.vue';

const tools = ref([]);
const loading = ref(true);

const chipClasses = {
    brand: 'bg-brand-100 text-brand-600 group-hover:bg-brand-600 group-hover:text-white',
    tangerine: 'bg-tangerine-100 text-tangerine-500 group-hover:bg-tangerine-400 group-hover:text-white',
    mint: 'bg-mint-100 text-mint-500 group-hover:bg-mint-400 group-hover:text-white',
};

onMounted(async () => {
    try {
        const res = await fetch('/api/tools');
        tools.value = await res.json();
    } finally {
        loading.value = false;
    }
});
</script>

<template>
    <!-- Hero -->
    <section class="relative overflow-hidden">
        <div class="pointer-events-none absolute -left-24 -top-24 h-72 w-72 rounded-full bg-brand-200/50 blur-3xl"></div>
        <div class="pointer-events-none absolute -right-20 top-10 h-64 w-64 rounded-full bg-tangerine-200/50 blur-3xl"></div>
        <div class="pointer-events-none absolute bottom-0 left-1/3 h-56 w-56 rounded-full bg-mint-200/50 blur-3xl"></div>

        <div class="relative mx-auto max-w-6xl px-4 pb-12 pt-16 text-center sm:px-6 sm:pt-20">
            <span class="inline-flex items-center gap-2 rounded-full border border-mint-200 bg-mint-50 px-4 py-1.5 text-xs font-bold text-mint-600">
                <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 22s8-3.6 8-10V5l-8-3-8 3v7c0 6.4 8 10 8 10z" />
                </svg>
                Private by design — files is not saved on the server
            </span>
            <h1 class="mx-auto mt-6 max-w-3xl text-4xl font-extrabold tracking-tight text-brand-900 sm:text-5xl">
                Every PDF tool you need,
                <span class="bg-gradient-to-r from-brand-600 via-tangerine-400 to-mint-500 bg-clip-text text-transparent">in one place</span>
            </h1>
            <p class="mx-auto mt-4 max-w-2xl text-lg text-slate-500">
                Merge, split, compress, convert, rotate and watermark PDFs.
            </p>
        </div>
    </section>

    <!-- Tool grid -->
    <section class="mx-auto max-w-6xl px-4 pb-20 sm:px-6">
        <div v-if="loading" class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div v-for="i in 6" :key="i" class="h-36 animate-pulse rounded-2xl border border-brand-100 bg-white"></div>
        </div>

        <div v-else class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <RouterLink
                v-for="tool in tools"
                :key="tool.slug"
                :to="'/tool/' + tool.slug"
                class="group rounded-2xl border border-brand-100 bg-white p-6 shadow-sm transition duration-200 hover:-translate-y-1 hover:border-brand-300 hover:shadow-lg hover:shadow-brand-600/10"
            >
                <div class="flex items-start justify-between">
                    <span
                        class="flex h-12 w-12 items-center justify-center rounded-xl transition"
                        :class="chipClasses[tool.color] || chipClasses.brand"
                    >
                        <ToolIcon :name="tool.icon" />
                    </span>
                    <span class="text-brand-300 transition group-hover:translate-x-1 group-hover:text-brand-600">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14m-6-6 6 6-6 6"/></svg>
                    </span>
                </div>
                <h2 class="mt-4 text-lg font-bold text-brand-900">{{ tool.title }}</h2>
                <p class="mt-1 text-sm leading-relaxed text-slate-500">{{ tool.description }}</p>
            </RouterLink>
        </div>
    </section>
</template>
