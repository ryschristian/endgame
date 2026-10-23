<script setup lang="ts">
import AccountLayout from '@/layouts/account-layout.vue';
import ProjectForm from '@/pages/projects/_components/project-form.vue';
import type { SharedPageProps } from '@/types/accounts';
import { Head, Link, usePage } from '@inertiajs/vue3';
defineProps<{ teamTimezone: string }>();
const page = usePage<SharedPageProps>();
</script>
<template>
    <Head title="Create project" />
    <AccountLayout
        :user="page.props.auth.user!"
        :can-manage-accounts="page.props.auth.canManageAccounts"
        :status="page.props.flash.status"
    >
        <Link
            href="/projects"
            class="text-sm text-muted-foreground hover:underline"
            >Projects</Link
        >
        <section
            class="mx-auto max-w-xl overflow-hidden rounded-xl border bg-card shadow-sm"
        >
            <div class="space-y-1 border-b px-6 py-5">
                <h1 class="text-xl! font-semibold">Create project</h1>
                <p class="text-sm text-muted-foreground">
                    A shared space for your team's work.
                </p>
            </div>
            <div class="p-6"><ProjectForm :team-timezone="teamTimezone" /></div>
        </section>
    </AccountLayout>
</template>
