<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Link } from '@inertiajs/vue3';

defineProps<{
    currentPage: number;
    lastPage: number;
    previous: string | null;
    next: string | null;
    label: string;
}>();
</script>

<template>
    <nav
        v-if="lastPage > 1"
        :aria-label="label"
        class="flex flex-wrap items-center justify-between gap-3 border-t px-4 py-3"
    >
        <p class="text-[12.5px] text-muted-foreground">
            Page {{ currentPage }} of {{ lastPage }}
        </p>
        <div class="flex gap-2">
            <Button
                v-if="previous"
                as-child
                variant="outline"
                class="min-h-9 sm:min-h-7.5"
                ><Link :href="previous" preserve-scroll>Previous</Link></Button
            >
            <Button
                v-else
                variant="outline"
                class="min-h-9 sm:min-h-7.5"
                disabled
                >Previous</Button
            >
            <Button
                v-if="next"
                as-child
                variant="outline"
                class="min-h-9 sm:min-h-7.5"
                ><Link :href="next" preserve-scroll>Next</Link></Button
            >
            <Button
                v-else
                variant="outline"
                class="min-h-9 sm:min-h-7.5"
                disabled
                >Next</Button
            >
        </div>
    </nav>
</template>
