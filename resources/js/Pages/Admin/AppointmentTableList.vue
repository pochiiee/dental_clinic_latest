<script setup>
import AppointmentDetailsModal from '@/Components/AdminViewDetails.vue'
import AdminRescheduleModal from '@/Components/AdminRescheduleModal.vue'
import { ref } from 'vue'
import { useToast } from 'vue-toastification'
import { router } from '@inertiajs/vue3'

const props = defineProps({
  appointments: {  // This is the correct prop name
    type: Array,
    required: true,
  },
})
const emit = defineEmits(['view', 'reschedule', 'cancel'])

// 🔹 Toast instance
const toast = useToast()

// 🔹 STATE 
const showDetailsModal = ref(false)
const showRescheduleModal = ref(false)
const selectedAppointment = ref(null)

// 🔹 Open Details
const openAppointmentDetails = (appointment) => {
  selectedAppointment.value = appointment
  showDetailsModal.value = true
}

// 🔹 Close Details
const closeDetailsModal = () => {
  showDetailsModal.value = false
  selectedAppointment.value = null
}

// 🔹 Open Reschedule Modal
const handleReschedule = (appointment) => {
  showDetailsModal.value = false
  selectedAppointment.value = appointment
  showRescheduleModal.value = true
}

// 🔹 Reload appointments from server
const fetchAppointments = () => {
  router.reload({ only: ['appointments'] })
}

// 🔹 Handle completion / cancel / reschedule
const handleCompleteAppointment = (appointment) => {
  toast.success(`Appointment #${appointment.id} marked as completed`)
  closeDetailsModal()
  fetchAppointments() // Refresh to show updated status
}

const handleRescheduleAppointment = (appointment) => {
  toast.info(`Appointment #${appointment.id} rescheduling initiated`)
  closeDetailsModal()
  fetchAppointments() // Refresh to show updated status
}

const handleCancelAppointment = (appointment) => {
  toast.warning(`Appointment #${appointment.id} cancelled`)
  closeDetailsModal()
  fetchAppointments() // Refresh to show updated status
  emit('cancel') // Notify parent to refresh
}

// 🔹 Utils
const formatDate = (dateString) => {
  if (!dateString) return 'N/A'
  const date = new Date(dateString)
  return date.toLocaleDateString('en-US', {
    month: '2-digit',
    day: '2-digit',
    year: 'numeric',
  })
}

const getPaymentDisplayText = (paymentStatus) => {
  if (!paymentStatus) return 'Pending'

  const status = paymentStatus.toLowerCase()
  if (status.includes('completed') || status.includes('paid')) return 'Paid'
  if (status.includes('failed')) return 'Failed'
  if (status.includes('pending')) return 'Pending'

  return paymentStatus
}

const getPaymentStatusClass = (paymentStatus) => {
  if (!paymentStatus) return 'text-gray-600'

  const status = paymentStatus.toLowerCase()
  if (status.includes('completed') || status.includes('paid')) return 'text-green-600'
  if (status.includes('failed')) return 'text-red-600'
  if (status.includes('pending')) return 'text-yellow-600'

  return 'text-gray-600'
}

const getStatusClass = (status) => {
  const statusLower = status?.toLowerCase()
  return {
    'text-green-600': statusLower === 'completed',
    'text-red-600': statusLower === 'cancelled',
    'text-yellow-600': statusLower === 'rescheduled',
    'text-blue-600': statusLower === 'pending' || statusLower === 'scheduled',
    'text-gray-600': !status,
  }
}

// Check if appointment can be actioned (not cancelled or completed)
const canPerformActions = (appointment) => {
  const status = appointment.status?.toLowerCase()
  return !(status === 'cancelled' || status === 'completed')
}
</script>

<template>
  <div class="bg-white rounded-2xl shadow-md overflow-hidden w-[1050px] h-[520px] mx-auto border border-gray-300 flex flex-col font-rem">
    <!-- Table Wrapper with Scroll -->
    <div class="flex-1 overflow-y-auto">
      <table class="w-full text-sm border-collapse">
        <thead class="bg-neutral text-white sticky top-0 z-10">
          <tr>
            <th class="border p-2 text-center w-[50px]">ID</th>
            <th class="border p-2 text-center w-[150px]">Patient Name</th>
            <th class="border p-2 text-center w-[150px]">Procedure Type</th>
            <th class="border p-2 text-center w-[150px]">Date and Time</th>
            <th class="border p-2 text-left w-[250px]">Tools/Equipment Reminder</th>
            <th class="border p-2 text-center w-[100px]">Payment Status</th>
            <th class="border p-2 text-center w-[120px]">Status</th>
            <th class="border p-2 text-center w-[120px]">Action</th>
          </tr>
        </thead>

        <tbody class="bg-white">
          <!-- FIX: Use props.appointments instead of appointments -->
          <tr
            v-for="(a, i) in props.appointments"
            :key="i"
            class="hover:bg-gray-50 border-b text-center last:border-none bg-white"
            :class="{
              'bg-red-50': a.status?.toLowerCase() === 'cancelled',
              'bg-green-50': a.status?.toLowerCase() === 'completed',
            }"
          >
            <!-- ID -->
            <td class="border p-2 text-center bg-inherit">{{ a.id }}</td>

            <!-- Patient Name -->
            <td class="border p-2 text-sm font-medium truncate bg-inherit">
              {{ a.patient }}
            </td>

            <!-- Procedure -->
            <td class="border p-2 text-sm truncate bg-inherit">
              {{ a.procedure }}
            </td>

            <!-- Date and Time -->
            <td class="border p-2 text-sm whitespace-nowrap truncate bg-inherit">
              <div>{{ formatDate(a.date) }}</div>
              <div class="text-gray-500 text-xs">{{ a.time }}</div>
            </td>

            <!-- Tools List -->
            <td class="border p-2 text-gray-700 align-top text-left bg-inherit">
              <div class="flex flex-col gap-1">
                <div
                  v-for="(tool, tIdx) in Array.isArray(a.tools) ? a.tools : [a.tools]"
                  :key="tIdx"
                  class="text-xs text-gray-700"
                >
                  • {{ tool }}
                </div>
              </div>
            </td>

            <!-- Payment -->
            <td
              class="border p-2 text-center font-semibold bg-inherit"
              :class="getPaymentStatusClass(a.payment)"
            >
              {{ getPaymentDisplayText(a.payment) }}
            </td>

            <!-- Status -->
            <td
              class="border p-2 font-semibold text-center capitalize bg-inherit"
              :class="getStatusClass(a.status)">
              {{ a.status }}
            </td>

            <!-- Action -->
            <td
              class="border p-2 text-sm text-center bg-inherit"
              :class="canPerformActions(a) ? 'cursor-pointer underline' : 'text-gray-400'"
              @click="canPerformActions(a) ? openAppointmentDetails(a) : null"
            >
              {{ canPerformActions(a) ? 'View Details' : 'No Actions' }}
            </td>
          </tr>

          <!-- Empty State -->
          <!-- FIX: Use props.appointments here too -->
          <tr v-if="!props.appointments || props.appointments.length === 0" class="bg-white">
            <td colspan="8" class="text-center py-6 text-gray-500 bg-white">
              No appointments found.
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Appointment Details Modal -->
  <AppointmentDetailsModal
    v-if="showDetailsModal"
    :appointment="selectedAppointment"
    @close="closeDetailsModal"
    @complete="handleCompleteAppointment"
    @reschedule="handleReschedule"
    @cancel="handleCancelAppointment"
  />

  <!-- Admin Reschedule Modal -->
  <AdminRescheduleModal
    v-if="showRescheduleModal"
    v-model="showRescheduleModal"
    :appointmentId="selectedAppointment?.id"
    :selectedDate="selectedAppointment?.date"
    :selectedScheduleId="selectedAppointment?.schedule_id"
    @updated="fetchAppointments"
  />
</template>

<style scoped>
table {
  border: 1px solid #ccc;
}
th,
td {
  border-color: #d1d5db;
}
tbody tr:hover {
  background-color: #f9fafb;
}
thead th {
  position: sticky;
  top: 0;
  color: white;
  z-index: 10;
}
</style>