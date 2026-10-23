<script setup lang="ts">
import { ref, computed, onBeforeUnmount } from 'vue';
import {
    Combobox,
    ComboboxAnchor,
    ComboboxEmpty,
    ComboboxInput,
    ComboboxItem,
    ComboboxItemIndicator,
    ComboboxList,
    ComboboxTrigger,
    ComboboxViewport,
} from '@/components/ui/combobox';
import type { Choice } from './types';
const props = defineProps<{
    projectId: number;
    initial?: { id: number; name: string } | null;
    disabled?: boolean;
    label?: string;
    id?: string;
}>();
const model = defineModel<number | null>({ default: null });
const options = ref<Choice[]>(
    props.initial
        ? [{ value: props.initial.id, label: props.initial.name }]
        : [],
);
const selectedName = ref(props.initial?.name ?? 'Unassigned');
const label = computed(() =>
    model.value
        ? (options.value.find((option) => option.value === model.value)
              ?.label ?? selectedName.value)
        : 'Unassigned',
);
const loading = ref(false);
const error = ref('');
let abort: AbortController | undefined;
let timer: ReturnType<typeof setTimeout>;
async function load(q = '') {
    abort?.abort();
    abort = new AbortController();
    loading.value = true;
    error.value = '';
    try {
        const response = await fetch(
            `/projects/${props.projectId}/ticket-assignees?q=${encodeURIComponent(q)}`,
            { signal: abort.signal, headers: { Accept: 'application/json' } },
        );
        if (!response.ok) throw new Error();
        options.value = await response.json();
    } catch (failure) {
        if (!(failure instanceof DOMException && failure.name === 'AbortError'))
            error.value = 'Could not load members. Try again.';
    } finally {
        loading.value = false;
    }
}
function search(value: string) {
    clearTimeout(timer);
    timer = setTimeout(() => load(value), 200);
}
function choose(value: unknown) {
    const id = typeof value === 'number' && value > 0 ? value : null;
    selectedName.value =
        options.value.find((option) => option.value === id)?.label ??
        'Unassigned';
    model.value = id;
}
onBeforeUnmount(() => {
    clearTimeout(timer);
    abort?.abort();
});
</script>
<template>
    <div class="space-y-1.5">
        <label :for="id ?? 'ticket-assignee'" class="text-xs font-medium">{{
            props.label ?? 'Assignee'
        }}</label
        ><Combobox
            :model-value="model ?? 0"
            :disabled="disabled"
            :ignore-filter="true"
            @update:model-value="choose"
            @update:open="
                (value) => {
                    if (value) load();
                }
            "
            ><ComboboxAnchor as-child
                ><ComboboxTrigger
                    :id="id ?? 'ticket-assignee'"
                    :aria-label="props.label ?? 'Assignee'"
                    class="flex h-9 w-full items-center justify-between rounded-md border border-input bg-card px-3 text-xs"
                    >{{ label
                    }}<span aria-hidden="true">⌄</span></ComboboxTrigger
                ></ComboboxAnchor
            ><ComboboxList
                ><ComboboxInput
                    placeholder="Search project members"
                    aria-label="Search project members"
                    @update:model-value="search" />
                <p v-if="loading || error" role="status" class="p-3 text-xs">
                    {{ error || 'Loading members…' }}
                </p>
                <ComboboxEmpty v-else>No eligible members.</ComboboxEmpty
                ><ComboboxViewport
                    ><ComboboxItem :value="0"
                        >Unassigned<ComboboxItemIndicator /></ComboboxItem
                    ><ComboboxItem
                        v-for="option in options"
                        :key="option.value"
                        :value="option.value"
                        >{{ option.label
                        }}<ComboboxItemIndicator /></ComboboxItem></ComboboxViewport></ComboboxList
        ></Combobox>
    </div>
</template>
