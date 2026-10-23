<script setup lang="ts">
import { Button } from '@/components/ui/button';
import FormField from '@/pages/_components/form-field.vue';
import AuthLayout from '@/layouts/auth-layout.vue';
import { Link, useForm } from '@inertiajs/vue3';

const form = useForm({ email: '' });
</script>

<template>
    <AuthLayout
        title="Reset your password"
        description="Enter your email and we’ll send a reset link if your account is eligible."
    >
        <form class="space-y-4" @submit.prevent="form.post('/forgot-password')">
            <FormField
                id="email"
                v-model="form.email"
                label="Email"
                type="email"
                autocomplete="username"
                :error="form.errors.email"
                required
            />
            <Button
                type="submit"
                class="min-h-9 w-full sm:min-h-7.5"
                :disabled="form.processing"
                >{{ form.processing ? 'Sending…' : 'Send reset link' }}</Button
            >
            <Link
                href="/login"
                class="block py-2 text-center text-[12.5px] underline underline-offset-4"
                >Back to sign in</Link
            >
        </form>
    </AuthLayout>
</template>
