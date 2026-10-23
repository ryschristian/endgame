<script setup lang="ts">
import type { WorkflowStatus } from './workspace-types';
import type { TicketRow } from '@/pages/tickets/_components/types';
defineProps<{
    ticket: TicketRow;
    statuses: WorkflowStatus[];
    disabled?: boolean;
}>();
const emit = defineEmits<{ change: [state: string] }>();
function change(event: Event, current: string | undefined) {
    const select = event.target as HTMLSelectElement;
    emit('change', select.value);
    select.value = current ?? '';
}
</script>
<template>
    <label class="inline-flex min-w-0 items-center gap-1.5">
        <span
            class="size-2.5 shrink-0 rounded-full border"
            :style="{
                backgroundColor: ticket.stateColor,
                borderColor: ticket.stateColor,
            }"
        />
        <select
            :aria-label="`Move ${ticket.key}`"
            :value="ticket.state"
            :disabled="disabled || !ticket.allowedStates?.length"
            class="max-w-36 cursor-pointer rounded border-0 bg-transparent py-1 pr-2 text-xs outline-offset-2 disabled:cursor-default disabled:opacity-60"
            @change="change($event, ticket.state)"
        >
            <option
                v-for="status in statuses.filter(
                    (s) =>
                        s.key === ticket.state ||
                        ticket.allowedStates?.includes(s.key),
                )"
                :key="status.key"
                :value="status.key"
            >
                {{ status.label }}
            </option>
        </select>
    </label>
</template>
