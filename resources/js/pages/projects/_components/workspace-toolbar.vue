<script setup lang="ts">
import { reactive } from 'vue';
import { ArrowDownUp, ListFilter, Plus, Search } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import ChoiceCombobox from '@/pages/tickets/_components/choice-combobox.vue';
import AssigneeCombobox from '@/pages/tickets/_components/assignee-combobox.vue';
import type { Choice } from '@/pages/tickets/_components/types';
import type { WorkspaceFilters, WorkflowStatus } from './workspace-types';
const props = defineProps<{
    filters: WorkspaceFilters;
    statuses: WorkflowStatus[];
    options: { workTypes: Choice[]; priorities: Choice[] };
    sortOptions: { value: string; label: string }[];
    projectId: number;
    canCreate: boolean;
    disabled: boolean;
    selectedAssignee?: { id: number; name: string } | null;
}>();
const emit = defineEmits<{ apply: [filters: WorkspaceFilters]; create: [] }>();
const filters = reactive({ ...props.filters });
function apply() {
    emit('apply', { ...filters });
}
function clear() {
    Object.assign(filters, {
        q: '',
        state: '',
        work_type_id: '',
        priority_id: '',
        assignee_id: '',
    });
    apply();
}
</script>
<template>
    <div class="flex flex-wrap items-center gap-2 border-b pb-3">
        <form
            class="relative"
            role="search"
            aria-label="Search project tickets"
            @submit.prevent="apply"
        >
            <Search
                class="pointer-events-none absolute top-2.5 left-2.5 size-3.5 text-muted-foreground"
            /><label for="workspace-ticket-search" class="sr-only"
                >Search tickets</label
            ><input
                id="workspace-ticket-search"
                v-model="filters.q"
                type="search"
                maxlength="255"
                placeholder="Search tickets"
                class="h-8 w-52 rounded-md border bg-card pr-8 pl-8 text-xs"
            /><button
                type="submit"
                class="absolute top-1 right-1 rounded p-1 text-muted-foreground hover:bg-muted"
                aria-label="Run ticket search"
            >
                <span aria-hidden="true">↵</span>
            </button>
        </form>
        <Popover
            ><PopoverTrigger as-child
                ><Button variant="ghost" class="h-8 gap-1.5 px-2 text-xs"
                    ><ListFilter class="size-3.5" />Filter<span
                        v-if="
                            filters.state ||
                            filters.work_type_id ||
                            filters.priority_id ||
                            filters.assignee_id
                        "
                        class="size-1.5 rounded-full bg-primary" /></Button></PopoverTrigger
            ><PopoverContent align="start" class="w-80 space-y-4"
                ><div class="space-y-1.5">
                    <label
                        for="workspace-status-filter"
                        class="text-xs font-medium"
                        >Status</label
                    ><select
                        id="workspace-status-filter"
                        v-model="filters.state"
                        class="h-9 w-full rounded-md border bg-card px-2 text-xs"
                    >
                        <option value="">All statuses</option>
                        <option
                            v-for="status in statuses"
                            :key="status.key"
                            :value="status.key"
                        >
                            {{ status.label }}
                        </option>
                    </select>
                </div>
                <ChoiceCombobox
                    id="workspace-type-filter"
                    :model-value="Number(filters.work_type_id) || 0"
                    :options="[
                        { value: 0, label: 'All types' },
                        ...options.workTypes,
                    ]"
                    label="Type"
                    @update:model-value="filters.work_type_id = $event || ''"
                /><ChoiceCombobox
                    id="workspace-priority-filter"
                    :model-value="Number(filters.priority_id) || 0"
                    :options="[
                        { value: 0, label: 'All priorities' },
                        ...options.priorities,
                    ]"
                    label="Priority"
                    @update:model-value="filters.priority_id = $event || ''"
                /><AssigneeCombobox
                    :model-value="Number(filters.assignee_id) || null"
                    :project-id="projectId"
                    :initial="selectedAssignee"
                    label="Assignee"
                    @update:model-value="filters.assignee_id = $event ?? ''"
                /><label class="flex items-center gap-2 text-xs"
                    ><input
                        type="checkbox"
                        :checked="filters.assignee_id === 'unassigned'"
                        @change="
                            filters.assignee_id = (
                                $event.target as HTMLInputElement
                            ).checked
                                ? 'unassigned'
                                : ''
                        "
                    />Unassigned only</label
                >
                <div class="flex justify-between">
                    <Button variant="ghost" @click="clear">Clear</Button
                    ><Button @click="apply">Apply filters</Button>
                </div></PopoverContent
            ></Popover
        >
        <label class="flex items-center gap-1 text-xs text-muted-foreground"
            ><ArrowDownUp class="size-3.5" /><span class="sr-only"
                >Sort tickets</span
            ><select
                v-model="filters.sort"
                class="h-8 max-w-36 rounded bg-transparent text-xs text-foreground"
                @change="apply"
            >
                <option
                    v-for="option in sortOptions"
                    :key="option.value"
                    :value="option.value"
                >
                    Sort: {{ option.label }}
                </option>
            </select></label
        ><button
            type="button"
            :aria-label="
                filters.direction === 'asc'
                    ? 'Sort descending'
                    : 'Sort ascending'
            "
            class="rounded px-1.5 py-1 text-xs text-muted-foreground hover:bg-muted"
            @click="
                filters.direction =
                    filters.direction === 'asc' ? 'desc' : 'asc';
                apply();
            "
        >
            {{ filters.direction === 'asc' ? '↑' : '↓' }}
        </button>
        <Button
            v-if="canCreate"
            :disabled="disabled"
            class="ml-auto h-8 gap-1.5 text-xs"
            @click="emit('create')"
            ><Plus class="size-3.5" />New ticket</Button
        >
    </div>
</template>
