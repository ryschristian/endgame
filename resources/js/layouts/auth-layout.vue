<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import type { SharedPageProps } from '@/types/accounts';

defineProps<{ title: string; description: string }>();
const page = usePage<SharedPageProps>();
</script>

<template>
    <Head :title="title" />
    <main
        class="flex min-h-screen flex-col items-center justify-center bg-muted/40 px-4 py-12"
    >
        <Link href="/" class="mb-8 text-xl font-semibold tracking-tight">{{
            page.props.appName
        }}</Link>
        <section
            class="w-full max-w-md rounded-xl border bg-card p-6 shadow-sm sm:p-8"
            :aria-label="title"
        >
            <header class="mb-7 space-y-2">
                <h1 class="text-2xl font-semibold tracking-tight">
                    {{ title }}
                </h1>
                <p class="text-sm leading-6 text-muted-foreground">
                    {{ description }}
                </p>
            </header>
            <p
                v-if="page.props.flash.status"
                role="status"
                class="mb-6 rounded-md border bg-muted p-3 text-sm leading-6"
            >
                {{ page.props.flash.status }}
            </p>
            <slot />
        </section>
    </main>
</template>
