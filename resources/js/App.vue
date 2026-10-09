<script setup>
import { RouterView, RouterLink } from 'vue-router';
</script>

<template>
    <div class="min-h-screen flex flex-col">
        <!-- Header -->
        <header class="sticky top-0 z-40 border-b border-brand-100 bg-white/85 backdrop-blur">
            <div class="mx-auto flex h-16 max-w-6xl items-center justify-between px-4 sm:px-6">
                <RouterLink to="/" class="flex items-center gap-2.5">
                    <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-brand-600 shadow-sm shadow-brand-600/30">
                        <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none">
                            <path d="M6 3h8l4 4v12a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z" fill="white" />
                            <path d="M14 3v4h4" fill="#ffb26b" />
                            <rect x="7.5" y="13" width="7" height="1.8" rx="0.9" fill="#2749e8" />
                            <rect x="7.5" y="16.5" width="4.5" height="1.8" rx="0.9" fill="#41d887" />
                        </svg>
                    </span>
                    <span class="text-xl font-extrabold tracking-tight text-brand-900">
                        PDF<span class="text-brand-600">olio</span>
                    </span>
                </RouterLink>
                <nav class="flex items-center gap-1 sm:gap-2">
                    <RouterLink
                        to="/"
                        class="rounded-lg px-3 py-2 text-sm font-semibold text-slate-600 transition hover:bg-brand-50 hover:text-brand-700"
                    >
                        All tools
                    </RouterLink>
                    <RouterLink
                        to="/tool/merge"
                        class="rounded-lg px-3 py-2 text-sm font-semibold text-slate-600 transition hover:bg-brand-50 hover:text-brand-700"
                    >
                        Merge
                    </RouterLink>
                    <RouterLink
                        to="/tool/compress"
                        class="rounded-lg px-3 py-2 text-sm font-semibold text-slate-600 transition hover:bg-brand-50 hover:text-brand-700"
                    >
                        Compress
                    </RouterLink>
                </nav>
            </div>
        </header>

        <!-- Main -->
        <main class="flex-1">
            <RouterView />
        </main>

        <!-- Footer -->
        <footer class="border-t border-brand-100 bg-white">
            <div class="mx-auto flex max-w-6xl flex-col items-center justify-between gap-3 px-4 py-8 text-sm text-slate-500 sm:flex-row sm:px-6">
                <p>PDFolio - By Kabir Maharjan © 2026</p>
                <div class="flex items-center gap-2 font-bold text-slate-600">
                    <span>RBBL</span>
                </div>
            </div>
        </footer>
    </div>
</template>
