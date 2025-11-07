<script setup>
import { ref, computed } from 'vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { CalendarIcon, UserPlusIcon, ClockIcon } from '@heroicons/vue/24/outline'

// Stats data
<<<<<<< Updated upstream
const totalAppointments = ref(10)
const patientsRegistered = ref(34)
const pendingAppointments = ref(25)
=======
const totalAppointments = ref(10);
const patientsRegistered = ref(34);
const scheduledAppointments = ref(25);
>>>>>>> Stashed changes

// Weekly dropdown
const selectedPeriod = ref('Weekly')
const periods = ['Daily', 'Weekly', 'Monthly', 'Yearly']

// Appointments chart data
const appointmentsData = ref([
  { day: 'Mon', value: 0 },
  { day: 'Tue', value: 11 },
  { day: 'Wed', value: 10 },
  { day: 'Thurs', value: 10 },
  { day: 'Fri', value: 25 },
  { day: 'Sat', value: 29 },
  { day: 'Sun', value: 0 }
])

const totalValue = computed(() => {
  return appointmentsData.value.reduce((sum, item) => sum + item.value, 0)
})

const maxValue = computed(() => {
  return Math.max(...appointmentsData.value.map(item => item.value))
})

// Appointment Status data
const appointmentStatus = ref([
  { label: 'Completed', percentage: 37, color: 'bg-green-500' },
  { label: 'Scheduled', percentage: 35, color: 'bg-teal-500' },
  { label: 'Rescheduled', percentage: 18, color: 'bg-blue-500' },
  { label: 'Cancelled', percentage: 6, color: 'bg-red-500' },
  { label: 'No show', percentage: 4, color: 'bg-gray-300' }
])

// Calculate donut chart segments
const calculateDonutSegments = () => {
  let currentAngle = -90 // Start from top
  return appointmentStatus.value.map(status => {
    const angle = (status.percentage / 100) * 360
    const segment = {
      ...status,
      startAngle: currentAngle,
      endAngle: currentAngle + angle
    }
    currentAngle += angle
    return segment
  })
}

const donutSegments = computed(() => calculateDonutSegments())

// Upcoming appointments
const upcomingAppointments = ref([
  {
    id: 1,
    name: 'Alex Ramos',
    procedure: 'Tooth Extraction / Root Canal Treatment / (Wisdom Tooth)',
    dateTime: '10-15-2025\n1:00 p.m - 3:00 p.m'
  },
  {
    id: 2,
    name: 'Gregorio Garcia',
    procedure: 'Mouth Examination / Oral Prophylaxis (Cleaning)',
    dateTime: '10-16-2025\n10:00 a.m - 1:00 p.m'
  },
  {
    id: 3,
    name: 'Chloe Rivera',
    procedure: 'Tooth Restoration (Pasta)',
    dateTime: '10-16-2025\n1:00 p.m - 3:00 p.m'
  }
])

// Helper function to calculate bar height percentage
const getBarHeight = (value) => {
  if (maxValue.value === 0) return 0
  return (value / maxValue.value) * 100
}
</script>

<template>
<<<<<<< Updated upstream
  <AdminLayout>
    <div class="min-h-screen bg-gray-100 p-8">
      <div class="max-w-7xl mx-auto">
        <!-- Header -->
        <div class="mb-8 flex justify-between items-center">
          <h1 class="text-4xl font-bold text-teal-700">HOME</h1>
          <div class="text-right">
            <p class="text-sm text-gray-600">Sunday</p>
            <p class="text-lg font-semibold text-gray-800">October 12, 2025</p>
          </div>
=======
    <AdminLayout>
        <div class="min-h-screen bg-gray-100 p-8">
            <div class="max-w-7xl mx-auto">
                <!-- Header -->
                <div class="mb-8 flex justify-between items-center">
                    <h1 class="text-4xl font-bold text-teal-700">Dashboard</h1>
                </div>

                <!-- Top Section -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
                    <!-- Left Stats Cards -->
                    <div class="space-y-4">
                        <!-- Total Appointments Card -->
                        <div class="bg-white rounded-2xl shadow-md p-6">
                            <div class="flex items-center gap-4">
                                <div
                                    class="w-16 h-16 bg-teal-100 rounded-xl flex items-center justify-center"
                                >
                                    <CalendarIcon
                                        class="w-8 h-8 text-teal-700"
                                    />
                                </div>
                                <div>
                                    <p class="text-4xl font-bold text-gray-900">
                                        {{ totalAppointments }}
                                    </p>
                                    <p class="text-sm text-gray-600 mt-1">
                                        Total Appointments (Today)
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Patients Registered Card -->
                        <div class="bg-white rounded-2xl shadow-md p-6">
                            <div class="flex items-center gap-4">
                                <div
                                    class="w-16 h-16 bg-teal-100 rounded-xl flex items-center justify-center"
                                >
                                    <UserPlusIcon
                                        class="w-8 h-8 text-teal-700"
                                    />
                                </div>
                                <div>
                                    <p class="text-4xl font-bold text-gray-900">
                                        {{ patientsRegistered }}
                                    </p>
                                    <p class="text-sm text-gray-600 mt-1">
                                        Patients Registered
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Appointments Card -->
                        <div class="bg-white rounded-2xl shadow-md p-6">
                            <div class="flex items-center gap-4">
                                <div
                                    class="w-16 h-16 bg-teal-100 rounded-xl flex items-center justify-center"
                                >
                                    <ClockIcon class="w-8 h-8 text-teal-700" />
                                </div>
                                <div>
                                    <p class="text-4xl font-bold text-gray-900">
                                        {{ scheduledAppointments }}
                                    </p>
                                    <p class="text-sm text-gray-600 mt-1">
                                        Scheduled Appointments
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Appointments Chart -->
                    <div
                        class="lg:col-span-2 bg-white rounded-2xl shadow-md p-6"
                    >
                        <div class="flex justify-between items-center mb-6">
                            <h2 class="text-xl font-bold text-gray-900">
                                Appointments
                            </h2>
                            <select
                                v-model="selectedPeriod"
                                class="px-4 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-teal-500"
                            >
                                <option
                                    v-for="period in periods"
                                    :key="period"
                                    :value="period"
                                >
                                    {{ period }}
                                </option>
                            </select>
                        </div>

                        <!-- Chart -->
                        <div class="relative h-80">
                            <!-- Y-axis labels -->
                            <div
                                class="absolute left-0 top-0 bottom-8 flex flex-col justify-between text-xs text-gray-600 w-8"
                            >
                                <span>60</span>
                                <span>50</span>
                                <span>40</span>
                                <span>30</span>
                                <span>20</span>
                                <span>10</span>
                                <span>0</span>
                            </div>

                            <!-- Chart area -->
                            <div
                                class="ml-12 h-full flex items-end justify-around gap-2 border-b border-l border-gray-300 pb-8"
                            >
                                <div
                                    v-for="item in appointmentsData"
                                    :key="item.day"
                                    class="flex-1 flex flex-col items-center"
                                >
                                    <!-- Bar -->
                                    <div
                                        class="relative w-full flex items-end justify-center"
                                        style="height: 280px"
                                    >
                                        <div
                                            v-if="item.value > 0"
                                            class="w-full bg-teal-600 rounded-t-lg relative flex items-start justify-center pt-2"
                                            :style="{
                                                height: `${getBarHeight(
                                                    item.value
                                                )}%`,
                                            }"
                                        >
                                            <span
                                                class="text-xs font-semibold text-white"
                                                >{{ item.value }}</span
                                            >
                                        </div>
                                    </div>
                                    <!-- Day label -->
                                    <span class="text-xs text-gray-600 mt-2">{{
                                        item.day
                                    }}</span>
                                </div>
                            </div>

                            <!-- Total Value -->
                            <div
                                class="absolute left-32 top-1/2 -translate-y-1/2 text-center"
                            >
                                <p class="text-sm text-gray-600">Total Value</p>
                                <p class="text-3xl font-bold text-gray-900">
                                    {{ totalValue }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Bottom Section -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Appointment Status -->
                    <div class="bg-white rounded-2xl shadow-md p-6">
                        <h2 class="text-xl font-bold text-gray-900 mb-6">
                            Appointment Status
                        </h2>

                        <div class="flex items-center gap-8">
                            <!-- Legend -->
                            <div class="flex-1 space-y-3">
                                <div
                                    v-for="status in appointmentStatus"
                                    :key="status.label"
                                    class="flex items-center justify-between"
                                >
                                    <div class="flex items-center gap-2">
                                        <div
                                            :class="[
                                                'w-3 h-3 rounded-full',
                                                status.color,
                                            ]"
                                        ></div>
                                        <span class="text-sm text-gray-700">{{
                                            status.label
                                        }}</span>
                                    </div>
                                    <span
                                        class="text-sm font-semibold text-gray-900"
                                        >{{ status.percentage }}%</span
                                    >
                                </div>
                            </div>

                            <!-- Donut Chart -->
                            <div class="relative w-48 h-48">
                                <svg
                                    viewBox="0 0 100 100"
                                    class="transform -rotate-90"
                                >
                                    <circle
                                        cx="50"
                                        cy="50"
                                        r="35"
                                        fill="none"
                                        stroke="#e5e7eb"
                                        stroke-width="15"
                                    />
                                    <circle
                                        v-for="(
                                            segment, index
                                        ) in donutSegments"
                                        :key="index"
                                        cx="50"
                                        cy="50"
                                        r="35"
                                        fill="none"
                                        :stroke="
                                            segment.label === 'Completed'
                                                ? '#22c55e'
                                                : segment.label === 'Scheduled'
                                                ? '#14b8a6'
                                                : segment.label ===
                                                  'Rescheduled'
                                                ? '#3b82f6'
                                                : segment.label === 'Cancelled'
                                                ? '#ef4444'
                                                : '#d1d5db'
                                        "
                                        stroke-width="15"
                                        :stroke-dasharray="`${
                                            segment.percentage * 2.2
                                        } ${220 - segment.percentage * 2.2}`"
                                        :stroke-dashoffset="
                                            220 -
                                            ((segment.startAngle + 90) * 2.2) /
                                                360
                                        "
                                        class="transition-all duration-300"
                                    />
                                </svg>
                            </div>
                        </div>
                    </div>

                    <!-- Upcoming Scheduled Appointments -->
                    <div class="bg-white rounded-2xl shadow-md p-6">
                        <h2 class="text-xl font-bold text-gray-900 mb-6">
                            Upcoming Scheduled Appointments
                        </h2>

                        <div class="overflow-x-auto">
                            <table class="w-full">
                                <thead>
                                    <tr class="bg-teal-600 text-white">
                                        <th
                                            class="px-4 py-3 text-left text-sm font-semibold"
                                        >
                                            Name
                                        </th>
                                        <th
                                            class="px-4 py-3 text-left text-sm font-semibold"
                                        >
                                            Procedure Type
                                        </th>
                                        <th
                                            class="px-4 py-3 text-left text-sm font-semibold"
                                        >
                                            Date and Time
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-teal-50">
                                    <tr
                                        v-for="appointment in upcomingAppointments"
                                        :key="appointment.id"
                                        class="border-b border-teal-200"
                                    >
                                        <td
                                            class="px-4 py-4 text-sm text-gray-900"
                                        >
                                            {{ appointment.name }}
                                        </td>
                                        <td
                                            class="px-4 py-4 text-sm text-gray-700"
                                        >
                                            {{ appointment.procedure }}
                                        </td>
                                        <td
                                            class="px-4 py-4 text-sm text-gray-700 whitespace-pre-line"
                                        >
                                            {{ appointment.dateTime }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
>>>>>>> Stashed changes
        </div>

        <!-- Top Section -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
          <!-- Left Stats Cards -->
          <div class="space-y-4">
            <!-- Total Appointments Card -->
            <div class="bg-white rounded-2xl shadow-md p-6">
              <div class="flex items-center gap-4">
                <div class="w-16 h-16 bg-teal-100 rounded-xl flex items-center justify-center">
                  <CalendarIcon class="w-8 h-8 text-teal-700" />
                </div>
                <div>
                  <p class="text-4xl font-bold text-gray-900">{{ totalAppointments }}</p>
                  <p class="text-sm text-gray-600 mt-1">Total Appointments (Today)</p>
                </div>
              </div>
            </div>

            <!-- Patients Registered Card -->
            <div class="bg-white rounded-2xl shadow-md p-6">
              <div class="flex items-center gap-4">
                <div class="w-16 h-16 bg-teal-100 rounded-xl flex items-center justify-center">
                  <UserPlusIcon class="w-8 h-8 text-teal-700" />
                </div>
                <div>
                  <p class="text-4xl font-bold text-gray-900">{{ patientsRegistered }}</p>
                  <p class="text-sm text-gray-600 mt-1">Patients Registered</p>
                </div>
              </div>
            </div>

            <!-- Pending Appointments Card -->
            <div class="bg-white rounded-2xl shadow-md p-6">
              <div class="flex items-center gap-4">
                <div class="w-16 h-16 bg-teal-100 rounded-xl flex items-center justify-center">
                  <ClockIcon class="w-8 h-8 text-teal-700" />
                </div>
                <div>
                  <p class="text-4xl font-bold text-gray-900">{{ pendingAppointments }}</p>
                  <p class="text-sm text-gray-600 mt-1">Pending Appointments</p>
                </div>
              </div>
            </div>
          </div>

          <!-- Appointments Chart -->
          <div class="lg:col-span-2 bg-white rounded-2xl shadow-md p-6">
            <div class="flex justify-between items-center mb-6">
              <h2 class="text-xl font-bold text-gray-900">Appointments</h2>
              <select
                v-model="selectedPeriod"
                class="px-4 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-teal-500"
              >
                <option v-for="period in periods" :key="period" :value="period">
                  {{ period }}
                </option>
              </select>
            </div>

            <!-- Chart -->
            <div class="relative h-80">
              <!-- Y-axis labels -->
              <div class="absolute left-0 top-0 bottom-8 flex flex-col justify-between text-xs text-gray-600 w-8">
                <span>60</span>
                <span>50</span>
                <span>40</span>
                <span>30</span>
                <span>20</span>
                <span>10</span>
                <span>0</span>
              </div>

              <!-- Chart area -->
              <div class="ml-12 h-full flex items-end justify-around gap-2 border-b border-l border-gray-300 pb-8">
                <div
                  v-for="item in appointmentsData"
                  :key="item.day"
                  class="flex-1 flex flex-col items-center"
                >
                  <!-- Bar -->
                  <div class="relative w-full flex items-end justify-center" style="height: 280px;">
                    <div
                      v-if="item.value > 0"
                      class="w-full bg-teal-600 rounded-t-lg relative flex items-start justify-center pt-2"
                      :style="{ height: `${getBarHeight(item.value)}%` }"
                    >
                      <span class="text-xs font-semibold text-white">{{ item.value }}</span>
                    </div>
                  </div>
                  <!-- Day label -->
                  <span class="text-xs text-gray-600 mt-2">{{ item.day }}</span>
                </div>
              </div>

              <!-- Total Value -->
              <div class="absolute left-32 top-1/2 -translate-y-1/2 text-center">
                <p class="text-sm text-gray-600">Total Value</p>
                <p class="text-3xl font-bold text-gray-900">{{ totalValue }}</p>
              </div>
            </div>
          </div>
        </div>

        <!-- Bottom Section -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
          <!-- Appointment Status -->
          <div class="bg-white rounded-2xl shadow-md p-6">
            <h2 class="text-xl font-bold text-gray-900 mb-6">Appointment Status</h2>
            
            <div class="flex items-center gap-8">
              <!-- Legend -->
              <div class="flex-1 space-y-3">
                <div
                  v-for="status in appointmentStatus"
                  :key="status.label"
                  class="flex items-center justify-between"
                >
                  <div class="flex items-center gap-2">
                    <div :class="['w-3 h-3 rounded-full', status.color]"></div>
                    <span class="text-sm text-gray-700">{{ status.label }}</span>
                  </div>
                  <span class="text-sm font-semibold text-gray-900">{{ status.percentage }}%</span>
                </div>
              </div>

              <!-- Donut Chart -->
              <div class="relative w-48 h-48">
                <svg viewBox="0 0 100 100" class="transform -rotate-90">
                  <circle
                    cx="50"
                    cy="50"
                    r="35"
                    fill="none"
                    stroke="#e5e7eb"
                    stroke-width="15"
                  />
                  <circle
                    v-for="(segment, index) in donutSegments"
                    :key="index"
                    cx="50"
                    cy="50"
                    r="35"
                    fill="none"
                    :stroke="segment.label === 'Completed' ? '#22c55e' : 
                             segment.label === 'Scheduled' ? '#14b8a6' :
                             segment.label === 'Rescheduled' ? '#3b82f6' :
                             segment.label === 'Cancelled' ? '#ef4444' : '#d1d5db'"
                    stroke-width="15"
                    :stroke-dasharray="`${segment.percentage * 2.2} ${220 - segment.percentage * 2.2}`"
                    :stroke-dashoffset="220 - (segment.startAngle + 90) * 2.2 / 360"
                    class="transition-all duration-300"
                  />
                </svg>
              </div>
            </div>
          </div>

          <!-- Upcoming Scheduled Appointments -->
          <div class="bg-white rounded-2xl shadow-md p-6">
            <h2 class="text-xl font-bold text-gray-900 mb-6">Upcoming Scheduled Appointments</h2>
            
            <div class="overflow-x-auto">
              <table class="w-full">
                <thead>
                  <tr class="bg-teal-600 text-white">
                    <th class="px-4 py-3 text-left text-sm font-semibold">Name</th>
                    <th class="px-4 py-3 text-left text-sm font-semibold">Procedure Type</th>
                    <th class="px-4 py-3 text-left text-sm font-semibold">Date and Time</th>
                  </tr>
                </thead>
                <tbody class="bg-teal-50">
                  <tr
                    v-for="appointment in upcomingAppointments"
                    :key="appointment.id"
                    class="border-b border-teal-200"
                  >
                    <td class="px-4 py-4 text-sm text-gray-900">{{ appointment.name }}</td>
                    <td class="px-4 py-4 text-sm text-gray-700">{{ appointment.procedure }}</td>
                    <td class="px-4 py-4 text-sm text-gray-700 whitespace-pre-line">{{ appointment.dateTime }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<style scoped>
/* Custom select styling */
select {
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%236b7280'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E");
  background-repeat: no-repeat;
  background-position: right 0.5rem center;
  background-size: 1.5em 1.5em;
  padding-right: 2.5rem;
  appearance: none;
}
</style>