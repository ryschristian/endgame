<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { CheckCircle2, CircleAlert, X } from '@lucide/vue';
import {
    ToastClose,
    ToastProvider,
    ToastRoot,
    ToastTitle,
    ToastViewport,
} from 'reka-ui';
import { onBeforeUnmount, onMounted, ref } from 'vue';

const props = defineProps<{ initialStatus?: string | null }>();
const messages = ref<{ id: number; message: string; error: boolean }[]>([]);
let nextId = 0;
const unsubscribe: (() => void)[] = [];
function add(message: string, error = false) {
    messages.value = [
        ...messages.value.slice(-4),
        { id: ++nextId, message, error },
    ];
}
function dismiss(id: number) {
    messages.value = messages.value.filter((message) => message.id !== id);
}
onMounted(() => {
    if (props.initialStatus) add(props.initialStatus);
    unsubscribe.push(
        router.on('success', (event) => {
            const flash = event.detail.page.props.flash as
                { status?: string } | undefined;
            if (flash?.status) add(flash.status);
        }),
        router.on('error', () =>
            add(
                'Changes could not be saved. Check the highlighted fields.',
                true,
            ),
        ),
    );
});
onBeforeUnmount(() => unsubscribe.forEach((remove) => remove()));
</script>

<template>
    <ToastProvider :duration="5000" swipe-direction="right">
        <ToastRoot
            v-for="message in messages"
            :key="message.id"
            data-activity-toast
            class="flex items-start gap-3 rounded-lg border bg-card p-4 text-sm shadow-lg data-[state=closed]:animate-out data-[state=open]:animate-in"
            @update:open="
                (open) => {
                    if (!open) dismiss(message.id);
                }
            "
        >
            <CircleAlert
                v-if="message.error"
                class="mt-0.5 size-4 shrink-0 text-destructive"
            />
            <CheckCircle2
                v-else
                class="mt-0.5 size-4 shrink-0 text-emerald-600"
            />
            <ToastTitle class="flex-1">{{ message.message }}</ToastTitle>
            <ToastClose
                aria-label="Dismiss notification"
                class="rounded p-0.5 text-muted-foreground hover:bg-muted"
                ><X class="size-4"
            /></ToastClose>
        </ToastRoot>
        <ToastViewport
            aria-label="Notifications"
            class="fixed right-4 bottom-4 z-[100] flex w-[min(24rem,calc(100vw-2rem))] flex-col gap-2 outline-none"
        />
    </ToastProvider>
</template>
