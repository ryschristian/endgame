<script setup lang="ts">
import { Button } from '@/components/ui/button';
import FormField from '@/pages/_components/form-field.vue';
import AuthLayout from '@/layouts/auth-layout.vue';
import { Link, useForm } from '@inertiajs/vue3';

const form = useForm({ email: '', password: '', remember: false });
function submit() {
    form.post('/login', { onFinish: () => form.reset('password') });
}
</script>

<template>
    <AuthLayout
        title="Sign in"
        description="Welcome back. Sign in to your Endgame account."
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
                label="Password"
                type="password"
                autocomplete="current-password"
                :error="form.errors.password"
                required
            />
            <div
                class="flex flex-wrap items-center justify-between gap-3 text-[12.5px]"
            >
                <label class="flex min-h-9 items-center gap-2 sm:min-h-7.5"
                    ><input
                        v-model="form.remember"
                        type="checkbox"
                        class="size-4 accent-primary"
                    />
                    Remember me</label
                >
                <Link
                    href="/forgot-password"
                    class="py-3 underline underline-offset-4"
                    >Forgot password?</Link
                >
            </div>
            <Button
                type="submit"
                class="min-h-9 w-full sm:min-h-7.5"
                :disabled="form.processing"
                >{{ form.processing ? 'Signing in…' : 'Sign in' }}</Button
            >
            <p class="text-center text-[12.5px] text-muted-foreground">
                New here? Ask your administrator for an invitation.
            </p>
        </form>
    </AuthLayout>
</template>
