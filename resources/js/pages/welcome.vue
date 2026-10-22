<script setup lang="ts">
import { Button } from '@/components/ui/button';

defineProps<{ appName: string }>();
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
    </main>
</template>
