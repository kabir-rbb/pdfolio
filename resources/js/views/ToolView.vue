<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import FileDropzone from '../components/FileDropzone.vue';
import ToolIcon from '../components/ToolIcon.vue';

const route = useRoute();
const router = useRouter();

const tool = ref(null);
const notFound = ref(false);
const files = ref([]);
const options = reactive({});
const state = ref('idle'); // idle | working | done | error
const errorMessage = ref('');

const bannerClasses = {
    brand: 'from-brand-600 to-brand-500',
    tangerine: 'from-tangerine-500 to-tangerine-400',
    mint: 'from-mint-500 to-mint-400',
};
const chipClasses = {
    brand: 'bg-brand-100 text-brand-600',
    tangerine: 'bg-tangerine-100 text-tangerine-500',
    mint: 'bg-mint-100 text-mint-500',
};

async function loadTool(slug) {
    state.value = 'idle';
    errorMessage.value = '';
    files.value = [];
    notFound.value = false;
    tool.value = null;

    const res = await fetch('/api/tools');
    const tools = await res.json();
    const found = tools.find((t) => t.slug === slug);
    if (!found) {
        notFound.value = true;
        return;
    }
    tool.value = found;
    Object.keys(options).forEach((k) => delete options[k]);
    for (const opt of found.options) {
        options[opt.name] = opt.default;
    }
    document.title = found.title + ' — PDFolio';
}

watch(() => route.params.slug, (slug) => slug && loadTool(slug), { immediate: true });

const canSubmit = computed(() => {
    if (!tool.value || state.value === 'working') return false;
    return files.value.length >= tool.value.minFiles;
});

function optionVisible(opt) {
    if (!opt.showIf) return true;
    return opt.showIf.values.includes(options[opt.showIf.field]);
}

async function submit() {
    if (!canSubmit.value) return;
    state.value = 'working';
    errorMessage.value = '';

    const body = new FormData();
    if (tool.value.multiple) {
        files.value.forEach((f) => body.append('files[]', f));
    } else {
        body.append('file', files.value[0]);
    }
    for (const opt of tool.value.options) {
        body.append(opt.name, options[opt.name] ?? '');
    }

    try {
        const res = await fetch('/api/tools/' + tool.value.slug, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '',
            },
            body,
        });

        if (!res.ok) {
            let message = 'Something went wrong while processing your file.';
            try {
                const data = await res.json();
                if (data.errors) {
                    message = Object.values(data.errors).flat().join(' ');
                } else if (data.message) {
                    message = data.message;
                }
            } catch { /* keep default */ }
            throw new Error(message);
        }

        const blob = await res.blob();
        const disposition = res.headers.get('Content-Disposition') || '';
        const match = disposition.match(/filename="?([^";]+)"?/);
        const filename = match ? match[1] : 'pdfolio-output';

        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        a.remove();
        URL.revokeObjectURL(url);

        state.value = 'done';
    } catch (err) {
        errorMessage.value = err.message;
        state.value = 'error';
    }
}

function reset() {
    files.value = [];
    state.value = 'idle';
    errorMessage.value = '';
}
</script>

<template>
    <div v-if="notFound" class="mx-auto max-w-xl px-4 py-24 text-center">
        <p class="text-6xl font-extrabold text-brand-200">404</p>
        <h1 class="mt-4 text-2xl font-bold text-brand-900">That tool doesn't exist</h1>
        <button class="mt-6 rounded-xl bg-brand-600 px-6 py-3 font-bold text-white transition hover:bg-brand-700" @click="router.push('/')">
            Browse all tools
        </button>
    </div>

    <div v-else-if="tool" class="mx-auto max-w-6xl px-4 py-10 sm:px-6">
        <!-- Heading -->
        <div class="text-center">
            <span class="inline-flex h-14 w-14 items-center justify-center rounded-2xl" :class="chipClasses[tool.color]">
                <ToolIcon :name="tool.icon" size="h-7 w-7" />
            </span>
            <h1 class="mt-4 text-3xl font-extrabold tracking-tight text-brand-900 sm:text-4xl">{{ tool.title }}</h1>
            <p class="mx-auto mt-2 max-w-xl text-slate-500">{{ tool.description }}</p>
        </div>

        <!-- Success -->
        <div v-if="state === 'done'" class="mx-auto mt-10 max-w-xl rounded-2xl border border-mint-200 bg-mint-50 p-10 text-center">
            <span class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-mint-400 text-white">
                <svg class="h-8 w-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="m5 13 4 4L19 7"/></svg>
            </span>
            <h2 class="mt-4 text-2xl font-extrabold text-mint-600">Done!</h2>
            <p class="mt-1 text-sm text-slate-500">Your file has been processed and the download should have started.</p>
            <div class="mt-6 flex flex-col justify-center gap-3 sm:flex-row">
                <button class="rounded-xl bg-brand-600 px-6 py-3 font-bold text-white transition hover:bg-brand-700" @click="reset">
                    Process another file
                </button>
                <button class="rounded-xl border border-brand-200 bg-white px-6 py-3 font-bold text-brand-700 transition hover:bg-brand-50" @click="router.push('/')">
                    All tools
                </button>
            </div>
        </div>

        <!-- Workspace -->
        <div v-else class="mt-10 grid grid-cols-1 gap-6 lg:grid-cols-3">
            <!-- Dropzone -->
            <div class="lg:col-span-2">
                <FileDropzone
                    v-model="files"
                    :multiple="tool.multiple"
                    :accept="tool.accept"
                    :accept-label="tool.acceptLabel"
                    :color="tool.color"
                />
            </div>

            <!-- Options + action -->
            <div class="flex flex-col gap-4">
                <div v-if="tool.options.length" class="rounded-2xl border border-brand-100 bg-white p-5 shadow-sm">
                    <h3 class="text-sm font-extrabold uppercase tracking-wide text-slate-400">Options</h3>
                    <div class="mt-4 space-y-4">
                        <template v-for="opt in tool.options" :key="opt.name">
                            <div v-if="optionVisible(opt)">
                                <label class="mb-1.5 block text-sm font-semibold text-slate-600">{{ opt.label }}</label>
                                <select
                                    v-if="opt.type === 'select'"
                                    v-model="options[opt.name]"
                                    class="w-full rounded-xl border border-brand-200 bg-white px-3.5 py-2.5 text-sm font-medium text-slate-700 outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-200"
                                >
                                    <option v-for="choice in opt.choices" :key="choice.value" :value="choice.value">{{ choice.label }}</option>
                                </select>
                                <input
                                    v-else
                                    v-model="options[opt.name]"
                                    :type="opt.type === 'password' ? 'password' : opt.type"
                                    :placeholder="opt.placeholder || ''"
                                    class="w-full rounded-xl border border-brand-200 bg-white px-3.5 py-2.5 text-sm font-medium text-slate-700 outline-none transition placeholder:text-slate-300 focus:border-brand-500 focus:ring-2 focus:ring-brand-200"
                                />
                            </div>
                        </template>
                    </div>
                </div>

                <div v-else class="rounded-2xl border border-brand-100 bg-white p-5 text-sm text-slate-500 shadow-sm">
                    No configuration needed — just add your
                    {{ tool.acceptLabel }} and hit the button below.
                </div>

                <!-- Error -->
                <div v-if="state === 'error'" class="rounded-xl border border-tangerine-300 bg-tangerine-50 px-4 py-3 text-sm font-semibold text-tangerine-600">
                    {{ errorMessage }}
                </div>

                <button
                    class="relative flex items-center justify-center gap-2 rounded-2xl bg-gradient-to-r px-6 py-4 text-lg font-extrabold text-white shadow-lg transition enabled:hover:-translate-y-0.5 enabled:hover:shadow-xl disabled:cursor-not-allowed disabled:opacity-40"
                    :class="[bannerClasses[tool.color] || bannerClasses.brand, state === 'working' ? 'opacity-80' : '']"
                    :disabled="!canSubmit"
                    @click="submit"
                >
                    <svg v-if="state === 'working'" class="h-5 w-5 animate-spin" viewBox="0 0 24 24" fill="none">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                        <path class="opacity-90" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4z" />
                    </svg>
                    {{ state === 'working' ? 'Processing…' : tool.action }}
                </button>

                <p v-if="files.length && files.length < tool.minFiles" class="text-center text-xs font-semibold text-tangerine-500">
                    This tool needs at least {{ tool.minFiles }} files.
                </p>
            </div>
        </div>
    </div>

    <!-- Loading skeleton -->
    <div v-else class="mx-auto max-w-6xl px-4 py-10 sm:px-6">
        <div class="mx-auto h-10 w-64 animate-pulse rounded-xl bg-brand-100"></div>
        <div class="mt-10 grid grid-cols-1 gap-6 lg:grid-cols-3">
            <div class="h-72 animate-pulse rounded-2xl bg-brand-50 lg:col-span-2"></div>
            <div class="h-72 animate-pulse rounded-2xl bg-brand-50"></div>
        </div>
    </div>
</template>
