<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/login';
import { request } from '@/routes/password';
import PasskeyVerify from '@/components/PasskeyVerify.vue';

defineOptions({
    layout: {
        title: 'Log in to your account',
        description: 'Enter your email and password below to log in',
    },
});

type DemoLogin = {
    name: string;
    email: string;
    password: string;
    role: string;
};

const props = defineProps<{
    status?: string;
    canResetPassword: boolean;
    demoLogins: DemoLogin[];
}>();

const roleTone: Record<string, string> = {
    owner: 'bg-cyan-100 text-cyan-800 dark:bg-cyan-500/20 dark:text-cyan-300',
    pharmacist:
        'bg-rose-100 text-rose-700 dark:bg-rose-500/20 dark:text-rose-300',
    assistant:
        'bg-amber-100 text-amber-800 dark:bg-amber-500/20 dark:text-amber-300',
    cashier:
        'bg-violet-100 text-violet-700 dark:bg-violet-500/20 dark:text-violet-300',
};

// The form is uncontrolled, so fill the fields directly.
function fill(email: unknown) {
    const login = props.demoLogins.find((l) => l.email === email);
    const emailInput = document.getElementById(
        'email',
    ) as HTMLInputElement | null;
    const passwordInput = document.getElementById(
        'password',
    ) as HTMLInputElement | null;

    if (login && emailInput && passwordInput) {
        emailInput.value = login.email;
        passwordInput.value = login.password;
    }
}
</script>

<template>
    <Head title="Log in" />

    <div
        v-if="status"
        class="mb-4 text-center text-sm font-medium text-green-600"
    >
        {{ status }}
    </div>

    <PasskeyVerify />

    <Form
        novalidate
        v-bind="store.form()"
        :reset-on-success="['password']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-6"
    >
        <div class="grid min-w-0 gap-6">
            <div
                v-if="demoLogins.length"
                class="grid min-w-0 gap-3 rounded-lg border bg-muted/50 p-4"
            >
                <Label id="quick-login">Quick login</Label>
                <RadioGroup
                    aria-labelledby="quick-login"
                    @update:model-value="fill"
                >
                    <Label
                        v-for="login in demoLogins"
                        :key="login.email"
                        class="flex min-w-0 cursor-pointer items-center gap-3 font-normal"
                    >
                        <RadioGroupItem :value="login.email" />
                        <span class="font-medium whitespace-nowrap">{{
                            login.name
                        }}</span>
                        <span class="min-w-0 truncate text-muted-foreground">{{
                            login.email
                        }}</span>
                        <span
                            :class="[
                                'ml-auto shrink-0 rounded-full px-2 py-0.5 text-xs font-medium capitalize',
                                roleTone[login.role],
                            ]"
                            >{{ login.role }}</span
                        >
                    </Label>
                </RadioGroup>
            </div>

            <div class="grid gap-2">
                <Label for="email">Email address</Label>
                <Input
                    id="email"
                    type="email"
                    name="email"
                    v-focus
                    :tabindex="1"
                    autocomplete="email"
                    placeholder="email@example.com"
                />
                <InputError :message="errors.email" />
            </div>

            <div class="grid gap-2">
                <div class="flex items-center justify-between">
                    <Label for="password">Password</Label>
                    <TextLink
                        v-if="canResetPassword"
                        :href="request()"
                        class="text-sm"
                        :tabindex="5"
                    >
                        Forgot your password?
                    </TextLink>
                </div>
                <PasswordInput
                    id="password"
                    name="password"
                    :tabindex="2"
                    autocomplete="current-password"
                    placeholder="Password"
                />
                <InputError :message="errors.password" />
            </div>

            <div class="flex items-center justify-between">
                <Label for="remember" class="flex items-center space-x-3">
                    <Checkbox id="remember" name="remember" :tabindex="3" />
                    <span>Remember me</span>
                </Label>
            </div>

            <Button
                type="submit"
                class="mt-4 w-full"
                :tabindex="4"
                :disabled="processing"
                data-test="login-button"
            >
                <Spinner v-if="processing" />
                Log in
            </Button>
        </div>
    </Form>
</template>
