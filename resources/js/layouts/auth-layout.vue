<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import type { SharedPageProps } from '@/types/accounts';

defineProps<{ title: string; description: string }>();
const page = usePage<SharedPageProps>();
</script>

<template>
    <Head :title="title" />
    <main
        class="flex min-h-screen flex-col items-center justify-center bg-background px-4 py-12"
    >
        <Link href="/" class="mb-6 text-lg font-semibold tracking-tight">{{
            page.props.appName
        }}</Link>
        <section
            class="w-full max-w-[420px] rounded-xl border bg-card p-5 shadow-sm sm:p-6"
            :aria-label="title"
        >
            <header class="mb-5 space-y-1.5">
                <h1 class="text-[23px] font-semibold tracking-tight">
                    {{ title }}
                </h1>
                <p class="text-[12.5px] leading-relaxed text-muted-foreground">
                    {{ description }}
                </p>
            </header>
            <p
                v-if="page.props.flash.status"
                role="status"
                class="mb-6 rounded-md border bg-muted p-3 text-[12.5px] leading-relaxed"
            >
                {{ page.props.flash.status }}
            </p>
            <slot />
        </section>
    </main>
</template>
