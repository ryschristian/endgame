<script setup lang="ts">
import { useForm, usePage } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import FormField from '@/pages/_components/form-field.vue';
import ChoiceCombobox from './choice-combobox.vue';
import AssigneeCombobox from './assignee-combobox.vue';
import TicketDatePicker from './ticket-date-picker.vue';
import type { Choice, TicketDetail } from './types';
const props = defineProps<{
    projectId: number;
    ticket?: TicketDetail | null;
    options: { workTypes: Choice[]; priorities: Choice[] };
    defaults: { work_type_id: number; priority_id: number };
    labels: Record<string, string>;
    disabled: boolean;
}>();
const emit = defineEmits<{ saved: [] }>();
const page = usePage();
// Keep the revision the editor opened with, even if page props refresh during editing.
const initialRevision = props.ticket?.revision;
const form = useForm({
    summary: props.ticket?.summary ?? '',
    description: props.ticket?.description ?? '',
    acceptance_criteria: props.ticket?.acceptance_criteria ?? '',
    work_type_id:
        props.ticket?.work_type_id ??
        (props.defaults.work_type_id as number | null),
    priority_id:
        props.ticket?.priority_id ??
        (props.defaults.priority_id as number | null),
    assignee_id: props.ticket?.assignee_id ?? (null as number | null),
    estimate: props.ticket?.estimate ?? '',
    due_date: props.ticket?.due_date ?? (null as string | null),
    start_date: props.ticket?.start_date ?? (null as string | null),
});
function submit() {
    if (props.disabled || form.processing) return;
    const options = { preserveScroll: true, onSuccess: () => emit('saved') };
    if (props.ticket)
        form.transform((data) => ({
            ...data,
            revision: initialRevision,
        })).patch(
            `/projects/${props.projectId}/tickets/${props.ticket.id}`,
            options,
        );
    else {
        const view = page.url?.split('?')[0]?.split('/').pop();
        form.transform((data) => ({
            ...data,
            ...(['board', 'list', 'table', 'calendar', 'timeline'].includes(
                view ?? '',
            )
                ? { return_view: view }
                : {}),
        })).post(`/projects/${props.projectId}/tickets`, options);
    }
}
</script>
<template>
    <form class="space-y-5" @submit.prevent="submit">
        <fieldset
            :disabled="disabled || form.processing"
            class="space-y-4 disabled:opacity-60"
        >
            <FormField
                id="ticket-summary"
                v-model="form.summary"
                :label="labels.summary"
                :error="form.errors.summary"
                required
            />
            <div class="grid gap-4 sm:grid-cols-2">
                <ChoiceCombobox
                    id="ticket-type"
                    v-model="form.work_type_id"
                    :options="options.workTypes"
                    :label="labels.work_type_id"
                    :disabled="disabled || form.processing"
                /><ChoiceCombobox
                    id="ticket-priority"
                    v-model="form.priority_id"
                    :options="options.priorities"
                    :label="labels.priority_id"
                    :disabled="disabled || form.processing"
                /><AssigneeCombobox
                    v-model="form.assignee_id"
                    :project-id="projectId"
                    :initial="ticket?.assignee"
                    :label="labels.assignee_id"
                    :disabled="disabled || form.processing"
                /><TicketDatePicker
                    v-model="form.start_date"
                    :label="labels.start_date ?? 'Start date'"
                    :disabled="disabled || form.processing"
                /><TicketDatePicker
                    v-model="form.due_date"
                    :label="labels.due_date"
                    :disabled="disabled || form.processing"
                />
            </div>
            <p
                v-if="ticket && !ticket.assigneeAvailable"
                class="text-xs text-muted-foreground"
            >
                The current assignee is unavailable. You can retain or replace
                this assignment.
            </p>
            <div class="space-y-1.5">
                <label for="ticket-estimate" class="text-xs font-medium">{{
                    labels.estimate
                }}</label>
                <input
                    id="ticket-estimate"
                    v-model="form.estimate"
                    type="number"
                    step="0.01"
                    min="0"
                    max="9999.99"
                    class="h-9 w-full rounded-md border border-input bg-card px-3 text-base sm:text-xs"
                    :aria-invalid="Boolean(form.errors.estimate)"
                />
            </div>
            <div
                v-for="field in ['description', 'acceptance_criteria'] as const"
                :key="field"
                class="space-y-1.5"
            >
                <label :for="`ticket-${field}`" class="text-xs font-medium">{{
                    labels[field]
                }}</label
                ><textarea
                    :id="`ticket-${field}`"
                    v-model="form[field]"
                    rows="4"
                    :maxlength="field === 'description' ? 50000 : 20000"
                    class="w-full rounded-md border border-input bg-card px-3 py-2 text-base sm:text-xs"
                    :aria-invalid="Boolean(form.errors[field])"
                />
            </div>
        </fieldset>
        <div
            v-if="Object.keys(form.errors).length"
            role="alert"
            class="space-y-1 text-xs text-destructive"
        >
            <p v-for="(error, key) in form.errors" :key="key">{{ error }}</p>
        </div>
        <Button
            type="submit"
            :disabled="disabled || form.processing"
            class="ml-auto flex"
            >{{
                form.processing
                    ? 'Saving…'
                    : ticket
                      ? 'Save ticket'
                      : 'Create ticket'
            }}</Button
        >
    </form>
</template>
