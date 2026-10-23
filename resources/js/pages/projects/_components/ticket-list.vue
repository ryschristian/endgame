<script setup lang="ts">
import { ChevronDown, Plus } from '@lucide/vue';
import type { TicketRow } from '@/pages/tickets/_components/types';
import { teamDate } from '@/pages/tickets/_components/types';
import type { MoveRequest, WorkflowStatus } from './workspace-types';
import TicketStatusSelect from './ticket-status-select.vue';
defineProps<{
    rows: TicketRow[];
    statuses: WorkflowStatus[];
    counts: Record<string, number>;
    canUpdate: boolean;
    busy: boolean;
}>();
const emit = defineEmits<{
    open: [id: number];
    move: [request: MoveRequest];
}>();
</script>
<template>
    <div class="overflow-x-auto border-y" aria-label="Grouped ticket list">
        <div
            class="grid min-w-[900px] grid-cols-[minmax(320px,1fr)_155px_150px_100px_120px_100px] gap-3 border-b px-4 py-2 text-[11px] text-muted-foreground"
        >
            <span>Ticket</span><span>Status</span><span>Assignee</span
            ><span>Priority</span><span>Due date</span><span>Type</span>
        </div>
        <details
            v-for="status in statuses"
            :key="status.key"
            open
            class="group border-b last:border-0"
        >
            <summary
                class="flex min-w-[900px] cursor-pointer list-none items-center gap-2 bg-muted/40 px-4 py-3 text-xs"
            >
                <ChevronDown
                    class="size-3 text-muted-foreground transition-transform group-not-open:-rotate-90"
                /><span
                    class="size-3 rounded-full border-2"
                    :style="{
                        borderColor: status.color,
                        backgroundColor: status.completed
                            ? status.color
                            : 'transparent',
                    }"
                /><span class="font-medium">{{ status.label }}</span
                ><span class="text-muted-foreground">{{
                    counts[status.key] ?? 0
                }}</span>
            </summary>
            <div
                v-for="ticket in rows.filter((t) => t.state === status.key)"
                :key="ticket.id"
                class="grid min-w-[900px] grid-cols-[minmax(320px,1fr)_155px_150px_100px_120px_100px] items-center gap-3 border-t px-4 py-2 text-xs hover:bg-muted/40"
            >
                <button
                    class="flex items-center gap-3 text-left"
                    :aria-label="`Open ${ticket.key}: ${ticket.summary}`"
                    @click="emit('open', ticket.id)"
                >
                    <span class="font-mono text-[10px] text-muted-foreground">{{
                        ticket.key
                    }}</span
                    ><span
                        :class="
                            ticket.completed
                                ? 'text-muted-foreground line-through'
                                : ''
                        "
                        >{{ ticket.summary }}</span
                    >
                </button>
                <TicketStatusSelect
                    v-if="canUpdate"
                    :ticket="ticket"
                    :statuses="statuses"
                    :disabled="busy"
                    @change="
                        emit('move', { ticket, state: $event, beforeId: null })
                    "
                /><span v-else>{{ ticket.stateLabel }}</span>
                <span class="truncate">{{
                    ticket.assignee?.name ?? 'Unassigned'
                }}</span
                ><span>{{ ticket.priority.name }}</span
                ><span>{{ teamDate(ticket.due_date) }}</span
                ><span
                    class="w-fit rounded border px-1.5 py-0.5 text-[10px] text-muted-foreground"
                    >{{ ticket.workType.name }}</span
                >
            </div>
            <p
                v-if="!rows.some((t) => t.state === status.key)"
                class="px-9 py-3 text-xs text-muted-foreground"
            >
                {{
                    counts[status.key]
                        ? 'No tickets on this page.'
                        : 'No tickets.'
                }}
            </p>
        </details>
    </div>
</template>
