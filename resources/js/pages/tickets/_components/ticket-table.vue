<script setup lang="ts">
import { computed, h } from 'vue';
import { Link } from '@inertiajs/vue3';
import {
    FlexRender,
    getCoreRowModel,
    useVueTable,
    type ColumnDef,
} from '@tanstack/vue-table';
import type { TicketRow } from './types';
import { teamDate } from './types';
const props = defineProps<{
    rows: TicketRow[];
    projectId: number;
    selectedId?: number;
    sort: string;
    direction: string;
    ticketUrl: (id: number) => string;
}>();
const emit = defineEmits<{ sort: [key: string] }>();
const columns: ColumnDef<TicketRow>[] = [
    {
        accessorKey: 'summary',
        header: 'Ticket',
        cell: ({ row }) =>
            h(
                Link,
                {
                    href: props.ticketUrl(row.original.id),
                    preserveScroll: true,
                    class: 'flex min-w-64 items-center gap-3 py-1 font-medium text-foreground hover:text-primary',
                    'aria-label': `Open ${row.original.key}: ${row.original.summary}`,
                },
                () => [
                    h('span', {
                        class: 'size-3 shrink-0 rounded-full border border-stone-400',
                        'aria-hidden': true,
                    }),
                    h('span', { class: 'line-clamp-2' }, row.original.summary),
                    h(
                        'span',
                        {
                            class: 'ml-auto shrink-0 font-mono text-[10px] font-normal text-muted-foreground',
                        },
                        row.original.key,
                    ),
                ],
            ),
    },
    { accessorKey: 'stateLabel', header: 'Status' },
    { id: 'work_type', header: 'Type', accessorFn: (row) => row.workType.name },
    {
        id: 'priority',
        header: 'Priority',
        accessorFn: (row) => row.priority.name,
    },
    {
        id: 'assignee',
        header: 'Assignee',
        accessorFn: (row) => row.assignee?.name ?? 'Unassigned',
    },
    {
        accessorKey: 'due_date',
        header: 'Due date',
        cell: ({ row }) => teamDate(row.original.due_date),
    },
    {
        accessorKey: 'updated_at',
        header: 'Updated',
        cell: ({ row }) => teamDate(row.original.updated_at),
    },
];
const table = useVueTable({
    get data() {
        return props.rows;
    },
    columns,
    getCoreRowModel: getCoreRowModel(),
    manualPagination: true,
    manualSorting: true,
    manualFiltering: true,
});
const sortable = ['summary', 'priority', 'due_date', 'updated_at'];
</script>
<template>
    <div class="overflow-x-auto rounded-lg border bg-card">
        <table class="w-full text-left text-xs" aria-label="Project tickets">
            <thead
                class="border-b bg-muted/40 text-[11px] text-muted-foreground"
            >
                <tr v-for="group in table.getHeaderGroups()" :key="group.id">
                    <th
                        v-for="header in group.headers"
                        :key="header.id"
                        class="border-r px-3 py-2 font-medium last:border-r-0"
                        :aria-sort="
                            sort === header.column.id
                                ? direction === 'asc'
                                    ? 'ascending'
                                    : 'descending'
                                : undefined
                        "
                    >
                        <button
                            v-if="sortable.includes(header.column.id)"
                            class="flex items-center gap-2 text-left hover:text-foreground"
                            @click="emit('sort', header.column.id)"
                        >
                            <FlexRender
                                :render="header.column.columnDef.header"
                                :props="header.getContext()"
                            /><span v-if="sort === header.column.id">{{
                                direction === 'asc' ? '↑' : '↓'
                            }}</span></button
                        ><FlexRender
                            v-else
                            :render="header.column.columnDef.header"
                            :props="header.getContext()"
                        />
                    </th>
                </tr>
            </thead>
            <tbody>
                <tr
                    v-for="row in table.getRowModel().rows"
                    :key="row.id"
                    class="border-b last:border-0 hover:bg-muted/40"
                    :class="selectedId === row.original.id ? 'bg-accent' : ''"
                >
                    <td
                        v-for="cell in row.getVisibleCells()"
                        :key="cell.id"
                        class="border-r px-3 py-2.5 last:border-r-0"
                    >
                        <FlexRender
                            :render="cell.column.columnDef.cell"
                            :props="cell.getContext()"
                        />
                    </td>
                </tr>
                <tr v-if="!rows.length">
                    <td
                        :colspan="columns.length"
                        class="py-16 text-center text-muted-foreground"
                    >
                        No tickets match these filters.
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
