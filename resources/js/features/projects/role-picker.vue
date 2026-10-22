<script setup lang="ts">
import type { Option } from '@/types/projects';
defineProps<{ options: Option<number>[]; disabled?: boolean }>();
const model = defineModel<number[]>({ default: () => [] });
</script>
<template>
    <fieldset :disabled="disabled" class="space-y-2">
        <legend class="mb-2 text-sm font-medium">Project roles</legend>
        <p v-if="!options.length" class="text-sm text-muted-foreground">
            No roles are available to assign.
        </p>
        <label
            v-for="option in options"
            :key="option.value"
            class="flex items-center gap-3 text-sm"
        >
            <input
                v-model="model"
                type="checkbox"
                :value="option.value"
                class="size-4 accent-primary"
            />{{ option.label }}
        </label>
    </fieldset>
</template>
