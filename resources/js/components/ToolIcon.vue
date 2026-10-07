<script setup>
import { computed } from 'vue';

const props = defineProps({
    name: { type: String, required: true },
    size: { type: String, default: 'h-6 w-6' },
});

// Simple, friendly line icons — one per tool.
const icons = {
    merge: '<path d="M8 3H5a2 2 0 0 0-2 2v3m18 0V5a2 2 0 0 0-2-2h-3m0 18h3a2 2 0 0 0 2-2v-3M3 16v3a2 2 0 0 0 2 2h3"/><path d="M12 8v8m-3-3 3 3 3-3"/>',
    split: '<path d="M12 3v18"/><path d="M8 7H5a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h3m8-10h3a2 2 0 0 1 2 2v6a2 2 0 0 1-2 2h-3"/>',
    compress: '<path d="M4 14h6v6M20 10h-6V4"/><path d="m10 14-7 7m17-17-7 7"/>',
    rotate: '<path d="M21 12a9 9 0 1 1-3-6.7"/><path d="M21 3v6h-6"/>',
    watermark: '<path d="M12 3s6 6.6 6 10.4A6 6 0 0 1 6 13.4C6 9.6 12 3 12 3z"/><path d="M9.5 13.5a2.5 2.5 0 0 0 2.5 2.5"/>',
    numbers: '<path d="M4 9h16M4 15h16"/><path d="M10 3 8 21M16 3l-2 18"/>',
    lock: '<rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/><circle cx="12" cy="16" r="1.4" fill="currentColor" stroke="none"/>',
    unlock: '<rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 7.6-1.7"/><circle cx="12" cy="16" r="1.4" fill="currentColor" stroke="none"/>',
    image: '<rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="8.5" cy="10" r="1.6"/><path d="m21 15-4.4-4.4a1.5 1.5 0 0 0-2.1 0L7 18"/>',
    images: '<rect x="6" y="7" width="15" height="12" rx="2"/><path d="M3 15V5a2 2 0 0 1 2-2h10"/><circle cx="11" cy="11" r="1.3"/><path d="m21 16-3.4-3.4a1.4 1.4 0 0 0-2 0L9 19"/>',
    text: '<path d="M4 7V5h16v2M9 20h6M12 5v15"/>',
    office: '<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5"/><path d="M9 13h6M9 17h4"/>',
    word: '<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5"/><path d="m9 12 1.2 5L12 14l1.8 3L15 12"/>',
    scan: '<path d="M3 8V5a2 2 0 0 1 2-2h3M16 3h3a2 2 0 0 1 2 2v3m0 8v3a2 2 0 0 1-2 2h-3M8 21H5a2 2 0 0 1-2-2v-3"/><path d="M4 12h16"/>',
    file: '<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5"/>',
};

const path = computed(() => icons[props.name] ?? icons.file);
</script>

<template>
    <svg
        :class="size"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="1.8"
        stroke-linecap="round"
        stroke-linejoin="round"
        v-html="path"
    />
</template>
