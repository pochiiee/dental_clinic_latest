<script setup>
import { ref, watch, defineProps, defineEmits } from 'vue'
import { router } from '@inertiajs/vue3'
import { useToast } from 'vue-toastification'
import { XMarkIcon } from '@heroicons/vue/24/outline'

const toast = useToast()

const props = defineProps({
  appointment: {
    type: Object,
    default: () => ({
      id: '',
      patient: '',
      procedure: '',
      date: '',
      time: '',
      tools: [],
      payment: '',
      status: ''
    })
  }
})

const emit = defineEmits(['close', 'reschedule', 'cancel', 'update-status'])
const isComplete = ref(false)
const showCancelConfirm = ref(false)

// Watch appointment status to sync checkbox
watch(
  () => props.appointment.status,
  (newStatus) => {
    isComplete.value = newStatus?.toLowerCase() === 'completed'
  },
  { immediate: true }
)

// ✅ Update appointment status
const updateStatus = () => {
  const newStatus = isComplete.value ? 'completed' : 'pending'

  router.patch(`/admin/appointments/${props.appointment.id}/status`, 
    { status: newStatus }, 
    {
      preserveScroll: true,
      onSuccess: () => {
        toast.success(`Appointment marked as ${newStatus}`)
        emit('update-status', { ...props.appointment, status: newStatus })
      },
      onError: (errors) => {
        toast.error('Failed to update status.')
        console.error('Error updating appointment status:', errors)
      }
    }
  )
}

// ✅ Cancel appointment with confirmation
const cancelAppointment = () => {
  showCancelConfirm.value = true
}

const confirmCancel = () => {
  console.log('Cancelling appointment:', props.appointment.id);
  
  router.patch(`/admin/appointments/${props.appointment.id}/cancel`, 
    {}, 
    {
      preserveScroll: true,
      preserveState: true,
      onSuccess: (page) => {
        // The page will reload with the flash message
        console.log('Cancel successful, page reloaded');
        emit('close')
        showCancelConfirm.value = false
      },
      onError: (errors) => {
        console.error('Cancel error:', errors);
        // Errors will be handled by the flash messages
        showCancelConfirm.value = false
      }
    }
  )
}

const cancelCancellation = () => {
  showCancelConfirm.value = false
}

const formatDateTime = (date, time) => {
  if (!date) return ''
  const dateObj = new Date(date)
  const formattedDate = dateObj.toLocaleDateString('en-US', {
    year: 'numeric', month: 'long', day: 'numeric'
  })
  return time ? `${formattedDate} ${time}` : formattedDate
}

const getPaymentDisplayText = (paymentStatus) => {
  if (!paymentStatus) return 'Pending'
  const status = paymentStatus.toLowerCase()
  if (status.includes('paid')) return 'Paid'
  if (status.includes('failed')) return 'Failed'
  return 'Pending'
}
</script>

<template>
  <div 
    class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-40 z-50"
    @click.self="emit('close')">

    <!-- Main Appointment Details Modal -->
    <div class="bg-white rounded-xl shadow-lg w-[400px] transition-all">
      <!-- Header -->
      <div class="flex justify-between items-center border-b px-5 py-3">
        <h2 class="text-lg font-semibold text-[#0b7464]">Appointment Details</h2>
        <button 
          @click="emit('close')" 
          class="text-gray-500 hover:text-gray-700 transition">
          <XMarkIcon class="w-5 h-5" />
        </button>
      </div>

      <!-- Content -->
      <div class="px-6 py-4 space-y-3 text-sm">
        <div><span class="font-semibold">ID:</span> {{ appointment.id || '' }}</div>
        <div><span class="font-semibold">Patient Name:</span> {{ appointment.patient || '' }}</div>
        <div><span class="font-semibold">Procedure Type:</span> {{ appointment.procedure || '' }}</div>
        <div><span class="font-semibold">Date and Time:</span> {{ formatDateTime(appointment.date, appointment.time) }}</div>

        <div>
          <span class="font-semibold block mb-1">Tools/Equipment Reminder List:</span>
          <ul class="list-disc list-inside space-y-1 pl-2 text-gray-700">
            <li v-for="(tool, index) in appointment.tools" :key="index">{{ tool }}</li>
            <li v-if="!appointment.tools?.length" class="text-gray-500">No tools required</li>
          </ul>
        </div>

        <div><span class="font-semibold">Payment Status:</span> {{ getPaymentDisplayText(appointment.payment) }}</div>

        <!-- Status + Checkbox -->
        <div class="flex items-center justify-between pb-2">
          <div>
            <span class="font-semibold text-gray-700">Status:</span>
            <span 
              class="ml-1 capitalize font-semibold"
              :class="{
                'text-green-600': appointment.status?.toLowerCase() === 'completed',
                'text-red-600': appointment.status?.toLowerCase() === 'cancelled',
                'text-yellow-600': appointment.status?.toLowerCase() === 'rescheduled',
                'text-blue-600': appointment.status?.toLowerCase() === 'pending' || appointment.status?.toLowerCase() === 'scheduled',
                'text-gray-600': !appointment.status
              }"
            >
              {{ appointment.status || 'N/A' }}
            </span>
          </div>

          <label class="flex items-center border-l-2 border-dark pl-4 mr-20 space-x-2 cursor-pointer">
            <input 
              type="checkbox"
              v-model="isComplete"
              @change="updateStatus"
              class="rounded border-gray-300 text-[#0b7464] shadow-sm focus:ring-[#0b7464]"
            />
            <span class="text-sm">Complete</span>
          </label>
        </div>
      </div>

      <!-- Footer Buttons -->
      <div class="flex justify-end items-center border-t px-6 py-3 space-x-6 text-sm">
        <button 
          @click="emit('reschedule', appointment)"
          class="text-dark font-semibold hover:underline">
          Reschedule
        </button>
        <button 
          @click="cancelAppointment"
          class="text-dark font-semibold hover:underline">
          Cancel
        </button>
      </div>
    </div>

    <!-- Cancel Confirmation Modal -->
    <div 
      v-if="showCancelConfirm"
      class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 z-[60]"
      @click.self="cancelCancellation">
      
      <div class="bg-white rounded-xl shadow-lg w-[400px] p-6">
        <div class="text-center">
          
          <h3 class="text-lg font-semibold text-gray-800 mb-2">Cancel Appointment?</h3>
          <p class="text-gray-600 text-sm mb-6">
            Are you sure you want to cancel this appointment for 
            <span class="font-semibold">{{ appointment.patient }}</span>?
          </p>
          
          <div class="space-x-6 text-right">
            <button
              @click="confirmCancel"
              class="text-dark text-sm hover:underline">
              Yes
            </button>
            <button
              @click="cancelCancellation"
              class=" text-dark text-sm hover:underline">
              No
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>