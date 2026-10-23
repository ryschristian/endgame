<script setup lang="ts">
import { ref } from 'vue';
import { CalendarDays, CheckCircle2, Plus, Signal } from '@lucide/vue';
import type { TicketRow } from '@/pages/tickets/_components/types';
import type { MoveRequest, WorkflowStatus } from './workspace-types';
import TicketStatusSelect from './ticket-status-select.vue';
const props = defineProps<{
    rows: TicketRow[];
    statuses: WorkflowStatus[];
    counts: Record<string, number>;
    canUpdate: boolean;
    canCreate: boolean;
    busy: boolean;
    manual: boolean;
    initial?: string;
}>();
const emit = defineEmits<{
    open: [id: number];
    create: [];
    move: [request: MoveRequest];
}>();
const dragging = ref<number | null>(null);
const over = ref<string | null>(null);
const initials = (name: string) =>
    name
        .split(/\s+/)
        .slice(0, 2)
        .map((s) => s[0])
        .join('')
        .toUpperCase();
const shortDate = (date: string) =>
    new Intl.DateTimeFormat('en-PH', {
        month: 'short',
        day: 'numeric',
        timeZone: 'UTC',
    }).format(new Date(`${date}T00:00:00Z`));
function canDrop(state: string) {
    const ticket = props.rows.find((t) => t.id === dragging.value);
    return (
        props.canUpdate &&
        props.manual &&
        !props.busy &&
        ticket &&
        (ticket.state === state || ticket.allowedStates?.includes(state))
    );
}
function drag(event: DragEvent, ticket: TicketRow) {
    dragging.value = ticket.id;
    if (event.dataTransfer) {
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', String(ticket.id));
    }
}
function drop(state: string, beforeId: number | null = null) {
    if (canDrop(state) && beforeId !== dragging.value) {
        const ticket = props.rows.find((t) => t.id === dragging.value)!;
        emit('move', { ticket, state, beforeId });
    }
    dragging.value = null;
    over.value = null;
}
</script>
<template>
    <div
        class="flex min-h-[60vh] items-start gap-3 overflow-x-auto pb-5"
        aria-label="Project board"
    >
        <section
            v-for="status in statuses"
            :key="status.key"
            :aria-label="`${status.label} column`"
            class="w-[282px] shrink-0 rounded-xl border border-border/60 bg-muted/75 p-2 transition-colors"
            :class="over === status.key ? 'ring-2 ring-primary/40' : ''"
            @dragover="
                (event) => {
                    if (canDrop(status.key)) {
                        event.preventDefault();
                        over = status.key;
                    }
                }
            "
            @dragleave.self="over = null"
            @drop.prevent="drop(status.key)"
        >
            <header class="flex h-8 items-center gap-2 px-1.5 text-xs">
                <CheckCircle2
                    v-if="status.completed"
                    class="size-3.5"
                    :style="{ color: status.color }"
                />
                <span
                    v-else
                    class="size-3 rounded-full border-2"
                    :style="{ borderColor: status.color }"
                />
                <h2 class="font-medium">{{ status.label }}</h2>
                <span class="text-muted-foreground">{{
                    counts[status.key] ?? 0
                }}</span>
                <button
                    v-if="canCreate && status.key === initial"
                    type="button"
                    aria-label="Create ticket"
                    class="ml-auto rounded p-1 text-muted-foreground hover:bg-background"
                    :disabled="busy"
                    @click="emit('create')"
                >
                    <Plus class="size-3.5" />
                </button>
            </header>
            <div class="mt-1 space-y-1.5">
                <article
                    v-for="ticket in rows.filter((t) => t.state === status.key)"
                    :key="ticket.id"
                    :data-ticket-id="ticket.id"
                    :draggable="canUpdate && manual && !busy"
                    class="rounded-lg border bg-card px-3 py-2.5 shadow-xs transition-shadow hover:shadow-sm"
                    :class="[
                        canUpdate && manual
                            ? 'cursor-grab active:cursor-grabbing'
                            : '',
                        dragging === ticket.id ? 'opacity-40' : '',
                    ]"
                    @dragstart="drag($event, ticket)"
                    @dragend="
                        dragging = null;
                        over = null;
                    "
                    @dragover.stop="
                        (event) => {
                            if (canDrop(status.key)) event.preventDefault();
                        }
                    "
                    @drop.stop.prevent="drop(status.key, ticket.id)"
                >
                    <span
                        class="mb-2 inline-flex max-w-full items-center gap-1 rounded border px-1.5 py-0.5 text-[10px] text-muted-foreground"
                        ><span class="size-1.5 rounded-sm bg-violet-500" />{{
                            ticket.workType.name
                        }}</span
                    >
                    <button
                        type="button"
                        :aria-label="`Open ${ticket.key}: ${ticket.summary}`"
                        class="mb-3 block w-full text-left text-[13px] leading-5 font-medium break-words hover:text-primary"
                        :class="
                            ticket.completed
                                ? 'text-muted-foreground line-through'
                                : ''
                        "
                        @click="emit('open', ticket.id)"
                    >
                        {{ ticket.summary }}
                    </button>
                    <div
                        class="flex items-center gap-2 text-[10px] text-muted-foreground"
                    >
                        <span class="font-mono">{{ ticket.key }}</span
                        ><Signal
                            class="size-3"
                            :aria-label="ticket.priority.name"
                            :title="ticket.priority.name"
                        /><span
                            v-if="ticket.due_date"
                            class="flex items-center gap-1"
                            ><CalendarDays class="size-3" />{{
                                shortDate(ticket.due_date)
                            }}</span
                        ><span
                            v-if="ticket.assignee"
                            class="ml-auto flex size-5 shrink-0 items-center justify-center rounded-full bg-violet-100 text-[8px] text-violet-800"
                            :title="ticket.assignee.name"
                            >{{ initials(ticket.assignee.name) }}</span
                        >
                    </div>
                    <div v-if="canUpdate" class="mt-2 border-t pt-1">
                        <TicketStatusSelect
                            :ticket="ticket"
                            :statuses="statuses"
                            :disabled="busy"
                            @change="
                                emit('move', {
                                    ticket,
                                    state: $event,
                                    beforeId: null,
                                })
                            "
                        />
                    </div>
                </article>
                <p
                    v-if="!rows.some((t) => t.state === status.key)"
                    class="px-2 py-5 text-center text-xs text-muted-foreground"
                >
                    {{
                        counts[status.key]
                            ? 'No tickets on this page'
                            : 'No tickets'
                    }}
                </p>
            </div>
            <button
                v-if="canCreate && status.key === initial"
                type="button"
                class="mt-2 flex w-full items-center gap-2 rounded-md px-2 py-2 text-xs text-muted-foreground hover:bg-background"
                :disabled="busy"
                @click="emit('create')"
            >
                <Plus class="size-3.5" />Add ticket
            </button>
        </section>
    </div>
</template>
