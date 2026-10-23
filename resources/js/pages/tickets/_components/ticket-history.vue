<script setup lang="ts">
import PaginationControls from '@/features/accounts/pagination-controls.vue';
import type { Paginated } from '@/types/accounts';
import type { HistoryEvent } from './types';
import { teamDate } from './types';
defineProps<{
    history: Paginated<HistoryEvent>;
    labels: Record<string, string>;
}>();
const display = (value: unknown) =>
    value === null || value === '' ? 'None' : String(value);
const changes = (event: HistoryEvent) =>
    Object.fromEntries(
        Object.entries(event.changes.after).filter(
            ([key]) =>
                !key.endsWith('_id') ||
                !(key.slice(0, -3) in event.changes.after),
        ),
    );
</script>
<template>
    <section class="mt-8 space-y-4 border-t pt-5" aria-label="Ticket activity">
        <h2 class="text-sm font-medium">
            Activity
            <span class="ml-1 text-xs text-muted-foreground">{{
                history.total
            }}</span>
        </h2>
        <article
            v-for="event in history.data"
            :key="event.id"
            class="space-y-2 border-b pb-4 text-xs last:border-0"
        >
            <p>
                <span class="font-medium">{{ event.actor }}</span>
                {{
                    event.action === 'created'
                        ? 'created this ticket'
                        : 'updated this ticket'
                }}<time class="mt-1 block text-[10px] text-muted-foreground">{{
                    teamDate(event.created_at, true)
                }}</time>
            </p>
            <dl v-if="event.action !== 'created'" class="space-y-1.5">
                <div
                    v-for="(value, key) in changes(event)"
                    :key="key"
                    class="grid grid-cols-[100px_minmax(0,1fr)] gap-2"
                >
                    <dt class="text-muted-foreground">
                        {{
                            labels[key] ??
                            labels[`${key}_id`] ??
                            String(key).replaceAll('_', ' ')
                        }}
                    </dt>
                    <dd class="break-words whitespace-pre-wrap">
                        <span class="text-muted-foreground line-through">{{
                            display(event.changes.before?.[key])
                        }}</span>
                        → {{ display(value) }}
                    </dd>
                </div>
            </dl>
        </article>
        <PaginationControls
            :current-page="history.current_page"
            :last-page="history.last_page"
            :previous="history.prev_page_url"
            :next="history.next_page_url"
            label="Ticket activity pagination"
        />
    </section>
</template>
