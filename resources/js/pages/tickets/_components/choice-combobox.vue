<script setup lang="ts">
import { computed } from 'vue';
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
    options: Choice[];
    label: string;
    id: string;
    disabled?: boolean;
}>();
const model = defineModel<number | null>({ default: null });
const selected = computed(
    () =>
        props.options.find((option) => option.value === model.value)?.label ??
        'Choose…',
);
</script>
<template>
    <div class="space-y-1.5">
        <label :for="id" class="text-xs font-medium">{{ label }}</label
        ><Combobox v-model="model" :disabled="disabled"
            ><ComboboxAnchor as-child
                ><ComboboxTrigger
                    :id="id"
                    :aria-label="label"
                    class="flex h-9 w-full items-center justify-between rounded-md border border-input bg-card px-3 text-xs"
                    >{{ selected
                    }}<span aria-hidden="true">⌄</span></ComboboxTrigger
                ></ComboboxAnchor
            ><ComboboxList
                ><ComboboxInput
                    :placeholder="`Search ${label.toLowerCase()}`"
                    :aria-label="`Search ${label.toLowerCase()}`" /><ComboboxEmpty
                    >No matches.</ComboboxEmpty
                ><ComboboxViewport
                    ><ComboboxItem
                        v-for="option in options"
                        :key="option.value"
                        :value="option.value"
                        :disabled="option.disabled"
                        >{{ option.label
                        }}<ComboboxItemIndicator /></ComboboxItem></ComboboxViewport></ComboboxList
        ></Combobox>
    </div>
</template>
