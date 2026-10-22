<script setup lang="ts">
import { Button } from '@/components/ui/button';
import type { SharedPageProps } from '@/types/accounts';
import { Link } from '@inertiajs/vue3';

defineProps<{ appName: string; auth?: SharedPageProps['auth'] }>();
</script>

<template>
    <main
        class="mx-auto flex min-h-screen max-w-xl flex-col justify-center gap-6 p-8"
    >
        <div class="space-y-2">
            <h1 class="text-3xl font-semibold tracking-tight">{{ appName }}</h1>
            <p class="text-muted-foreground">
                The application foundation is ready.
            </p>
        </div>

        <Button as-child class="self-start">
            <a href="/up">Check application health</a>
        </Button>
        <Link
            :href="auth?.user ? '/account' : '/login'"
            class="self-start py-3 text-sm underline underline-offset-4"
            >{{ auth?.user ? 'My account' : 'Sign in' }}</Link
        >
    </main>
</template>
