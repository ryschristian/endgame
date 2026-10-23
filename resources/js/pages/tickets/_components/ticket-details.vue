<script setup lang="ts">
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import TicketHistory from './ticket-history.vue';
import TicketFormDialog from './ticket-form-dialog.vue';
import type { TicketProps } from './types';
import { teamDate } from './types';
import TicketStatusSelect from '@/pages/projects/_components/ticket-status-select.vue';
import TicketTransitionDialog from '@/pages/projects/_components/ticket-transition-dialog.vue';
defineProps<{ data: TicketProps }>();
const editing = ref(false);
const target = ref<string | null>(null);
const initials = (name: string) =>
    name
        .split(/\s+/)
        .slice(0, 2)
        .map((part) => part[0])
        .join('')
        .toUpperCase();
</script>
<template>
    <div v-if="data.selectedTicket" class="px-1">
        <div class="mb-6 flex items-start justify-between gap-4">
            <h2 class="min-w-0 text-xl leading-snug font-semibold break-words">
                {{ data.selectedTicket.summary }}
            </h2>
            <Button
                v-if="data.ticketCan.update"
                variant="outline"
                class="shrink-0"
                :disabled="Boolean(data.project.archived_at)"
                @click="editing = true"
                >Edit ticket</Button
            >
        </div>
        <dl class="space-y-4 text-xs">
            <div
                class="grid grid-cols-[120px_minmax(0,1fr)] items-center gap-3"
            >
                <dt class="text-muted-foreground">
                    {{ data.fieldLabels.state }}
                </dt>
                <dd class="flex items-center gap-2">
                    <TicketStatusSelect
                        v-if="data.ticketCan.update && data.workflow"
                        :ticket="data.selectedTicket"
                        :statuses="data.workflow.definition.statuses"
                        :disabled="Boolean(data.project.archived_at)"
                        @change="target = $event"
                    />
                    <span
                        v-else
                        aria-hidden="true"
                        class="size-3 rounded-full border border-stone-400"
                    /><span v-if="!data.ticketCan.update || !data.workflow">{{
                        data.selectedTicket.stateLabel
                    }}</span>
                </dd>
            </div>
            <div
                v-for="field in [
                    {
                        label: data.fieldLabels.work_type_id,
                        value: data.selectedTicket.workType.name,
                    },
                    {
                        label: data.fieldLabels.priority_id,
                        value: data.selectedTicket.priority.name,
                    },
                    {
                        label: data.fieldLabels.start_date,
                        value: teamDate(data.selectedTicket.start_date ?? null),
                    },
                    {
                        label: data.fieldLabels.due_date,
                        value: teamDate(data.selectedTicket.due_date),
                    },
                    {
                        label: data.fieldLabels.estimate,
                        value: data.selectedTicket.estimate ?? '—',
                    },
                    {
                        label: data.fieldLabels.reporter_id,
                        value: data.selectedTicket.reporter.name,
                    },
                ]"
                :key="field.label"
                class="grid grid-cols-[120px_minmax(0,1fr)] gap-3"
            >
                <dt class="text-muted-foreground">{{ field.label }}</dt>
                <dd>{{ field.value }}</dd>
            </div>
            <div
                class="grid grid-cols-[120px_minmax(0,1fr)] items-center gap-3"
            >
                <dt class="text-muted-foreground">
                    {{ data.fieldLabels.assignee_id }}
                </dt>
                <dd class="flex items-center gap-2">
                    <span
                        v-if="data.selectedTicket.assignee"
                        class="flex size-6 shrink-0 items-center justify-center rounded-full bg-violet-100 text-[9px] text-violet-700"
                        >{{ initials(data.selectedTicket.assignee.name) }}</span
                    >{{ data.selectedTicket.assignee?.name ?? 'Unassigned'
                    }}<span
                        v-if="!data.selectedTicket.assigneeAvailable"
                        class="text-muted-foreground"
                        >(Unavailable)</span
                    >
                </dd>
            </div>
        </dl>
        <p
            v-if="data.selectedTicket.resolution"
            class="mt-4 text-xs text-muted-foreground"
        >
            Resolution:
            {{
                data.workflow?.definition.resolutions.find(
                    (r) => r.key === data.selectedTicket?.resolution,
                )?.label ?? data.selectedTicket.resolution
            }}
            · {{ teamDate(data.selectedTicket.completed_at ?? null, true) }}
        </p>
        <TicketTransitionDialog
            v-if="target && data.workflow"
            :key="target"
            :project-id="data.project.id"
            :ticket="data.selectedTicket"
            :state="target"
            :workflow="data.workflow"
            @close="target = null"
        />
        <section
            v-for="field in ['description', 'acceptance_criteria'] as const"
            :key="field"
            class="mt-7 space-y-3"
        >
            <h3 class="text-xs font-medium">{{ data.fieldLabels[field] }}</h3>
            <p
                class="text-xs leading-6 break-words whitespace-pre-wrap"
                :class="
                    !data.selectedTicket[field] ? 'text-muted-foreground' : ''
                "
            >
                {{ data.selectedTicket[field] || 'Not added yet.' }}
            </p>
        </section>
        <TicketHistory
            v-if="data.history"
            :history="data.history"
            :labels="data.fieldLabels"
        /><TicketFormDialog
            :open="editing"
            :data="data"
            :ticket="data.selectedTicket"
            @close="editing = false"
        />
    </div>
</template>
