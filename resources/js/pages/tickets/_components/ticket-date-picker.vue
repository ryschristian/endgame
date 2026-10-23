<script setup lang="ts">
import { computed } from 'vue';
import { parseDate, type DateValue } from '@internationalized/date';
import { Calendar } from '@/components/ui/calendar';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { Button } from '@/components/ui/button';
const props = defineProps<{ label: string; disabled?: boolean }>();
const model = defineModel<string | null>({ default: null });
const date = computed({
    get: () => (model.value ? parseDate(model.value) : undefined),
    set: (value: DateValue | undefined) => {
        model.value = value?.toString() ?? null;
    },
});
</script>
<template>
    <div class="space-y-1.5">
        <p class="text-xs font-medium">{{ props.label }}</p>
        <div class="flex gap-1">
            <Popover
                ><PopoverTrigger as-child
                    ><Button
                        variant="outline"
                        class="h-9 flex-1 justify-start font-normal"
                        :disabled="disabled"
                        :aria-label="props.label"
                        >{{ model ?? 'Choose date' }}</Button
                    ></PopoverTrigger
                ><PopoverContent class="w-auto p-0"
                    ><Calendar
                        v-model="date"
                        locale="en-PH" /></PopoverContent></Popover
            ><Button
                type="button"
                variant="ghost"
                :disabled="disabled || !model"
                aria-label="Clear due date"
                @click="model = null"
                >×</Button
            >
        </div>
    </div>
</template>
