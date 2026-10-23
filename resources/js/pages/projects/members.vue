<script setup lang="ts">
import AccountLayout from '@/layouts/account-layout.vue';
import ProjectHeader from '@/pages/projects/_components/project-header.vue';
import MemberForm from '@/pages/projects/_components/member-form.vue';
import MemberRow from '@/pages/projects/_components/member-row.vue';
import InvitationRow from '@/pages/projects/_components/invitation-row.vue';
import PaginationControls from '@/pages/_components/pagination-controls.vue';
import type { SharedPageProps, Paginated } from '@/types/accounts';
import type {
    ProjectPageProps,
    MemberRow as Member,
    ProjectInvitationRow,
    Option,
} from '@/types/projects';
import { Head, usePage } from '@inertiajs/vue3';
defineProps<
    ProjectPageProps & {
        members: Paginated<Member>;
        invitations: Paginated<ProjectInvitationRow>;
        roleOptions: Option<number>[];
    }
>();
const page = usePage<SharedPageProps>();
</script>
<template>
    <Head :title="`${project.name} · Members`" />
    <AccountLayout
        :user="page.props.auth.user!"
        :can-manage-accounts="page.props.auth.canManageAccounts"
        :status="page.props.flash.status"
    >
        <ProjectHeader :project="project" :can="can" />
        <div
            class="grid items-start gap-6 lg:grid-cols-[minmax(0,2fr)_minmax(0,3fr)]"
        >
            <section class="rounded-xl border bg-card p-6">
                <MemberForm
                    :key="project.revision"
                    :project-id="project.id"
                    :revision="project.revision"
                    :role-options="roleOptions"
                    :disabled="Boolean(project.archived_at)"
                />
            </section>
            <section class="overflow-hidden rounded-xl border bg-card">
                <h2 class="p-5 text-lg font-semibold">
                    Members
                    <span class="text-sm font-normal text-muted-foreground"
                        >({{ members.total }})</span
                    >
                </h2>
                <MemberRow
                    v-for="member in members.data"
                    :key="`${project.revision}-${member.id}`"
                    :member="member"
                    :project-id="project.id"
                    :revision="project.revision"
                    :role-options="roleOptions"
                    :disabled="Boolean(project.archived_at)"
                /><PaginationControls
                    :current-page="members.current_page"
                    :last-page="members.last_page"
                    :previous="members.prev_page_url"
                    :next="members.next_page_url"
                    label="Members pagination"
                />
            </section>
        </div>
        <section class="overflow-hidden rounded-xl border bg-card">
            <h2 class="p-5 text-lg font-semibold">Project invitations</h2>
            <p
                v-if="!invitations.data.length"
                class="border-t p-5 text-sm text-muted-foreground"
            >
                No project invitations.
            </p>
            <InvitationRow
                v-for="invitation in invitations.data"
                :key="`${project.revision}-${invitation.id}`"
                :invitation="invitation"
                :project-id="project.id"
                :revision="project.revision"
                :role-options="roleOptions"
                :disabled="Boolean(project.archived_at)"
            /><PaginationControls
                :current-page="invitations.current_page"
                :last-page="invitations.last_page"
                :previous="invitations.prev_page_url"
                :next="invitations.next_page_url"
                label="Invitations pagination"
            />
        </section>
    </AccountLayout>
</template>
