<script setup lang="ts">
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
    SheetDescription,
} from '@/components/ui/sheet';
import { Link } from '@inertiajs/vue3';
import TicketDetails from './ticket-details.vue';
import type { TicketProps } from './types';
defineProps<{ data: TicketProps }>();
defineEmits<{ close: [] }>();
</script>
<template>
    <Sheet
        :open="Boolean(data.selectedTicket)"
        @update:open="
            (value) => {
                if (!value) $emit('close');
            }
        "
        ><SheetContent
            class="gap-0 overflow-y-auto p-0 data-[side=right]:w-full data-[side=right]:sm:max-w-[620px]"
            ><SheetHeader class="border-b px-5 py-4 pr-12"
                ><SheetTitle class="flex items-center gap-2 text-xs font-normal"
                    ><span class="truncate">{{ data.project.name }}</span
                    ><span class="text-muted-foreground">/</span
                    ><span
                        class="font-mono text-[10px] text-muted-foreground"
                        >{{ data.selectedTicket?.key }}</span
                    ><Link
                        v-if="data.selectedTicket"
                        :href="`/projects/${data.project.id}/tickets/${data.selectedTicket.id}`"
                        class="ml-auto underline underline-offset-4"
                        aria-label="Open full ticket details"
                        >Expand</Link
                    ></SheetTitle
                ><SheetDescription class="sr-only"
                    >Ticket details and activity</SheetDescription
                ></SheetHeader
            >
            <div class="p-5 sm:p-6">
                <TicketDetails
                    v-if="data.selectedTicket"
                    :key="data.selectedTicket.id"
                    :data="data"
                /></div></SheetContent
    ></Sheet>
</template>
