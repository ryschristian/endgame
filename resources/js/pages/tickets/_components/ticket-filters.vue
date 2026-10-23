<script setup lang="ts">
import { reactive, computed } from 'vue';
import { parseDate, type DateValue } from '@internationalized/date';
import { RangeCalendar } from '@/components/ui/range-calendar';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { Button } from '@/components/ui/button';
import ChoiceCombobox from './choice-combobox.vue';
import AssigneeCombobox from './assignee-combobox.vue';
import type { Choice, Filters } from './types';
const props = defineProps<{
    filters: Filters;
    options: { workTypes: Choice[]; priorities: Choice[] };
    projectId: number;
    selectedAssignee?: { id: number; name: string } | null;
}>();
const emit = defineEmits<{ apply: [filters: Filters] }>();
const filters = reactive({ ...props.filters });
const type = computed({
    get: () => Number(filters.work_type_id) || 0,
    set: (v: number | null) => {
        filters.work_type_id = v || '';
    },
});
const priority = computed({
    get: () => Number(filters.priority_id) || 0,
    set: (v: number | null) => {
        filters.priority_id = v || '';
    },
});
const assignee = computed({
    get: () => Number(filters.assignee_id) || null,
    set: (v: number | null) => {
        filters.assignee_id = v ?? '';
    },
});
const range = computed({
    get: () => ({
        start: filters.due_from ? parseDate(filters.due_from) : undefined,
        end: filters.due_to ? parseDate(filters.due_to) : undefined,
    }),
    set: (value: {
        start: DateValue | undefined;
        end: DateValue | undefined;
    }) => {
        filters.due_from = value.start?.toString() ?? '';
        filters.due_to = value.end?.toString() ?? '';
    },
});
function clear() {
    Object.assign(filters, {
        q: '',
        work_type_id: '',
        priority_id: '',
        assignee_id: '',
        due_from: '',
        due_to: '',
    });
    emit('apply', { ...filters });
}
</script>
<template>
    <form
        role="search"
        aria-label="Filter tickets"
        class="flex flex-wrap items-end gap-3"
        @submit.prevent="emit('apply', { ...filters })"
    >
        <div class="min-w-40 flex-1 space-y-1.5">
            <label for="ticket-search" class="text-xs font-medium">Search</label
            ><input
                id="ticket-search"
                v-model="filters.q"
                type="search"
                maxlength="255"
                placeholder="Search tickets"
                class="h-9 w-full rounded-md border border-input bg-card px-3 text-sm sm:text-xs"
            />
        </div>
        <div class="w-36">
            <ChoiceCombobox
                id="filter-type"
                v-model="type"
                :options="[
                    { value: 0, label: 'All types' },
                    ...options.workTypes,
                ]"
                label="Type"
            />
        </div>
        <div class="w-36">
            <ChoiceCombobox
                id="filter-priority"
                v-model="priority"
                :options="[
                    { value: 0, label: 'All priorities' },
                    ...options.priorities,
                ]"
                label="Priority"
            />
        </div>
        <div class="w-40">
            <AssigneeCombobox
                id="filter-assignee"
                v-model="assignee"
                :project-id="projectId"
                :initial="selectedAssignee"
                label="Assignee"
            />
        </div>
        <label class="flex h-9 items-center gap-2 text-xs"
            ><input
                :checked="filters.assignee_id === 'unassigned'"
                type="checkbox"
                @change="
                    filters.assignee_id = ($event.target as HTMLInputElement)
                        .checked
                        ? 'unassigned'
                        : ''
                "
            />Unassigned only</label
        ><Popover
            ><PopoverTrigger as-child
                ><Button variant="outline" class="h-9 font-normal">{{
                    filters.due_from || filters.due_to
                        ? `${filters.due_from || 'Any'} – ${filters.due_to || 'Any'}`
                        : 'Due date range'
                }}</Button></PopoverTrigger
            ><PopoverContent class="w-auto p-0"
                ><RangeCalendar
                    v-model="range"
                    locale="en-PH" /></PopoverContent></Popover
        ><Button type="submit" class="h-9">Apply</Button
        ><Button type="button" variant="ghost" class="h-9" @click="clear"
            >Clear</Button
        >
    </form>
</template>
