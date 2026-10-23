<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
} from '@/components/ui/dialog';
import type { TicketRow } from '@/pages/tickets/_components/types';
import type { Workflow } from './workspace-types';
const props = defineProps<{
    projectId: number;
    ticket: TicketRow;
    state: string;
    workflow: Workflow;
    beforeId?: number | null;
    boardRevision?: number;
}>();
const emit = defineEmits<{ close: [] }>();
// Capture the versions that were reviewed when this dialog opened.
const form = useForm({
    revision: props.ticket.revision,
    state: props.state,
    resolution: '',
    reason: '',
    before_id: props.beforeId ?? null,
    board_revision: props.boardRevision,
});
const target = computed(() =>
    props.workflow.definition.statuses.find((s) => s.key === props.state)!,
);
function submit() {
    form.post(
        `/projects/${props.projectId}/tickets/${props.ticket.id}/${props.boardRevision === undefined ? 'transition' : 'move'}`,
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
                ><DialogTitle>Move to {{ target.label }}</DialogTitle
                ><DialogDescription
                    >{{ ticket.key }} · {{ ticket.summary }}</DialogDescription
                ></DialogHeader
            >
            <form class="space-y-4" @submit.prevent="submit">
                <div v-if="target.completed" class="space-y-1.5">
                    <label
                        for="transition-resolution"
                        class="text-xs font-medium"
                        >Resolution</label
                    ><select
                        id="transition-resolution"
                        v-model="form.resolution"
                        required
                        class="h-9 w-full rounded-md border bg-card px-3 text-sm"
                    >
                        <option value="" disabled>Choose outcome</option>
                        <option
                            v-for="outcome in workflow.definition.resolutions"
                            :key="outcome.key"
                            :value="outcome.key"
                        >
                            {{ outcome.label }}
                        </option>
                    </select>
                </div>
                <div
                    v-if="ticket.completed && !target.completed"
                    class="space-y-1.5"
                >
                    <label for="transition-reason" class="text-xs font-medium"
                        >Reopening reason</label
                    ><textarea
                        id="transition-reason"
                        v-model="form.reason"
                        required
                        maxlength="2000"
                        rows="3"
                        class="w-full rounded-md border p-3 text-sm"
                    />
                </div>
                <p
                    v-for="(error, field) in form.errors"
                    :key="field"
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
                    ><Button type="submit" :disabled="form.processing">{{
                        form.processing ? 'Saving…' : 'Change status'
                    }}</Button>
                </div>
            </form>
        </DialogContent></Dialog
    >
</template>
