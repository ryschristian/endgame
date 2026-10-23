<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import AccountLayout from '@/layouts/account-layout.vue';
import ProjectHeader from './_components/project-header.vue';
import WorkspaceToolbar from './_components/workspace-toolbar.vue';
import TicketBoard from './_components/ticket-board.vue';
import TicketList from './_components/ticket-list.vue';
import TicketSchedule from './_components/ticket-schedule.vue';
import TicketTransitionDialog from './_components/ticket-transition-dialog.vue';
import TicketScheduleDialog from './_components/ticket-schedule-dialog.vue';
import TicketTable from '@/pages/tickets/_components/ticket-table.vue';
import TicketDetailsSheet from '@/pages/tickets/_components/ticket-details-sheet.vue';
import TicketFormDialog from '@/pages/tickets/_components/ticket-form-dialog.vue';
import TicketCatalogDialog from '@/pages/tickets/_components/ticket-catalog-dialog.vue';
import PaginationControls from '@/pages/_components/pagination-controls.vue';
import type { SharedPageProps } from '@/types/accounts';
import type { TicketRow } from '@/pages/tickets/_components/types';
import type {
    MoveRequest,
    WorkspaceFilters,
    WorkspaceProps,
} from './_components/workspace-types';
const props = defineProps<WorkspaceProps>();
const page = usePage<SharedPageProps>();
const creating = ref(false);
const configuring = ref(false);
const pending = ref<MoveRequest | null>(null);
const editingDates = ref<TicketRow | null>(null);
const moveForm = useForm({
    revision: 0,
    state: '',
    before_id: null as number | null,
    board_revision: 0,
});
const dateForm = useForm({
    revision: 0,
    start_date: null as string | null,
    due_date: null as string | null,
});
const busy = computed(
    () =>
        Boolean(props.project.archived_at) ||
        moveForm.processing ||
        dateForm.processing,
);
const today = new Intl.DateTimeFormat('en-CA', {
    timeZone: 'Asia/Manila',
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
}).format(new Date());
function ticketUrl(id?: number) {
    const url = new URL(page.url, window.location.origin);
    url.searchParams.delete('ticket');
    url.searchParams.delete('history_page');
    if (id) url.searchParams.set('ticket', String(id));
    return url.pathname + url.search;
}
function openTicket(id?: number) {
    router.get(
        ticketUrl(id),
        {},
        { preserveScroll: true, preserveState: true },
    );
}
function filter(filters: WorkspaceFilters) {
    router.get(
        `/projects/${props.project.id}/${props.view}`,
        { ...filters },
        { preserveScroll: true },
    );
}
function sort(key: string) {
    filter({
        ...props.filters,
        sort: key,
        direction:
            props.filters.sort === key && props.filters.direction === 'asc'
                ? 'desc'
                : 'asc',
    });
}
function move(request: MoveRequest) {
    if (busy.value) return;
    const target = props.workflow.definition.statuses.find(
        (status) => status.key === request.state,
    );
    if (
        request.state !== request.ticket.state &&
        (target?.completed || request.ticket.completed)
    ) {
        pending.value = request;
        return;
    }
    Object.assign(moveForm, {
        revision: request.ticket.revision,
        state: request.state,
        before_id: request.beforeId,
        board_revision: props.boardRevision,
    });
    moveForm.post(
        `/projects/${props.project.id}/tickets/${request.ticket.id}/move`,
        { preserveScroll: true },
    );
}
function schedule(ticket: TicketRow, start: string | null, due: string | null) {
    if (busy.value) return;
    Object.assign(dateForm, {
        revision: ticket.revision,
        start_date: start,
        due_date: due,
    });
    dateForm.patch(
        `/projects/${props.project.id}/tickets/${ticket.id}/schedule`,
        { preserveScroll: true },
    );
}
</script>
<template>
    <Head
        :title="`${project.name} · ${view[0]!.toUpperCase() + view.slice(1)}`"
    />
    <AccountLayout
        class="project-workspace"
        :user="page.props.auth.user!"
        :can-manage-accounts="page.props.auth.canManageAccounts"
    >
        <ProjectHeader :project="project" :can="can" />
        <WorkspaceToolbar
            :key="JSON.stringify(filters)"
            :filters="filters"
            :statuses="workflow.definition.statuses"
            :options="options"
            :sort-options="sortOptions"
            :project-id="project.id"
            :selected-assignee="selectedAssignee"
            :can-create="ticketCan.create"
            :disabled="busy"
            @apply="filter"
            @create="creating = true"
        />
        <div
            v-if="
                Object.keys(moveForm.errors).length ||
                Object.keys(dateForm.errors).length
            "
            role="alert"
            class="flex flex-wrap items-center gap-2 rounded-md border border-destructive/20 px-3 py-2 text-xs text-destructive"
        >
            <p
                v-for="(message, key) in {
                    ...moveForm.errors,
                    ...dateForm.errors,
                }"
                :key="key"
            >
                {{ message }}
            </p>
            <Button
                variant="outline"
                class="ml-auto"
                @click="
                    router.reload({
                        onSuccess: () => {
                            moveForm.clearErrors();
                            dateForm.clearErrors();
                        },
                    })
                "
                >Reload</Button
            >
        </div>
        <div
            v-if="view === 'board' && filters.sort !== 'manual'"
            class="text-xs text-muted-foreground"
        >
            Select Manual sorting to reorder cards. Status controls remain
            available.
        </div>
        <TicketBoard
            v-if="view === 'board'"
            :rows="tickets.data"
            :statuses="workflow.definition.statuses"
            :counts="statusCounts"
            :can-update="ticketCan.update"
            :can-create="ticketCan.create"
            :initial="workflow.definition.initial"
            :busy="busy"
            :manual="filters.sort === 'manual' && filters.direction === 'asc'"
            @open="openTicket"
            @create="creating = true"
            @move="move"
        />
        <TicketList
            v-else-if="view === 'list'"
            :rows="tickets.data"
            :statuses="workflow.definition.statuses"
            :counts="statusCounts"
            :can-update="ticketCan.update"
            :busy="busy"
            @open="openTicket"
            @move="move"
        />
        <TicketTable
            v-else-if="view === 'table'"
            :rows="tickets.data"
            :project-id="project.id"
            :selected-id="selectedTicket?.id"
            :sort="filters.sort"
            :direction="filters.direction"
            :ticket-url="ticketUrl"
            @sort="sort"
        />
        <TicketSchedule
            v-else
            :rows="tickets.data"
            :month="filters.month"
            :timeline="view === 'timeline'"
            :can-update="ticketCan.update"
            :busy="busy"
            :today="today"
            @open="openTicket"
            @edit="editingDates = $event"
            @schedule="schedule"
            @month="filter({ ...filters, month: $event })"
        />
        <div class="flex flex-wrap items-center justify-between gap-3">
            <p class="text-xs text-muted-foreground">
                {{ tickets.total }} matching
                {{ tickets.total === 1 ? 'ticket' : 'tickets'
                }}<span v-if="tickets.last_page > 1">
                    · Page {{ tickets.current_page }} of
                    {{ tickets.last_page }}. Views show this page's
                    tickets.</span
                >
            </p>
            <Button
                v-if="ticketCan.configure"
                variant="ghost"
                class="text-xs text-muted-foreground"
                :disabled="busy"
                @click="configuring = true"
                >Ticket setup</Button
            >
        </div>
        <PaginationControls
            :current-page="tickets.current_page"
            :last-page="tickets.last_page"
            :previous="tickets.prev_page_url"
            :next="tickets.next_page_url"
            label="Workspace tickets pagination"
        />
        <TicketDetailsSheet
            :data="props"
            @close="openTicket()"
        /><TicketFormDialog
            :open="creating"
            :data="props"
            @close="creating = false"
        /><TicketCatalogDialog
            :open="configuring"
            :data="props"
            @close="configuring = false"
        />
        <TicketTransitionDialog
            v-if="pending"
            :key="`${pending.ticket.id}-${pending.state}`"
            :project-id="project.id"
            :ticket="pending.ticket"
            :state="pending.state"
            :workflow="workflow"
            :before-id="pending.beforeId"
            :board-revision="boardRevision"
            @close="pending = null"
        />
        <TicketScheduleDialog
            v-if="editingDates"
            :key="editingDates.id"
            :ticket="editingDates"
            :project-id="project.id"
            @close="editingDates = null"
        />
    </AccountLayout>
</template>
<style>
.project-workspace {
    --primary: #5453df;
    --primary-foreground: #fff;
    --ring: #5453df;
    --background: #fff;
}
</style>
