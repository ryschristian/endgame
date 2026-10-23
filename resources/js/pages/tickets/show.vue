<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import AccountLayout from '@/layouts/account-layout.vue';
import ProjectHeader from '@/pages/projects/_components/project-header.vue';
import TicketDetails from './_components/ticket-details.vue';
import type { TicketProps } from './_components/types';
import type { SharedPageProps } from '@/types/accounts';
const props = defineProps<TicketProps>();
const page = usePage<SharedPageProps>();
</script>
<template>
    <Head :title="`${selectedTicket?.key} · ${project.name}`" /><AccountLayout
        :user="page.props.auth.user!"
        :can-manage-accounts="page.props.auth.canManageAccounts"
        :status="page.props.flash.status"
        ><ProjectHeader :project="project" :can="can" />
        <section class="mx-auto max-w-3xl rounded-xl border bg-card">
            <header
                class="flex items-center justify-between border-b px-6 py-4 text-xs"
            >
                <span class="font-mono text-muted-foreground">{{
                    selectedTicket?.key
                }}</span
                ><Link
                    :href="`/projects/${project.id}/tickets`"
                    class="underline underline-offset-4"
                    >Back to tickets</Link
                >
            </header>
            <div class="p-6 sm:p-8">
                <TicketDetails
                    v-if="selectedTicket"
                    :key="selectedTicket.id"
                    :data="props"
                />
            </div></section
    ></AccountLayout>
</template>
