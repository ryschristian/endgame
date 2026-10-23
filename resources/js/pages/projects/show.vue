<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { CircleAlert, Pencil, Users } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
} from '@/components/ui/dialog';
import AccountLayout from '@/layouts/account-layout.vue';
import ProjectHeader from './_components/project-header.vue';
import ProjectForm from './_components/project-form.vue';
import ProjectConfirm from './_components/project-confirm.vue';
import PaginationControls from '@/pages/_components/pagination-controls.vue';
import { teamDate, type TicketRow } from '@/pages/tickets/_components/types';
import type { SharedPageProps, Paginated } from '@/types/accounts';
import type { ProjectPageProps } from '@/types/projects';
import type { Workflow } from './_components/workspace-types';
const props = defineProps<
    ProjectPageProps & {
        workflow: Workflow;
        members: Paginated<{ id: number; name: string; roles: string[] }>;
        overview: null | {
            total: number;
            completed: number;
            overdue: number;
            statusCounts: Record<string, number>;
            upcoming: TicketRow[];
            activity: {
                id: number;
                actor: string;
                action: string;
                ticket: { id: number; key: string; summary: string };
                created_at: string;
                status: string | null;
            }[];
        };
    }
>();
const page = usePage<SharedPageProps>();
const confirming = ref(false);
const editing = ref(false);
const progress = computed(() =>
    props.overview?.total
        ? Math.round((props.overview.completed / props.overview.total) * 100)
        : 0,
);
const lifecycle = useForm({ revision: props.project.revision });
const initials = (name: string) =>
    name
        .split(/\s+/)
        .slice(0, 2)
        .map((part) => part[0])
        .join('')
        .toUpperCase();
const actionLabels: Record<string, string> = {
    created: 'created',
    updated: 'updated',
    transitioned: 'moved',
    ordered: 'reordered',
    scheduled: 'rescheduled',
    workflow_migrated: 'updated the workflow for',
};
function changeArchive() {
    lifecycle
        .transform(() => ({ revision: props.project.revision }))
        .post(
            `/projects/${props.project.id}/${props.project.archived_at ? 'restore' : 'archive'}`,
            {
                preserveScroll: true,
                onSuccess: () => {
                    confirming.value = false;
                },
            },
        );
}
</script>
<template>
    <Head :title="project.name" />
    <AccountLayout
        class="project-overview"
        :user="page.props.auth.user!"
        :can-manage-accounts="page.props.auth.canManageAccounts"
    >
        <ProjectHeader :project="project" :can="can" />
        <div
            class="mx-auto grid max-w-6xl items-start gap-4 pt-1 lg:grid-cols-[minmax(0,1.55fr)_minmax(280px,1fr)]"
        >
            <div class="space-y-4">
                <section class="rounded-xl border bg-card p-5">
                    <div class="mb-3 flex items-center justify-between">
                        <h2 class="text-sm font-medium">About</h2>
                        <Button
                            v-if="can.update"
                            variant="ghost"
                            class="h-7 gap-1.5 px-2 text-xs text-muted-foreground"
                            @click="editing = true"
                            ><Pencil class="size-3.5" />Edit project</Button
                        >
                    </div>
                    <p class="text-[13px] leading-6 whitespace-pre-wrap">
                        {{ project.description || 'No description yet.' }}
                    </p>
                </section>
                <section v-if="overview" class="rounded-xl border bg-card p-5">
                    <div class="flex items-center justify-between text-xs">
                        <h2 class="font-medium">Progress</h2>
                        <span class="text-muted-foreground"
                            >{{ overview.completed }} of
                            {{ overview.total }} tickets done</span
                        >
                    </div>
                    <div class="my-4 flex items-end gap-4">
                        <span class="text-3xl font-semibold tracking-tight"
                            >{{ progress }}%</span
                        ><span
                            v-if="overview.overdue"
                            class="mb-1 inline-flex items-center gap-1 rounded bg-red-50 px-2 py-1 text-[11px] text-red-700"
                            ><CircleAlert class="size-3" />{{
                                overview.overdue
                            }}
                            overdue</span
                        >
                    </div>
                    <div
                        class="mb-3 flex h-2 overflow-hidden rounded-full bg-muted"
                        role="img"
                        :aria-label="`${progress}% complete`"
                    >
                        <span
                            v-for="stage in workflow.definition.statuses"
                            :key="stage.key"
                            :title="`${stage.label}: ${overview.statusCounts[stage.key] ?? 0}`"
                            :style="{
                                width: `${overview.total ? ((overview.statusCounts[stage.key] ?? 0) / overview.total) * 100 : 0}%`,
                                backgroundColor: stage.color,
                            }"
                            class="border-r border-white last:border-0"
                        />
                    </div>
                    <div class="flex flex-wrap gap-x-4 gap-y-2">
                        <Link
                            v-for="stage in workflow.definition.statuses"
                            :key="stage.key"
                            :href="`/projects/${project.id}/list?state=${stage.key}`"
                            class="flex items-center gap-1.5 text-[11px] hover:underline"
                            ><span
                                class="size-2.5 rounded-full border-2"
                                :style="{
                                    borderColor: stage.color,
                                    backgroundColor: stage.completed
                                        ? stage.color
                                        : 'transparent',
                                }"
                            />{{ stage.label
                            }}<span class="font-medium">{{
                                overview.statusCounts[stage.key] ?? 0
                            }}</span></Link
                        >
                    </div>
                </section>
                <section
                    v-if="overview"
                    class="overflow-hidden rounded-xl border bg-card"
                >
                    <div
                        class="flex items-center justify-between border-b px-5 py-4 text-xs"
                    >
                        <h2 class="font-medium">Coming up</h2>
                        <Link
                            :href="`/projects/${project.id}/list?sort=due_date&direction=asc`"
                            class="text-muted-foreground hover:text-foreground"
                            >View list</Link
                        >
                    </div>
                    <Link
                        v-for="ticket in overview.upcoming"
                        :key="ticket.id"
                        :href="`/projects/${project.id}/list?ticket=${ticket.id}`"
                        class="flex items-center gap-3 border-b px-5 py-3 text-xs last:border-0 hover:bg-muted/40"
                        ><span
                            class="size-3 shrink-0 rounded-full border-2"
                            :style="{ borderColor: ticket.stateColor }"
                        /><span class="min-w-0 flex-1 truncate">{{
                            ticket.summary
                        }}</span
                        ><span
                            class="shrink-0 text-[11px] text-muted-foreground"
                            >{{ teamDate(ticket.due_date) }}</span
                        ><span
                            v-if="ticket.assignee"
                            class="flex size-5 shrink-0 items-center justify-center rounded-full bg-violet-100 text-[8px] text-violet-800"
                            :title="ticket.assignee.name"
                            >{{ initials(ticket.assignee.name) }}</span
                        ></Link
                    >
                    <p
                        v-if="!overview.upcoming.length"
                        class="p-5 text-xs text-muted-foreground"
                    >
                        No upcoming due dates.
                    </p>
                </section>
            </div>
            <div class="space-y-4">
                <section class="rounded-xl border bg-card p-5">
                    <h2 class="mb-5 text-sm font-medium">Details</h2>
                    <dl class="space-y-4 text-xs">
                        <div class="grid grid-cols-[100px_1fr] gap-3">
                            <dt class="text-muted-foreground">Status</dt>
                            <dd>
                                <span
                                    class="rounded border px-2 py-1 text-[11px]"
                                    >{{ project.statusLabel }}</span
                                >
                            </dd>
                        </div>
                        <div class="grid grid-cols-[100px_1fr] gap-3">
                            <dt class="text-muted-foreground">Project key</dt>
                            <dd class="font-mono">{{ project.key }}</dd>
                        </div>
                        <div class="grid grid-cols-[100px_1fr] gap-3">
                            <dt class="text-muted-foreground">Timezone</dt>
                            <dd>{{ project.timezone }}</dd>
                        </div>
                        <div
                            v-if="can.tickets"
                            class="grid grid-cols-[100px_1fr] gap-3"
                        >
                            <dt class="text-muted-foreground">Workflow</dt>
                            <dd>
                                <Link
                                    v-if="can.update"
                                    :href="`/projects/${project.id}/workflow`"
                                    class="hover:underline"
                                    >Version {{ workflow.version }}</Link
                                ><span v-else
                                    >Version {{ workflow.version }}</span
                                >
                            </dd>
                        </div>
                        <div class="grid grid-cols-[100px_1fr] gap-3">
                            <dt
                                class="flex items-center gap-1.5 text-muted-foreground"
                            >
                                <Users class="size-3.5" />Members
                            </dt>
                            <dd>{{ members.total }}</dd>
                        </div>
                    </dl>
                </section>
                <section class="overflow-hidden rounded-xl border bg-card">
                    <div
                        class="flex items-center justify-between border-b px-5 py-4"
                    >
                        <h2 class="text-sm font-medium">Members</h2>
                        <Link
                            v-if="can.members"
                            :href="`/projects/${project.id}/members`"
                            class="text-xs text-muted-foreground hover:text-foreground"
                            >Manage</Link
                        >
                    </div>
                    <ul class="divide-y">
                        <li
                            v-for="member in members.data"
                            :key="member.id"
                            class="flex items-center gap-3 px-5 py-3"
                        >
                            <span
                                class="flex size-7 shrink-0 items-center justify-center rounded-full bg-violet-100 text-[10px] text-violet-800"
                                >{{ initials(member.name) }}</span
                            >
                            <div class="min-w-0">
                                <p class="truncate text-xs">
                                    {{ member.name }}
                                </p>
                                <p
                                    class="mt-0.5 text-[10px] text-muted-foreground"
                                >
                                    {{
                                        member.roles.join(', ') ||
                                        'No roles assigned'
                                    }}
                                </p>
                            </div>
                        </li>
                    </ul>
                    <PaginationControls
                        :current-page="members.current_page"
                        :last-page="members.last_page"
                        :previous="members.prev_page_url"
                        :next="members.next_page_url"
                        label="Member overview pagination"
                    />
                </section>
                <section
                    v-if="overview"
                    class="overflow-hidden rounded-xl border bg-card"
                >
                    <h2 class="border-b px-5 py-4 text-sm font-medium">
                        Recent activity
                    </h2>
                    <Link
                        v-for="event in overview.activity"
                        :key="event.id"
                        :href="`/projects/${project.id}/list?ticket=${event.ticket.id}`"
                        class="flex gap-3 px-5 py-3 hover:bg-muted/40"
                        ><span
                            class="mt-0.5 flex size-5 shrink-0 items-center justify-center rounded-full bg-violet-100 text-[8px] text-violet-800"
                            >{{ initials(event.actor) }}</span
                        >
                        <div class="text-xs leading-5">
                            <p>
                                <span class="font-medium">{{
                                    event.actor
                                }}</span>
                                {{ actionLabels[event.action] ?? 'changed' }}
                                {{ event.ticket.summary
                                }}<span v-if="event.status">
                                    to {{ event.status }}</span
                                >
                            </p>
                            <time class="text-[10px] text-muted-foreground">{{
                                teamDate(event.created_at, true)
                            }}</time>
                        </div></Link
                    >
                    <p
                        v-if="!overview.activity.length"
                        class="p-5 text-xs text-muted-foreground"
                    >
                        No ticket activity yet.
                    </p>
                </section>
            </div>
        </div>
        <div
            v-if="project.archived_at ? can.restore : can.archive"
            class="mx-auto flex max-w-6xl justify-end"
        >
            <Button
                variant="ghost"
                class="text-xs text-muted-foreground"
                @click="confirming = true"
                >{{
                    project.archived_at ? 'Restore project' : 'Archive project'
                }}</Button
            >
        </div>
        <Dialog v-model:open="editing"
            ><DialogContent class="sm:max-w-lg"
                ><DialogHeader
                    ><DialogTitle>Project settings</DialogTitle
                    ><DialogDescription class="sr-only"
                        >Edit this project's name and
                        description</DialogDescription
                    ></DialogHeader
                ><ProjectForm
                    :key="project.revision"
                    :project="project"
                    :team-timezone="page.props.teamTimezone"
                    @saved="editing = false" /></DialogContent
        ></Dialog>
        <ProjectConfirm
            :open="confirming"
            :title="project.archived_at ? 'Restore project' : 'Archive project'"
            :description="
                project.archived_at
                    ? 'Allow changes to this project again?'
                    : 'Keep this project readable and stop changes until it is restored?'
            "
            :busy="lifecycle.processing"
            @close="confirming = false"
            @confirm="changeArchive"
            ><p
                v-for="(message, key) in lifecycle.errors"
                :key="key"
                role="alert"
                class="text-sm text-destructive"
            >
                {{ message }}
            </p></ProjectConfirm
        >
    </AccountLayout>
</template>
<style>
.project-overview {
    --primary: #5453df;
    --primary-foreground: #fff;
    --ring: #5453df;
    --background: #fff;
}
</style>
