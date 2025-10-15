<script setup>
import { ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import CustomerLayout from '@/Layouts/CustomerLayout.vue';

const currentRoute = ref('profile.personal-information');
</script>

<template>
    <Head title="Profile" />
    <CustomerLayout>
        <div class="min-h-screen py-10 px-4 sm:px-6 lg:px-8">
            <div class="max-w-7xl mx-auto">
                <h1 class="text-2xl sm:text-3xl font-extrabold text-dark mb-8 sm:mb-10 uppercase">
                    Profile
                </h1>

                <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 lg:gap-8">
                    <!-- Sidebar -->
                    <div class="lg:col-span-4 xl:col-span-3">
                        <div class="bg-[#B3D9D2] rounded-2xl shadow-lg p-6 space-y-3">
                            <Link
                                :href="route('profile.personal-information')"
                                :class="[
                                    'block w-full text-left px-6 py-3 rounded-lg font-semibold transition-all duration-200',
                                    route().current('profile.personal-information')
                                        ? 'bg-white text-dark shadow-md'
                                        : 'text-dark hover:bg-white/50'
                                ]"
                            >
                                Personal Information
                            </Link>

                            <Link
                                :href="route('profile.change-password')"
                                :class="[
                                    'block w-full text-left px-6 py-3 rounded-lg font-semibold transition-all duration-200',
                                    route().current('profile.change-password')
                                        ? 'bg-white text-dark shadow-md'
                                        : 'text-dark hover:bg-white/50'
                                ]"
                            >
                                Change Password
                            </Link>
                        </div>
                    </div>

                    <!-- Dynamic Content -->
                    <div class="lg:col-span-8 xl:col-span-9">
                        <div class="bg-white rounded-2xl shadow-lg p-6 sm:p-8 md:p-10">
                            <slot />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </CustomerLayout>
</template>
