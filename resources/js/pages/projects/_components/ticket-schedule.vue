<script setup lang="ts">
import { computed, ref } from 'vue';
import { CalendarDays, ChevronLeft, ChevronRight, Pencil } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import type { TicketRow } from '@/pages/tickets/_components/types';
import { monthDays, moveDates } from './schedule-dates';
const props = defineProps<{
    rows: TicketRow[];
    month: string;
    timeline: boolean;
    canUpdate: boolean;
    busy: boolean;
    today: string;
}>();
const emit = defineEmits<{
    open: [id: number];
    edit: [ticket: TicketRow];
    schedule: [ticket: TicketRow, start: string | null, due: string | null];
    month: [month: string];
}>();
const days = computed(() => monthDays(props.month));
const title = computed(() =>
    new Intl.DateTimeFormat('en-PH', {
        month: 'long',
        year: 'numeric',
        timeZone: 'UTC',
    }).format(new Date(`${props.month}-01T00:00:00Z`)),
);
const dragging = ref<number | null>(null);
const unscheduled = computed(() =>
    props.rows.filter((ticket) => !ticket.due_date),
);
const scheduled = computed(() =>
    props.rows.filter((ticket) => ticket.due_date),
);
function changeMonth(offset: number) {
    const date = new Date(`${props.month}-01T00:00:00Z`);
    date.setUTCMonth(date.getUTCMonth() + offset);
    emit('month', date.toISOString().slice(0, 7));
}
function drag(event: DragEvent, ticket: TicketRow) {
    dragging.value = ticket.id;
    if (event.dataTransfer) {
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', String(ticket.id));
    }
}
function drop(day: string, onlyTicket?: TicketRow) {
    const ticket = props.rows.find((t) => t.id === dragging.value);
    if (
        !ticket ||
        !props.canUpdate ||
        props.busy ||
        (onlyTicket && onlyTicket.id !== ticket.id)
    )
        return;
    if (props.timeline) {
        const dates = moveDates(ticket, day);
        emit('schedule', ticket, dates.start_date, dates.due_date);
    } else
        emit(
            'schedule',
            ticket,
            ticket.start_date && ticket.start_date <= day
                ? ticket.start_date
                : day,
            day,
        );
    dragging.value = null;
}
function bar(ticket: TicketRow) {
    const start = ticket.start_date ?? ticket.due_date!;
    const first = days.value[0]!;
    const last = days.value[41]!;
    const left = Math.max(
        0,
        Math.round((Date.parse(start) - Date.parse(first)) / 86400000),
    );
    const right = Math.min(
        41,
        Math.round(
            (Date.parse(ticket.due_date!) - Date.parse(first)) / 86400000,
        ),
    );
    return {
        left: `${(left / 42) * 100}%`,
        width: `${((right - left + 1) / 42) * 100}%`,
        backgroundColor: ticket.stateColor,
        display: start > last || ticket.due_date! < first ? 'none' : undefined,
    };
}
function dropOnLane(event: DragEvent, ticket: TicketRow) {
    const bounds = (event.currentTarget as HTMLElement).getBoundingClientRect();
    const index = Math.max(
        0,
        Math.min(
            41,
            Math.floor(((event.clientX - bounds.left) / bounds.width) * 42),
        ),
    );
    const date = days.value[index];
    if (date) drop(date, ticket);
}
</script>
<template>
    <div class="space-y-4">
        <div class="flex flex-wrap items-center gap-2">
            <CalendarDays class="size-4 text-muted-foreground" />
            <h2 class="mr-3 text-sm font-medium">{{ title }}</h2>
            <Button
                size="icon"
                variant="outline"
                aria-label="Previous month"
                @click="changeMonth(-1)"
                ><ChevronLeft class="size-4" /></Button
            ><Button variant="outline" @click="emit('month', today.slice(0, 7))"
                >Today</Button
            ><Button
                size="icon"
                variant="outline"
                aria-label="Next month"
                @click="changeMonth(1)"
                ><ChevronRight class="size-4" /></Button
            ><label
                class="ml-auto flex items-center gap-2 text-xs text-muted-foreground"
                >Month<input
                    type="month"
                    :value="month"
                    class="rounded-md border bg-card px-2 py-1.5 text-xs"
                    @change="
                        ($event.target as HTMLInputElement).value &&
                        emit('month', ($event.target as HTMLInputElement).value)
                    "
            /></label>
        </div>
        <div v-if="!timeline" class="overflow-x-auto rounded-lg border">
            <div class="grid min-w-[770px] grid-cols-7 bg-muted/40">
                <div
                    v-for="day in [
                        'Mon',
                        'Tue',
                        'Wed',
                        'Thu',
                        'Fri',
                        'Sat',
                        'Sun',
                    ]"
                    :key="day"
                    class="border-r px-3 py-2 text-center text-[11px] text-muted-foreground last:border-r-0"
                >
                    {{ day }}
                </div>
            </div>
            <div class="grid min-w-[770px] grid-cols-7">
                <div
                    v-for="day in days"
                    :key="day"
                    :data-date="day"
                    class="min-h-32 border-t border-r p-1.5 last:border-r-0"
                    :class="day.slice(0, 7) !== month ? 'bg-muted/35' : ''"
                    @dragover="
                        (event) => {
                            if (canUpdate && !busy && dragging)
                                event.preventDefault();
                        }
                    "
                    @drop.prevent="drop(day)"
                >
                    <time
                        :datetime="day"
                        class="mb-2 ml-1 inline-flex size-6 items-center justify-center rounded-full text-[11px]"
                        :class="
                            day === today
                                ? 'bg-primary text-primary-foreground'
                                : 'text-muted-foreground'
                        "
                        >{{ Number(day.slice(-2)) }}</time
                    >
                    <div
                        v-for="ticket in scheduled.filter(
                            (t) => t.due_date === day,
                        )"
                        :key="ticket.id"
                        :draggable="canUpdate && !busy"
                        class="mb-1 flex items-center gap-1 rounded border bg-card px-2 py-1.5 text-[11px]"
                        :style="{
                            borderLeft: `3px solid ${ticket.stateColor}`,
                        }"
                        @dragstart="drag($event, ticket)"
                        @dragend="dragging = null"
                    >
                        <button
                            class="min-w-0 flex-1 truncate text-left"
                            :title="ticket.summary"
                            @click="emit('open', ticket.id)"
                        >
                            {{ ticket.summary }}</button
                        ><button
                            v-if="canUpdate"
                            :disabled="busy"
                            :aria-label="`Edit dates for ${ticket.key}`"
                            class="rounded p-1 hover:bg-muted"
                            @click="emit('edit', ticket)"
                        >
                            <Pencil class="size-3" />
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <div
            v-else
            class="overflow-x-auto rounded-lg border"
            aria-label="Ticket timeline"
        >
            <div class="min-w-[1320px]">
                <div class="grid grid-cols-[260px_1fr] border-b bg-muted/40">
                    <div class="px-4 py-3 text-xs font-medium">Ticket</div>
                    <div class="grid grid-cols-42">
                        <div
                            v-for="day in days"
                            :key="day"
                            class="border-l py-2 text-center text-[10px] text-muted-foreground"
                            :class="
                                day === today
                                    ? 'bg-primary/10 font-semibold text-primary'
                                    : ''
                            "
                        >
                            {{ Number(day.slice(-2)) }}
                        </div>
                    </div>
                </div>
                <div
                    v-for="ticket in rows"
                    :key="ticket.id"
                    :data-timeline-ticket="ticket.id"
                    class="grid grid-cols-[260px_1fr] border-b last:border-b-0"
                >
                    <div class="flex items-center gap-2 px-3 py-3">
                        <button
                            class="min-w-0 flex-1 truncate text-left text-xs"
                            @click="emit('open', ticket.id)"
                        >
                            {{ ticket.summary }}</button
                        ><button
                            v-if="canUpdate"
                            :disabled="busy"
                            :aria-label="`Edit dates for ${ticket.key}`"
                            class="rounded p-1 hover:bg-muted"
                            @click="emit('edit', ticket)"
                        >
                            <Pencil class="size-3 text-muted-foreground" />
                        </button>
                    </div>
                    <div
                        class="relative min-h-12"
                        @dragover="
                            (event) => {
                                if (
                                    canUpdate &&
                                    !busy &&
                                    dragging === ticket.id
                                )
                                    event.preventDefault();
                            }
                        "
                        @drop.prevent="dropOnLane($event, ticket)"
                    >
                        <div class="absolute inset-0 grid grid-cols-42">
                            <div
                                v-for="day in days"
                                :key="day"
                                :data-timeline-date="day"
                                class="border-l hover:bg-muted/60"
                                :class="day === today ? 'bg-primary/5' : ''"
                                @dragover="
                                    (event) => {
                                        if (
                                            canUpdate &&
                                            !busy &&
                                            dragging === ticket.id
                                        )
                                            event.preventDefault();
                                    }
                                "
                                @drop.stop.prevent="drop(day, ticket)"
                            />
                        </div>
                        <span
                            v-if="!ticket.due_date"
                            class="pointer-events-none absolute top-4 left-3 text-[10px] text-muted-foreground"
                            >Unscheduled</span
                        ><button
                            v-if="ticket.due_date"
                            :draggable="canUpdate && !busy"
                            class="absolute top-3 h-6 cursor-grab overflow-hidden rounded px-2 text-left text-[10px] font-medium text-white shadow-xs active:cursor-grabbing"
                            :style="bar(ticket)"
                            :title="`${ticket.start_date ?? ticket.due_date} → ${ticket.due_date}: ${ticket.summary}`"
                            @click="emit('open', ticket.id)"
                            @dragstart="drag($event, ticket)"
                            @dragend="dragging = null"
                        >
                            {{ ticket.key }}
                        </button>
                    </div>
                </div>
                <p
                    v-if="!rows.length"
                    class="p-10 text-center text-sm text-muted-foreground"
                >
                    No scheduled tickets in this period.
                </p>
            </div>
        </div>
        <section class="rounded-lg border">
            <h3 class="border-b px-4 py-3 text-xs font-medium">
                Unscheduled
                <span class="ml-1 text-muted-foreground">{{
                    unscheduled.length
                }}</span>
            </h3>
            <div v-if="unscheduled.length" class="flex flex-wrap gap-2 p-3">
                <div
                    v-for="ticket in unscheduled"
                    :key="ticket.id"
                    :draggable="canUpdate && !busy"
                    class="flex items-center gap-2 rounded-md border bg-card px-3 py-2 text-xs"
                    @dragstart="drag($event, ticket)"
                    @dragend="dragging = null"
                >
                    <button @click="emit('open', ticket.id)">
                        {{ ticket.summary }}</button
                    ><button
                        v-if="canUpdate"
                        :disabled="busy"
                        :aria-label="`Set dates for ${ticket.key}`"
                        class="rounded p-1 hover:bg-muted"
                        @click="emit('edit', ticket)"
                    >
                        <CalendarDays class="size-3.5 text-muted-foreground" />
                    </button>
                </div>
            </div>
            <p v-else class="px-4 py-4 text-xs text-muted-foreground">
                All tickets on this page have a due date.
            </p>
        </section>
    </div>
</template>
