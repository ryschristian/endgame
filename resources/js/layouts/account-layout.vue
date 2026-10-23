<script setup lang="ts">
import { Button } from '@/components/ui/button';
import type { AccountUser } from '@/types/accounts';
import { Link } from '@inertiajs/vue3';

defineProps<{
    user: AccountUser;
    canManageAccounts: boolean;
    status?: string | null;
}>();
</script>

<template>
    <div class="min-h-screen bg-muted/30">
        <header class="border-b bg-background">
            <div
                class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-3 px-4 py-3 sm:px-6"
            >
                <Link href="/" class="text-lg font-semibold tracking-tight"
                    >Endgame</Link
                >
                <nav
                    aria-label="Account navigation"
                    class="flex flex-wrap items-center gap-2 sm:gap-4"
                >
                    <Link
                        href="/account"
                        class="rounded px-2 py-3 text-sm hover:underline"
                        >My account</Link
                    >
                    <Link
                        v-if="canManageAccounts"
                        href="/admin/accounts"
                        class="rounded px-2 py-3 text-sm hover:underline"
                        >Accounts</Link
                    >
                    <span
                        class="hidden text-sm text-muted-foreground sm:inline"
                        >{{ user.name }}</span
                    >
                    <Button variant="outline" as-child class="min-h-11">
                        <Link href="/logout" method="post" as="button"
                            >Sign out</Link
                        >
                    </Button>
                </nav>
            </div>
        </header>
        <main class="mx-auto max-w-6xl space-y-6 px-4 py-8 sm:px-6 sm:py-12">
            <p
                v-if="status"
                role="status"
                class="rounded-lg border bg-background p-4 text-sm"
            >
                {{ status }}
            </p>
            <slot />
        </main>
    </div>
</template>
