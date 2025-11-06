<template>
  <Modal :show="modelValue" @close="$emit('update:modelValue', false)" max-width="2xl">
    <div class="p-6">
      <!-- Header -->
      <div class="text-center mb-6">
        <h2 class="text-2xl font-bold text-gray-900">Complete Payment</h2>
        <p class="text-gray-600 mt-2">Pay ₱300.00 to confirm your appointment</p>
      </div>

      <!-- Appointment Summary -->
      <div class="bg-gray-50 rounded-lg p-4 mb-6">
        <h3 class="font-semibold text-lg mb-3">Appointment Details</h3>
        <div class="space-y-2 text-sm">
          <div class="flex justify-between">
            <span class="text-gray-600">Service:</span>
            <span class="font-medium">{{ appointmentData.service }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-gray-600">Date:</span>
            <span class="font-medium">{{ appointmentData.date }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-gray-600">Time:</span>
            <span class="font-medium">{{ appointmentData.time }}</span>
          </div>
          <div class="flex justify-between border-t pt-2 mt-2">
            <span class="text-gray-600 font-semibold">Total Amount:</span>
            <span class="font-bold text-lg">₱300.00</span>
          </div>
        </div>
      </div>

      <!-- Payment Information -->
      <div class="mb-6">
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
          <h4 class="font-semibold text-blue-800 mb-2">How it works:</h4>
          <ul class="text-sm text-blue-700 space-y-1">
            <li>• Click "Proceed to Payment" below</li>
            <li>• You'll be redirected to PayMongo's secure checkout</li>
            <li>• Choose your preferred payment method (GCash, GrabPay, Maya, or Card)</li>
            <li>• Complete the payment on PayMongo's platform</li>
            <li>• You'll be redirected back to confirm your appointment</li>
          </ul>
        </div>
      </div>

      <!-- Available Payment Methods Info -->
      <div class="mb-6">
        <p class="text-sm text-gray-600 text-center mb-3">Available on PayMongo checkout:</p>
        <div class="flex justify-center space-x-6">
          <div class="text-center">
            <div class="w-12 h-8 bg-green-500 rounded flex items-center justify-center mx-auto mb-1">
              <span class="text-white text-xs font-bold">GCash</span>
            </div>
            <span class="text-xs text-gray-500">GCash</span>
          </div>
          <div class="text-center">
            <div class="w-12 h-8 bg-green-400 rounded flex items-center justify-center mx-auto mb-1">
              <span class="text-white text-xs font-bold">Grab</span>
            </div>
            <span class="text-xs text-gray-500">GrabPay</span>
          </div>
          <div class="text-center">
            <div class="w-12 h-8 bg-purple-500 rounded flex items-center justify-center mx-auto mb-1">
              <span class="text-white text-xs font-bold">Maya</span>
            </div>
            <span class="text-xs text-gray-500">Maya</span>
          </div>
          <div class="text-center">
            <div class="w-12 h-8 bg-blue-500 rounded flex items-center justify-center mx-auto mb-1">
              <span class="text-white text-xs font-bold">Card</span>
            </div>
            <span class="text-xs text-gray-500">Card</span>
          </div>
        </div>
      </div>

      <!-- Pay Button -->
      <button
        @click="createCheckoutSession"
        :disabled="loading"
        :class="[
          'w-full py-3 bg-green-500 text-white rounded-md hover:bg-green-600 font-semibold transition-colors mb-3',
          loading ? 'opacity-50 cursor-not-allowed' : ''
        ]"
      >
        {{ loading ? 'Creating Payment...' : 'Proceed to Payment' }}
      </button>

      <!-- Cancel Button -->
      <button
        @click="cancelPayment"
        :disabled="loading"
        :class="[
          'w-full py-2 bg-gray-500 text-white rounded-md hover:bg-gray-600 transition-colors',
          loading ? 'opacity-50 cursor-not-allowed' : ''
        ]"
      >
        Cancel
      </button>

      <!-- Error Message -->
      <div v-if="error" class="mt-4 p-3 bg-red-50 border border-red-200 rounded-md">
        <p class="text-red-700 text-sm">{{ error }}</p>
      </div>

      <!-- Security Note -->
      <div class="mt-4 text-center">
        <p class="text-xs text-gray-500">
          🔒 Secure payment powered by PayMongo
        </p>
      </div>
    </div>
  </Modal>
</template>

<script setup>
import { ref } from 'vue'
import { useForm, usePage } from '@inertiajs/vue3'

const props = defineProps({
  modelValue: Boolean,
  appointmentData: {
    type: Object,
    required: true,
    default: () => ({
      service: '',
      serviceId: '',
      date: '',
      time: '',
      scheduleId: ''
    })
  }
})

const emit = defineEmits(['update:modelValue', 'payment-success', 'payment-cancelled'])

const loading = ref(false)
const error = ref('')

// Use Inertia form (handles CSRF automatically)
const form = useForm({
  service_id: props.appointmentData.serviceId,
  service_name: props.appointmentData.service,
  appointment_date: props.appointmentData.date,
  schedule_id: props.appointmentData.scheduleId
})

const createCheckoutSession = () => {
  loading.value = true
  error.value = ''

  form.post('/customer/payment/create', {
    preserveScroll: true,
    onSuccess: (data) => {
      // Redirect to PayMongo checkout
      if (data.props.checkout_url) {
        window.location.href = data.props.checkout_url
      } else {
        error.value = 'No checkout URL received'
      }
    },
    onError: (errors) => {
      error.value = errors.error || 'Payment processing failed. Please try again.'
    },
    onFinish: () => {
      loading.value = false
    }
  })
}

const cancelPayment = () => {
  emit('payment-cancelled')
  emit('update:modelValue', false)
}
</script>