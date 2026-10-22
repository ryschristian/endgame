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
    <div class="space-y-2">
        <label :for="id" class="block text-sm font-medium">{{ label }}</label>
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
            class="flex h-11 w-full rounded-md border border-input bg-background px-3 text-base shadow-xs transition-colors outline-none read-only:bg-muted read-only:text-muted-foreground focus-visible:border-ring focus-visible:ring-2 focus-visible:ring-ring/50 aria-invalid:border-destructive"
        />
        <p
            v-if="error"
            :id="`${id}-error`"
            role="alert"
            class="text-sm text-destructive"
        >
            {{ error }}
        </p>
        <p
            v-else-if="hint"
            :id="`${id}-hint`"
            class="text-sm text-muted-foreground"
        >
            {{ hint }}
        </p>
    </div>
</template>
