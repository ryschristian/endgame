<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import AccountLayout from '@/layouts/account-layout.vue';
import ProjectHeader from './_components/project-header.vue';
import WorkflowForm from './_components/workflow-form.vue';
import type { Workflow, WorkflowStatus } from './_components/workspace-types';
import type { ProjectPageProps } from '@/types/projects';
import type { SharedPageProps } from '@/types/accounts';
defineProps<
    ProjectPageProps & {
        workflow: Workflow;
        ticketCount: number;
        stageDefaults: Omit<WorkflowStatus, 'key'>;
    }
>();
const page = usePage<SharedPageProps>();
</script>
<template>
    <Head title="Workflow setup" /><AccountLayout
        :user="page.props.auth.user!"
        :can-manage-accounts="page.props.auth.canManageAccounts"
        ><ProjectHeader :project="project" :can="can" /><WorkflowForm
            :key="workflow.id"
            :workflow="workflow"
            :stage-defaults="stageDefaults"
            :project-id="project.id"
            :ticket-count="ticketCount"
            :disabled="Boolean(project.archived_at)"
    /></AccountLayout>
</template>
