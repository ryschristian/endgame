<script setup lang="ts">
import type { InputTypeHTMLAttribute } from 'vue';

withDefaults(
    defineProps<{
        id: string;
        label: string;
        type?: InputTypeHTMLAttribute;
        autocomplete?: string;
        error?: string;
        hint?: string;
        required?: boolean;
        readonly?: boolean;
    }>(),
    { type: 'text' },
);
const model = defineModel<string>({ default: '' });
</script>

<template>
    <div class="space-y-1.5">
        <label :for="id" class="block text-[12.5px] font-medium">{{
            label
        }}</label>
        <input
            :id="id"
            v-model="model"
            :type="type"
            :name="id"
            :autocomplete="autocomplete"
            :required="required"
            :readonly="readonly"
            :aria-invalid="error ? 'true' : undefined"
            :aria-describedby="
                error ? `${id}-error` : hint ? `${id}-hint` : undefined
            "
            class="flex h-9 w-full rounded-md border border-input bg-card px-2.5 text-base transition-[border-color,box-shadow] duration-150 outline-none read-only:bg-muted read-only:text-muted-foreground focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/10 aria-invalid:border-destructive aria-invalid:ring-3 aria-invalid:ring-destructive/10 sm:h-7.5 sm:text-[13.5px]"
        />
        <p
            v-if="error"
            :id="`${id}-error`"
            role="alert"
            class="text-xs text-destructive"
        >
            {{ error }}
        </p>
        <p
            v-else-if="hint"
            :id="`${id}-hint`"
            class="text-xs text-muted-foreground"
        >
            {{ hint }}
        </p>
    </div>
</template>
