<script setup lang="ts">
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
} from '@/components/ui/dialog';
import TicketForm from './ticket-form.vue';
import type { TicketProps, TicketDetail } from './types';
defineProps<{
    open: boolean;
    data: TicketProps;
    ticket?: TicketDetail | null;
}>();
defineEmits<{ close: [] }>();
</script>
<template>
    <Dialog
        :open="open"
        @update:open="
            (value) => {
                if (!value) $emit('close');
            }
        "
        ><DialogContent
            class="top-auto bottom-0 left-0 max-h-[92dvh] max-w-full translate-x-0 translate-y-0 overflow-y-auto rounded-b-none sm:top-1/2 sm:bottom-auto sm:left-1/2 sm:max-h-[90dvh] sm:max-w-xl sm:-translate-x-1/2 sm:-translate-y-1/2 sm:rounded-xl"
            ><DialogHeader
                ><DialogTitle>{{
                    ticket ? `Edit ${ticket.key}` : 'New ticket'
                }}</DialogTitle
                ><DialogDescription class="sr-only"
                    >Ticket details for
                    {{ data.project.name }}</DialogDescription
                ></DialogHeader
            ><TicketForm
                :key="ticket?.id ?? 'new'"
                :project-id="data.project.id"
                :ticket="ticket"
                :options="data.options"
                :defaults="data.defaults"
                :labels="data.fieldLabels"
                :disabled="Boolean(data.project.archived_at)"
                @saved="$emit('close')" /></DialogContent
    ></Dialog>
</template>
