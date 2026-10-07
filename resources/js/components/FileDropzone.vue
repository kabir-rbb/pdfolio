<script setup>
import { ref } from 'vue';

const props = defineProps({
    multiple: { type: Boolean, default: false },
    accept: { type: String, default: '.pdf' },
    acceptLabel: { type: String, default: 'PDF files' },
    color: { type: String, default: 'brand' },
});

const files = defineModel({ type: Array, default: () => [] });

const dragging = ref(false);
const input = ref(null);

const colorText = {
    brand: 'text-brand-600',
    tangerine: 'text-tangerine-500',
    mint: 'text-mint-500',
};

function addFiles(list) {
    const incoming = Array.from(list || []);
    if (!incoming.length) return;
    if (props.multiple) {
        files.value = [...files.value, ...incoming];
    } else {
        files.value = [incoming[0]];
    }
}

function onDrop(e) {
    dragging.value = false;
    addFiles(e.dataTransfer.files);
}

function remove(index) {
    files.value = files.value.filter((_, i) => i !== index);
}

function move(index, dir) {
    const target = index + dir;
    if (target < 0 || target >= files.value.length) return;
    const copy = [...files.value];
    [copy[index], copy[target]] = [copy[target], copy[index]];
    files.value = copy;
}

function prettySize(bytes) {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
}
</script>

<template>
    <div>
        <div
            class="relative flex min-h-56 cursor-pointer flex-col items-center justify-center rounded-2xl border-2 border-dashed px-6 py-10 text-center transition"
            :class="dragging
                ? 'border-brand-500 bg-brand-50'
                : 'border-brand-200 bg-white hover:border-brand-400 hover:bg-brand-50/50'"
            @click="input.click()"
            @dragover.prevent="dragging = true"
            @dragleave.prevent="dragging = false"
            @drop.prevent="onDrop"
        >
            <input
                ref="input"
                type="file"
                class="hidden"
                :accept="accept"
                :multiple="multiple"
                @change="addFiles($event.target.files); $event.target.value = ''"
            />
            <div class="mb-3 flex h-14 w-14 items-center justify-center rounded-2xl bg-brand-100">
                <svg class="h-7 w-7 text-brand-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 16V4m0 0 4 4m-4-4-4 4" />
                    <path d="M4 15v3a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-3" />
                </svg>
            </div>
            <p class="text-lg font-bold text-brand-900">
                Drop {{ acceptLabel }} here
            </p>
            <p class="mt-1 text-sm text-slate-500">
                or <span class="font-semibold underline" :class="colorText[color]">browse your computer</span>
            </p>
            <p class="mt-3 text-xs text-slate-400">
                <span v-if="multiple">You can select several files · Max 100 MB each</span>
                <span v-else>One file · Max 100 MB</span>
            </p>
        </div>

        <!-- Selected files -->
        <ul v-if="files.length" class="mt-4 space-y-2">
            <li
                v-for="(file, i) in files"
                :key="i + file.name"
                class="flex items-center gap-3 rounded-xl border border-brand-100 bg-white px-4 py-3 shadow-sm"
            >
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-tangerine-100 text-xs font-extrabold uppercase text-tangerine-600">
                    {{ (file.name.split('.').pop() || 'file').slice(0, 4) }}
                </span>
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-semibold text-slate-700">{{ file.name }}</p>
                    <p class="text-xs text-slate-400">{{ prettySize(file.size) }}</p>
                </div>
                <template v-if="multiple">
                    <button type="button" class="rounded-md p-1.5 text-slate-400 transition hover:bg-brand-50 hover:text-brand-600 disabled:opacity-30" :disabled="i === 0" title="Move up" @click.stop="move(i, -1)">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="m18 15-6-6-6 6"/></svg>
                    </button>
                    <button type="button" class="rounded-md p-1.5 text-slate-400 transition hover:bg-brand-50 hover:text-brand-600 disabled:opacity-30" :disabled="i === files.length - 1" title="Move down" @click.stop="move(i, 1)">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="m6 9 6 6 6-6"/></svg>
                    </button>
                </template>
                <button type="button" class="rounded-md p-1.5 text-slate-400 transition hover:bg-red-50 hover:text-red-500" title="Remove" @click.stop="remove(i)">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
                </button>
            </li>
        </ul>
    </div>
</template>
