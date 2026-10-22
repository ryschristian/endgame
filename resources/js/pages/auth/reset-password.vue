<script setup lang="ts">
import { Button } from '@/components/ui/button';
import FormField from '@/features/accounts/form-field.vue';
import AuthLayout from '@/layouts/auth-layout.vue';
import type { SharedPageProps } from '@/types/accounts';
import { useForm, usePage } from '@inertiajs/vue3';

const props = defineProps<{ email: string; token: string }>();
const page = usePage<SharedPageProps>();
const form = useForm({
    email: props.email,
    token: props.token,
    password: '',
    password_confirmation: '',
});
function submit() {
    form.post('/reset-password', {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
}
</script>

<template>
    <AuthLayout
        title="Choose a new password"
        description="Set a new password to regain access to your account."
    >
        <form class="space-y-4" @submit.prevent="submit">
            <FormField
                id="email"
                v-model="form.email"
                label="Email"
                type="email"
                autocomplete="username"
                :error="form.errors.email"
                required
            />
            <FormField
                id="password"
                v-model="form.password"
                label="New password"
                type="password"
                autocomplete="new-password"
                :hint="page.props.passwordHint"
                :error="form.errors.password"
                required
            />
            <FormField
                id="password_confirmation"
                v-model="form.password_confirmation"
                label="Confirm new password"
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
                >{{ form.processing ? 'Resetting…' : 'Reset password' }}</Button
            >
        </form>
    </AuthLayout>
</template>
