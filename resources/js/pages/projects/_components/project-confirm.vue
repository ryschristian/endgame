<script setup lang="ts">
import { Button } from '@/components/ui/button';
import {
    AlertDialogRoot,
    AlertDialogPortal,
    AlertDialogOverlay,
    AlertDialogContent,
    AlertDialogTitle,
    AlertDialogDescription,
    AlertDialogCancel,
} from 'reka-ui';
defineProps<{
    open: boolean;
    title: string;
    description: string;
    busy: boolean;
}>();
const emit = defineEmits<{ close: []; confirm: [] }>();
</script>
<template>
    <AlertDialogRoot
        :open="open"
        @update:open="
            (value) => {
                if (!value && !busy) emit('close');
            }
        "
    >
        <AlertDialogPortal
            ><AlertDialogOverlay class="fixed inset-0 z-40 bg-black/45" />
            <AlertDialogContent
                class="fixed top-1/2 left-1/2 z-50 w-[calc(100%-2rem)] max-w-md -translate-x-1/2 -translate-y-1/2 space-y-5 rounded-xl border bg-background p-6 shadow-xl"
            >
                <AlertDialogTitle class="text-xl font-semibold">{{
                    title
                }}</AlertDialogTitle>
                <AlertDialogDescription
                    class="text-sm leading-6 text-muted-foreground"
                    >{{ description }}</AlertDialogDescription
                >
                <slot />
                <div class="flex justify-end gap-3">
                    <AlertDialogCancel as-child
                        ><Button variant="outline" :disabled="busy"
                            >Cancel</Button
                        ></AlertDialogCancel
                    >
                    <Button
                        variant="destructive"
                        :disabled="busy"
                        @click="emit('confirm')"
                        >{{ busy ? 'Updating…' : 'Confirm' }}</Button
                    >
                </div>
            </AlertDialogContent>
        </AlertDialogPortal>
    </AlertDialogRoot>
</template>
