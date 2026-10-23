<script setup lang="ts">
import { Button } from '@/components/ui/button';
import FormField from '@/features/accounts/form-field.vue';
import AuthLayout from '@/layouts/auth-layout.vue';
import type { SharedPageProps } from '@/types/accounts';
import { Link, useForm, usePage } from '@inertiajs/vue3';

const props = defineProps<{ valid: boolean; email?: string; token?: string }>();
const page = usePage<SharedPageProps>();
const form = useForm<{
    name: string;
    password: string;
    password_confirmation: string;
    token?: string;
}>({ name: '', password: '', password_confirmation: '' });

function submit() {
    if (!props.valid || !props.token) return;
    form.post(`/invitations/${props.token}`, {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
}
</script>

<template>
    <AuthLayout
        :title="valid ? 'Accept your invitation' : 'Invitation unavailable'"
        :description="
            valid
                ? 'Create your account to get started with Endgame.'
                : 'This invitation is invalid, expired, or already used. Ask your administrator for a new invitation.'
        "
    >
        <form v-if="valid" class="space-y-4" @submit.prevent="submit">
            <div class="rounded-md bg-muted px-3 py-3 text-[12.5px]">
                <span class="block text-muted-foreground">Invited email</span
                ><span class="font-medium break-all">{{ email }}</span>
            </div>
            <FormField
                id="name"
                v-model="form.name"
                label="Name"
                autocomplete="name"
                :error="form.errors.name"
                required
            />
            <FormField
                id="password"
                v-model="form.password"
                label="Password"
                type="password"
                autocomplete="new-password"
                :hint="page.props.passwordHint"
                :error="form.errors.password"
                required
            />
            <FormField
                id="password_confirmation"
                v-model="form.password_confirmation"
                label="Confirm password"
                type="password"
                autocomplete="new-password"
                :error="form.errors.password_confirmation"
                required
            />
            <p
                v-if="form.errors.token"
                role="alert"
                class="text-[12.5px] text-destructive"
            >
                {{ form.errors.token }}
            </p>
            <Button
                type="submit"
                class="min-h-9 w-full sm:min-h-7.5"
                :disabled="form.processing"
                >{{
                    form.processing ? 'Creating account…' : 'Create account'
                }}</Button
            >
        </form>
        <Link
            v-else
            href="/login"
            class="text-[12.5px] underline underline-offset-4"
            >Back to sign in</Link
        >
    </AuthLayout>
</template>
