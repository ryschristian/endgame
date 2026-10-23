<script setup lang="ts">
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
} from '@/components/ui/dialog';
import type { Catalog, TicketProps } from './types';
const props = defineProps<{ open: boolean; data: TicketProps }>();
defineEmits<{ close: [] }>();
const catalog = ref<'work-types' | 'priorities'>('work-types');
const form = useForm({
    id: null as number | null,
    name: '',
    position: 0,
    is_active: true,
    is_default: false,
    revision: props.data.catalogRevision,
});
function edit(record?: Catalog) {
    form.revision = props.data.catalogRevision;
    form.clearErrors();
    form.id = record?.id ?? null;
    form.name = record?.name ?? '';
    form.position = record?.position ?? 0;
    form.is_active = Boolean(record?.is_active ?? true);
    form.is_default = Boolean(record?.is_default ?? false);
}
function save() {
    form.post(
        `/projects/${props.data.project.id}/ticket-catalogs/${catalog.value}`,
        { preserveScroll: true, onSuccess: () => edit() },
    );
}
</script>
<template>
    <Dialog
        :open="open"
        @update:open="
            (value) => {
                if (!value) $emit('close');
            }
        "
        ><DialogContent class="max-h-[90dvh] overflow-y-auto sm:max-w-xl"
            ><DialogHeader
                ><DialogTitle>Ticket settings</DialogTitle
                ><DialogDescription class="sr-only"
                    >Manage project work types and priorities</DialogDescription
                ></DialogHeader
            >
            <div class="flex gap-2">
                <Button
                    v-for="item in ['work-types', 'priorities'] as const"
                    :key="item"
                    :variant="catalog === item ? 'default' : 'outline'"
                    @click="
                        catalog = item;
                        edit();
                    "
                    >{{
                        item === 'work-types' ? 'Work types' : 'Priorities'
                    }}</Button
                >
            </div>
            <ul class="divide-y rounded-md border">
                <li
                    v-for="record in catalog === 'work-types'
                        ? data.catalogs.workTypes
                        : data.catalogs.priorities"
                    :key="record.id"
                    class="flex items-center justify-between px-3 py-2 text-xs"
                >
                    <span
                        >{{ record.name
                        }}<span class="ml-2 text-muted-foreground">{{
                            record.is_default
                                ? 'Default'
                                : !record.is_active
                                  ? 'Inactive'
                                  : ''
                        }}</span></span
                    ><Button
                        variant="ghost"
                        :disabled="Boolean(data.project.archived_at)"
                        @click="edit(record)"
                        >Edit</Button
                    >
                </li>
            </ul>
            <form class="space-y-4 border-t pt-4" @submit.prevent="save">
                <h3 class="text-sm font-medium">
                    {{ form.id ? 'Edit entry' : 'Add entry' }}
                </h3>
                <fieldset
                    :disabled="
                        form.processing || Boolean(data.project.archived_at)
                    "
                    class="space-y-3"
                >
                    <div>
                        <label for="catalog-name" class="text-xs">Name</label
                        ><input
                            id="catalog-name"
                            v-model="form.name"
                            class="mt-1 h-9 w-full rounded-md border border-input px-3 text-sm"
                            maxlength="80"
                            required
                        />
                    </div>
                    <div>
                        <label for="catalog-position" class="text-xs"
                            >Order</label
                        ><input
                            id="catalog-position"
                            v-model.number="form.position"
                            type="number"
                            min="0"
                            max="100000"
                            class="mt-1 h-9 w-full rounded-md border border-input px-3 text-sm"
                            required
                        />
                    </div>
                    <div class="flex gap-5 text-xs">
                        <label class="flex items-center gap-2"
                            ><input
                                v-model="form.is_active"
                                type="checkbox"
                            />Active</label
                        ><label class="flex items-center gap-2"
                            ><input
                                v-model="form.is_default"
                                type="checkbox"
                            />Default</label
                        >
                    </div>
                </fieldset>
                <p
                    v-for="(error, key) in form.errors"
                    :key="key"
                    role="alert"
                    class="text-xs text-destructive"
                >
                    {{ error }}
                </p>
                <div class="flex justify-end gap-2">
                    <Button
                        v-if="form.id"
                        type="button"
                        variant="ghost"
                        @click="edit()"
                        >New entry</Button
                    ><Button
                        type="submit"
                        :disabled="
                            form.processing || Boolean(data.project.archived_at)
                        "
                        >Save entry</Button
                    >
                </div>
            </form></DialogContent
        ></Dialog
    >
</template>
