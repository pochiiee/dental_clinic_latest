<script setup>
import { ref } from "vue";
import { usePage } from "@inertiajs/vue3";
import AdminLayout from "@/Layouts/AdminLayout.vue";
import { MagnifyingGlassIcon } from "@heroicons/vue/24/outline";

const { props } = usePage();
const patients = ref(props.patients ?? []);
const totalPatients = ref(patients.value.length);

// Search & Sort model refs
const searchQuery = ref("");
const sortBy = ref("");

const viewDetails = (id) => {
    console.log("View details for patient", id);
};
</script>

<template>
    <AdminLayout>
        <div class="min-h-screen">
            <!-- Header Section -->
            <div class="max-w-7xl mx-auto px-6 lg:px-8 py-6">
                <h2 class="font-bold text-4xl text-teal-700 tracking-wide">
                    Patients
                </h2>
            </div>

            <!-- Main Content -->
            <div class="max-w-7xl mx-auto px-6 lg:px-10 py-8">
                <!-- Search + Total -->
                <div class="mb-8 flex flex-col lg:flex-row gap-6 items-start">
                    <!-- Search -->
                    <div class="flex-1 flex flex-col gap-4 w-full lg:w-auto">
                        <div class="relative">
                            <input
                                v-model="searchQuery"
                                type="text"
                                placeholder="Search"
                                class="w-3/4 pl-10 pr-4 py-2 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-teal-600 focus:border-teal-600 bg-white"
                            />
                            <MagnifyingGlassIcon
                                class="absolute left-3 top-3.5 h-5 w-5 text-gray-400"
                            />
                        </div>

                        <select
                            v-model="sortBy"
                            class="w-full lg:w-48 px-4 py-3 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-teal-500 focus:border-teal-500 bg-white"
                        >
                            <option value="">Sort</option>
                            <option value="name">Name</option>
                            <option value="date">Date</option>
                            <option value="id">ID</option>
                        </select>
                    </div>

                    <!-- Total Patients Card -->
                    <div
                        class="bg-white rounded-lg shadow-lg p-6 flex items-center gap-4 min-w-[280px]"
                    >
                        <div
                            class="bg-teal-50 rounded-lg p-4 flex items-center justify-center min-w-[80px]"
                        >
                            <span class="text-4xl font-bold text-teal-700">{{
                                totalPatients
                            }}</span>
                        </div>
                        <div class="text-lg font-semibold text-teal-700">
                            Total Patients
                        </div>
                    </div>
                </div>

                <!-- Table -->
                <div
                    class="bg-white rounded-2xl shadow-md border border-gray-200 w-full mx-auto p-8 flex flex-col"
                >
                    <div class="overflow-y-auto rounded-xl h-full">
                        <table
                            class="min-w-full border-separate border-spacing-0"
                        >
                            <thead>
                                <tr class="bg-neutral text-white">
                                    <th
                                        class="px-6 py-4 text-sm font-bold uppercase tracking-wide border-r border-white rounded-tl-xl text-center"
                                    >
                                        ID
                                    </th>
                                    <th
                                        class="px-6 py-4 text-sm font-bold uppercase tracking-wide border-r border-white text-center"
                                    >
                                        Last Name
                                    </th>
                                    <th
                                        class="px-6 py-4 text-sm font-bold uppercase tracking-wide border-r border-white text-center"
                                    >
                                        First Name
                                    </th>
                                    <th
                                        class="px-6 py-4 text-sm font-bold uppercase tracking-wide border-r border-white text-center"
                                    >
                                        Email
                                    </th>
                                    <th
                                        class="px-6 py-4 text-sm font-bold uppercase tracking-wide border-r border-white text-center"
                                    >
                                        Contact Number
                                    </th>
                                    <th
                                        class="px-6 py-4 text-sm font-bold uppercase tracking-wide rounded-tr-xl text-center"
                                    >
                                        Action
                                    </th>
                                </tr>
                            </thead>

                            <tbody>
                                <tr
                                    v-for="(patient, index) in patients"
                                    :key="patient.id"
                                    class="hover:bg-gray-50 transition"
                                >
                                    <td
                                        class="border border-gray-300 px-6 py-3 text-center"
                                    >
                                        {{ index + 1 }}
                                    </td>
                                    <td
                                        class="border border-gray-300 px-6 py-3 text-center"
                                    >
                                        {{ patient.lastName }}
                                    </td>
                                    <td
                                        class="border border-gray-300 px-6 py-3 text-center"
                                    >
                                        {{ patient.firstName }}
                                    </td>
                                    <td
                                        class="border border-gray-300 px-6 py-3 text-center"
                                    >
                                        {{ patient.email }}
                                    </td>
                                    <td
                                        class="border border-gray-300 px-6 py-3 text-center"
                                    >
                                        {{ patient.contactNumber }}
                                    </td>
                                    <td
                                        class="border border-gray-300 px-6 py-3 text-center"
                                    >
                                        <button
                                            @click="viewDetails(patient.id)"
                                            class="font-medium hover:underline"
                                        >
                                            View Details
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
