<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
} from '@/components/ui/dialog';
import TicketDatePicker from '@/pages/tickets/_components/ticket-date-picker.vue';
import type { TicketRow } from '@/pages/tickets/_components/types';
const props = defineProps<{ ticket: TicketRow; projectId: number }>();
const emit = defineEmits<{ close: [] }>();
const form = useForm({
    revision: props.ticket.revision,
    start_date: props.ticket.start_date ?? null,
    due_date: props.ticket.due_date,
});
function save() {
    form.patch(
        `/projects/${props.projectId}/tickets/${props.ticket.id}/schedule`,
        { preserveScroll: true, onSuccess: () => emit('close') },
    );
}
</script>
<template>
    <Dialog
        :open="true"
        @update:open="
            (open) => {
                if (!open) emit('close');
            }
        "
        ><DialogContent class="sm:max-w-md"
            ><DialogHeader
                ><DialogTitle>Ticket dates</DialogTitle
                ><DialogDescription
                    >{{ ticket.key }} · {{ ticket.summary }}</DialogDescription
                ></DialogHeader
            >
            <form class="space-y-4" @submit.prevent="save">
                <TicketDatePicker
                    v-model="form.start_date"
                    label="Start date"
                    :disabled="form.processing"
                /><TicketDatePicker
                    v-model="form.due_date"
                    label="Due date"
                    :disabled="form.processing"
                />
                <p
                    v-for="(error, key) in form.errors"
                    :key="key"
                    role="alert"
                    class="text-xs text-destructive"
                >
                    {{ error }}
                </p>
                <div class="flex justify-end gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        @click="emit('close')"
                        >Cancel</Button
                    ><Button type="submit" :disabled="form.processing"
                        >Save dates</Button
                    >
                </div>
            </form></DialogContent
        ></Dialog
    >
</template>
