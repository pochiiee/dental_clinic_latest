<script setup>
import AuthBackgroundLayout from "@/Layouts/AuthBackroundLayout.vue"
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps({
    email: {
        type: String,
        required: true,
    },
    token: {
        type: String,
        required: true,
    },
});

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post(route('password.store'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <AuthBackgroundLayout title="Welcome to District Smiles Dental Center">
        <Head title="Reset Password" />

        <div
            class="backdrop-blur-md bg-light border-2 border-white rounded-2xl shadow-2xl 
                   p-8 sm:p-10 w-full max-w-md mx-auto text-center"
        >
            <!-- Header -->
            <h2 class="text-xl sm:text-2xl font-semibold mb-4 text-gray-900 drop-shadow-sm">
                Reset Password
            </h2>

            <!-- Form -->
            <form @submit.prevent="submit" class="space-y-5">
                <div>
                    <InputLabel for="email" />

                    <TextInput
                        id="email"
                        type="email"
                        placeholder="Email"
                        v-model="form.email"
                        required
                        autofocus
                        autocomplete="username"
                        class="w-full px-5 py-3.5 rounded-xl border-0 bg-white/90 
                               text-gray-800 placeholder-gray-500 focus:ring-2 
                               focus:ring-teal-500 focus:outline-none transition-all shadow-sm"
                    />

                    <InputError class="mt-2 text-red-500 text-sm" :message="form.errors.email" />
                </div>

                <div>
                    <InputLabel for="password" />

                    <TextInput
                        id="password"
                        type="password"
                        placeholder="Password"
                        v-model="form.password"
                        required
                        autocomplete="new-password"
                        class="w-full px-5 py-3.5 rounded-xl border-0 bg-white/90 
                               text-gray-800 placeholder-gray-500 focus:ring-2 
                               focus:ring-teal-500 focus:outline-none transition-all shadow-sm"
                    />

                    <InputError class="mt-2 text-red-500 text-sm" :message="form.errors.password" />
                </div>

                <div>
                    <InputLabel for="password_confirmation" />

                    <TextInput
                        id="password_confirmation"
                        type="password"
                        placeholder="Confirm Password"
                        v-model="form.password_confirmation"
                        required
                        autocomplete="new-password"
                        class="w-full px-5 py-3.5 rounded-xl border-0 bg-white/90 
                               text-gray-800 placeholder-gray-500 focus:ring-2 
                               focus:ring-teal-500 focus:outline-none transition-all shadow-sm"
                    />

                    <InputError class="mt-2 text-red-500 text-sm" :message="form.errors.password_confirmation" />
                </div>

                <PrimaryButton
                    type="submit"
                    :disabled="form.processing"
                    :class="{ 'opacity-25': form.processing }"
                    class="w-1/2 bg-white font-semibold rounded-full shadow-md hover:shadow-lg uppercase"
                >
                    Reset Password
                </PrimaryButton>
            </form>
        </div>
    </AuthBackgroundLayout>
</template>