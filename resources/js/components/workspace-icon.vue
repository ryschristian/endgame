<script setup lang="ts">
defineProps<{ name: string }>();
const paths: Record<string, string> = {
    projects:
        'M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v10H3z M7 11v4m4-5v6m4-4v3',
    folder: 'M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v10H3z',
    grid: 'M3 3h7v7H3z M14 3h7v7h-7z M3 14h7v7H3z M14 14h7v7h-7z',
    list: 'M8 5h13M8 12h13M8 19h13M3 5h.01M3 12h.01M3 19h.01',
    search: 'M21 21l-5-5 M18 10a8 8 0 1 1-16 0 8 8 0 0 1 16 0',
    users: 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2 M16 3a4 4 0 0 1 0 8m6 10v-2a4 4 0 0 0-3-3.87 M13 7a4 4 0 1 1-8 0 4 4 0 0 1 8 0',
    settings:
        'M9 3h6l1 3 3 1 2 5-2 5-3 1-1 3H9l-1-3-3-1-2-5 2-5 3-1z M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0',
    shield: 'M12 3l8 3v6c0 5-8 9-8 9s-8-4-8-9V6z M9 12l2 2 4-4',
    archive: 'M3 4h18v4H3z M5 8v12h14V8 M10 12h4',
    chevron: 'M9 5l7 7-7 7',
    down: 'M7 10l5 5 5-5',
    plus: 'M12 5v14M5 12h14',
    menu: 'M4 6h16M4 12h16M4 18h16',
    close: 'M6 6l12 12M6 18 18 6',
    globe: 'M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0 M3 12h18 M12 3c-5 5-5 13 0 18 5-5 5-13 0-18',
    clock: 'M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0 M12 7v5l3 2',
    logout: 'M9 4H4v16h5 M10 12h11m-4-4 4 4-4 4',
    panel: 'M3 4h18v16H3z M9 4v16',
    arrow: 'M7 17 17 7 M7 7h10v10',
};
</script>
<template>
    <svg
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="1.6"
        stroke-linecap="round"
        stroke-linejoin="round"
        aria-hidden="true"
        class="size-4 shrink-0"
    >
        <path :d="paths[name] ?? paths.folder" />
    </svg>
</template>
