<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import UpdatePasswordForm from '@/Pages/Admin/Profile/UpdatePasswordForm.vue';
import UpdateProfileInformationForm from '@/Pages/Admin/Profile/UpdateProfileInformation.vue';
import { Head } from '@inertiajs/vue3';
import { ref } from 'vue';

defineProps({
    mustVerifyEmail: Boolean,
    status: String,
});

const activeTab = ref('personal');
</script>

<template>
    <Head title="Profile" />

    <AdminLayout>
        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="bg-gray-50 shadow-xl rounded-3xl p-12 flex flex-col">
                    <!-- Header -->
                    <h2 class="text-4xl font-extrabold text-cyan-800 tracking-wide uppercase text-left mb-10">
                        PROFILE
                    </h2>

                    <div class="flex justify-center gap-16">
                        <!-- Left Panel - Fixed Navigation -->
                        <div class="w-64 flex-shrink-0 sticky top-24 self-start">
                            <div class="bg-light rounded-xl shadow-md overflow-hidden w-full">
                                <!-- Personal Information -->
                                <button
                                    @click="activeTab = 'personal'"
                                    :class="[
                                        'relative w-full text-center px-6 py-5 font-semibold text-sm transition-all duration-300',
                                        activeTab === 'personal'
                                            ? 'text-gray-900 shadow-inner'
                                            : 'text-gray-700 hover:bg-[#cfe8e8]'
                                    ]"
                                >
                                    <span
                                        v-if="activeTab === 'personal'"
                                        class="absolute left-0 top-2 h-3/4 w-1 bg-cyan-800 rounded-r-md"
                                    ></span>
                                    Personal Information
                                </button>

                                <!-- Change Password -->
                                <button
                                    @click="activeTab = 'password'"
                                    :class="[
                                        'relative w-full text-center px-6 py-5 font-semibold text-sm transition-all duration-300',
                                        activeTab === 'password'
                                            ? 'text-gray-900 shadow-inner'
                                            : 'text-gray-700 hover:bg-[#cfe8e8]'
                                    ]"
                                >
                                    <span
                                        v-if="activeTab === 'password'"
                                        class="absolute left-0 top-2 h-3/4 w-1 bg-cyan-800 rounded-r-md"
                                    ></span>
                                    Change Password
                                </button>
                            </div>
                        </div>

                        <!-- Right Panel - Content -->
                        <div class="flex-1 flex justify-center">
                            <!-- Personal Information Tab -->
                            <transition name="fade" mode="out-in">
                                <div
                                    v-if="activeTab === 'personal'"
                                    key="personal"
                                    class="w-full max-w-2xl transition-opacity"
                                >
                                    <UpdateProfileInformationForm
                                        :must-verify-email="mustVerifyEmail"
                                        :status="status"
                                        class="w-full"
                                    />
                                </div>
                            </transition>

                            <!-- Change Password Tab -->
                            <transition name="fade" mode="out-in">
                                <div
                                    v-if="activeTab === 'password'"
                                    key="password"
                                    class="w-full max-w-xl transition-opacity"
                                >
                                    <UpdatePasswordForm class="w-full" />
                                </div>
                            </transition>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

<style scoped>
/* Fade transition for smooth content switching */
.fade-enter-active, .fade-leave-active {
  transition: opacity 0.3s ease;
}
.fade-enter-from, .fade-leave-to {
  opacity: 0;
}
</style>
