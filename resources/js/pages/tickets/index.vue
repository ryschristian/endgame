<script setup lang="ts">
import { ref } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import AccountLayout from '@/layouts/account-layout.vue';
import ProjectHeader from '@/pages/projects/_components/project-header.vue';
import PaginationControls from '@/pages/_components/pagination-controls.vue';
import TicketTable from './_components/ticket-table.vue';
import TicketFilters from './_components/ticket-filters.vue';
import TicketDetailsSheet from './_components/ticket-details-sheet.vue';
import TicketFormDialog from './_components/ticket-form-dialog.vue';
import TicketCatalogDialog from './_components/ticket-catalog-dialog.vue';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import type { TicketProps, TicketRow, Filters } from './_components/types';
import type { SharedPageProps, Paginated } from '@/types/accounts';
const props = defineProps<
    TicketProps & { tickets: Paginated<TicketRow>; filters: Filters }
>();
const page = usePage<SharedPageProps>();
const creating = ref(false);
const configuring = ref(false);
function url(id?: number) {
    const params = new URLSearchParams();
    if (props.tickets.current_page > 1)
        params.set('page', String(props.tickets.current_page));
    for (const [key, value] of Object.entries(props.filters))
        if (
            value !== '' &&
            value != null &&
            !['ticket', 'history_page'].includes(key)
        )
            params.set(key, String(value));
    if (id) params.set('ticket', String(id));
    return `/projects/${props.project.id}/tickets?${params}`;
}
function filter(filters: Filters) {
    router.get(
        `/projects/${props.project.id}/tickets`,
        { ...filters, ticket: undefined },
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
</script>
<template>
    <Head :title="`${project.name} · Tickets`" /><AccountLayout
        :user="page.props.auth.user!"
        :can-manage-accounts="page.props.auth.canManageAccounts"
        :status="page.props.flash.status"
        ><ProjectHeader :project="project" :can="can" />
        <div class="flex items-center justify-between gap-3">
            <p class="text-xs text-muted-foreground">
                {{ tickets.total }}
                {{ tickets.total === 1 ? 'ticket' : 'tickets' }}
            </p>
            <div class="flex gap-2">
                <DropdownMenu v-if="ticketCan.configure"
                    ><DropdownMenuTrigger as-child
                        ><Button variant="outline" aria-label="Ticket options"
                            >•••</Button
                        ></DropdownMenuTrigger
                    ><DropdownMenuContent
                        ><DropdownMenuItem @select="configuring = true"
                            >Ticket settings</DropdownMenuItem
                        ></DropdownMenuContent
                    ></DropdownMenu
                ><Button
                    v-if="ticketCan.create"
                    :disabled="Boolean(project.archived_at)"
                    @click="creating = true"
                    >+ New ticket</Button
                >
            </div>
        </div>
        <TicketFilters
            :key="JSON.stringify(filters)"
            :filters="filters"
            :options="options"
            :selected-assignee="selectedAssignee"
            :project-id="project.id"
            @apply="filter" /><TicketTable
            :rows="tickets.data"
            :project-id="project.id"
            :selected-id="selectedTicket?.id"
            :ticket-url="url"
            :sort="filters.sort"
            :direction="filters.direction"
            @sort="sort" /><PaginationControls
            :current-page="tickets.current_page"
            :last-page="tickets.last_page"
            :previous="tickets.prev_page_url"
            :next="tickets.next_page_url"
            label="Tickets pagination" /><TicketDetailsSheet
            :data="props"
            @close="
                router.get(url(), {}, { preserveScroll: true })
            " /><TicketFormDialog
            :open="creating"
            :data="props"
            @close="creating = false" /><TicketCatalogDialog
            :open="configuring"
            :data="props"
            @close="configuring = false"
    /></AccountLayout>
</template>
