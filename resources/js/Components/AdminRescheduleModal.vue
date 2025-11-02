<script setup>
import { ref, computed, onMounted, watch } from "vue"
import axios from "axios"
import { useToast } from "vue-toastification"
import { router } from "@inertiajs/vue3"
import AppModal from "@/Components/AppModal.vue"

const props = defineProps({
  modelValue: Boolean,
  appointmentId: {
    type: Number,
    required: true
  },
  appointmentStatus: {
    type: String,
    default: 'pending'
  },
  selectedDate: String,
  selectedScheduleId: [String, Number],
})

const emits = defineEmits(["update:modelValue", "updated"])

const toast = useToast()
const localSelectedDate = ref(props.selectedDate || "")
const localSelectedScheduleId = ref(props.selectedScheduleId || "")
const schedules = ref([])
const isLoading = ref(false)
const errorMessage = ref("")
const showConfirmModal = ref(false)
const scheduleCache = ref({})
const bookedSlots = ref([]) // Track booked slots for the selected date

const currentMonth = ref(new Date().getMonth())
const currentYear = ref(new Date().getFullYear())

// Use the same origin as the frontend to avoid CORS/CSRF issues
const API_BASE = window.location.origin

// Check if appointment can be rescheduled
const canReschedule = computed(() => {
  return props.appointmentStatus !== 'cancelled';
});

const monthNames = [
  "January","February","March","April","May","June",
  "July","August","September","October","November","December"
]
const dayNames = ["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"]

const getDaysInMonth = (y, m) => new Date(y, m + 1, 0).getDate()
const getFirstDayOfMonth = (y, m) => new Date(y, m, 1).getDay()

const daysInMonth = computed(() => getDaysInMonth(currentYear.value, currentMonth.value))
const firstDay = computed(() => getFirstDayOfMonth(currentYear.value, currentMonth.value))

const calendarDays = computed(() => {
  const days = []
  for (let i = 0; i < firstDay.value; i++) days.push(null)
  for (let d = 1; d <= daysInMonth.value; d++) days.push(d)
  return days
})

const getDateStatus = (day) => {
  if (!day) return { isPast: false, isClosed: false }
  const dateObj = new Date(currentYear.value, currentMonth.value, day)
  const today = new Date()
  today.setHours(0, 0, 0, 0)
  const isPast = dateObj < today
  const isClosed = dateObj.getDay() === 0 || dateObj.getDay() === 1 
  return { isPast, isClosed }
}

const selectDate = (day) => {
  if (!canReschedule.value) return
  
  const { isPast, isClosed } = getDateStatus(day)
  if (!day || isPast || isClosed) return
  const formatted = `${currentYear.value}-${String(currentMonth.value + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`
  localSelectedDate.value = formatted
  fetchSchedules(formatted)
  fetchBookedSlots(formatted) // Fetch booked slots when date changes
}

const nextMonth = () => {
  if (currentMonth.value === 11) {
    currentMonth.value = 0
    currentYear.value++
  } else {
    currentMonth.value++
  }
}

const prevMonth = () => {
  if (currentMonth.value === 0) {
    currentMonth.value = 11
    currentYear.value--
  } else {
    currentMonth.value--
  }
}

// Filter out booked time slots
const availableTimeSlots = computed(() => {
  if (!schedules.value.length) return []
  
  return schedules.value
    .map(slot => ({
      label: slot.display_time,
      value: slot.start_time || slot.time_slot,
      scheduleId: slot.schedule_id,
      isBooked: bookedSlots.value.includes(slot.schedule_id)
    }))
    .filter(slot => !slot.isBooked) // Only show available slots
})

/**
 * Fetch schedules available for a date
 */
const fetchSchedules = async (date, isPrefetch = false) => {
  if (!date) {
    schedules.value = []
    return
  }

  // ✅ Return cached result immediately if available
  if (scheduleCache.value[date]) {
    schedules.value = scheduleCache.value[date]
    if (!isPrefetch) isLoading.value = false
    return
  }

  if (!isPrefetch) {
    isLoading.value = true
    errorMessage.value = ""
  }

  try {
    const res = await axios.get(`${API_BASE}/schedules/${date}/available-slots`)
    const data = res.data.available_slots || res.data || []

    // ✅ Cache the result
    scheduleCache.value[date] = data

    if (!isPrefetch) {
      schedules.value = data
      errorMessage.value = data.length ? "" : "No available time slots for this date"
    }

  } catch (err) {
    console.error("fetchSchedules error:", err)
    if (!isPrefetch) {
      errorMessage.value = "Failed to load schedules."
      schedules.value = []
    }
  } finally {
    if (!isPrefetch) isLoading.value = false
  }
}

/**
 * Fetch booked slots for the selected date
 */
const fetchBookedSlots = async (date) => {
  if (!date) {
    bookedSlots.value = []
    return
  }

  try {
    const res = await axios.get(`${API_BASE}/admin/appointments/booked-slots`, {
      params: { date }
    })
    
    // Assuming the API returns an array of booked schedule IDs
    bookedSlots.value = res.data.booked_slots || res.data || []
    
  } catch (err) {
    console.error("fetchBookedSlots error:", err)
    bookedSlots.value = []
  }
}

const handleTimeSelect = (slot) => {
  if (!canReschedule.value) return
  
  localSelectedScheduleId.value = slot.scheduleId
}

watch(() => props.selectedDate, (newDate) => {
  localSelectedDate.value = newDate || ""
  if (newDate) {
    fetchSchedules(newDate)
    fetchBookedSlots(newDate)
  }
})

watch(() => props.selectedScheduleId, (newId) => {
  localSelectedScheduleId.value = newId || ""
})

onMounted(() => {
  if (props.selectedDate) {
    fetchSchedules(props.selectedDate)
    fetchBookedSlots(props.selectedDate)
  }
})

/**
 * Open confirm modal after selecting
 */
const confirmSelection = () => {
  // Check if appointment is cancelled
  if (!canReschedule.value) {
    toast.error("Cancelled appointments cannot be rescheduled.")
    return
  }

  if (!localSelectedDate.value) {
    errorMessage.value = "Please select a date."
    return
  }
  if (!localSelectedScheduleId.value) {
    errorMessage.value = "Please select a time."
    return
  }

  errorMessage.value = ""
  showConfirmModal.value = true
}

/**
 * Confirm and perform reschedule using Inertia.js form submission
 */
const confirmReschedule = async () => {
  showConfirmModal.value = false

  if (!props.appointmentId) {
    toast.error("Invalid appointment selected.")
    return
  }

  router.patch(route('admin.appointments.reschedule', props.appointmentId), {
    date: localSelectedDate.value,
    schedule_id: localSelectedScheduleId.value,
  }, {
    preserveScroll: true,
    onSuccess: () => {
      toast.success("Appointment rescheduled successfully.")
      emits("update:modelValue", false)
      emits("updated")
    },
    onError: (errors) => {
      if (errors.date) {
        toast.error(errors.date)
      } else if (errors.schedule_id) {
        toast.error(errors.schedule_id)
      } else if (errors.message) {
        toast.error(errors.message)
      } else {
        toast.error("Failed to reschedule appointment.")
      }
    }
  })
}

const cancelReschedule = () => {
  showConfirmModal.value = false
}
</script>

<template>
  <div v-if="modelValue" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white p-6 rounded-xl shadow-lg w-[90%] md:w-[700px] relative">
      <button 
        @click="$emit('update:modelValue', false)" 
        class="absolute top-3 right-5 text-2xl text-gray-600 hover:text-black"
      >
        ✕
      </button>

      <h2 class="text-xl font-semibold mb-4 text-[#0E5C5C]">Reschedule Appointment</h2>
      
      <!-- Show warning if appointment is cancelled -->
      <div v-if="!canReschedule" class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
        <p class="font-semibold">Cannot Reschedule</p>
        <p>This appointment has been cancelled and cannot be rescheduled.</p>
      </div>

      <p class="font-medium text-sm text-gray-600 mb-4" v-if="localSelectedScheduleId && canReschedule">
        Selected Time: {{ availableTimeSlots.find(slot => slot.scheduleId === localSelectedScheduleId)?.label || 'Not selected' }}
      </p>

      <div class="flex flex-col md:flex-row gap-10" :class="{ 'opacity-50 pointer-events-none': !canReschedule }">
        <!-- Calendar -->
        <div class="flex-1 p-3 border-2 rounded-xl border-gray-700">
          <div class="flex items-center justify-between mb-4">
            <span class="font-semibold">{{ monthNames[currentMonth] }} {{ currentYear }}</span>
            <div class="flex items-center">
              <button @click="prevMonth" class="p-1 hover:scale-105 transition-transform">
                <img src="/icons/arrow_back.png" alt="Previous" class="w-4 h-4" />
              </button>
              <button @click="nextMonth" class="p-1 hover:scale-105 transition-transform">
                <img src="/icons/arrow_forward.png" alt="Next" class="w-4 h-4" />
              </button>
            </div>
          </div>

          <div class="grid grid-cols-7 gap-2 mb-2 text-sm font-semibold">
            <div v-for="d in dayNames" :key="d" class="text-center">{{ d }}</div>
          </div>

          <div class="grid grid-cols-7 gap-2">
            <div
              v-for="(day, index) in calendarDays"
              :key="index"
              class="p-2 text-center rounded cursor-pointer transition font-medium"
              :class="{
                'bg-gray-300 text-gray-500 cursor-not-allowed': getDateStatus(day).isPast,
                'bg-red-400 text-white cursor-not-allowed': getDateStatus(day).isClosed,
                'bg-[#0E5C5C] text-white': localSelectedDate && localSelectedDate.endsWith(String(day).padStart(2, '0')),
                'hover:bg-[#0E5C5C] hover:text-white': !getDateStatus(day).isPast && !getDateStatus(day).isClosed && canReschedule
              }"
              @click="selectDate(day)">
              {{ day }}
            </div>
          </div>
        </div>

        <!-- Time Slots -->
        <div class="flex-1">
          <h2 class="text-lg font-semibold mb-2">Available Time Slots</h2>

          <!-- Loading State -->
          <div v-if="isLoading" class="flex flex-col space-y-2 py-2">
            <div v-for="n in 4" :key="n" class="h-5 bg-gray-200 rounded animate-pulse"></div>
          </div>

          <!-- Available Slots -->
          <div v-else-if="availableTimeSlots.length" class="flex flex-col space-y-4">
            <label
              v-for="slot in availableTimeSlots"
              :key="slot.scheduleId"
              class="flex items-center font-semibold space-x-3 p-2 hover:bg-gray-50 rounded cursor-pointer"
              :class="{ 'opacity-50': !canReschedule }"
            >
              <input
                type="radio"
                class="w-5 h-5 accent-[#0E5C5C] focus:ring-0 cursor-pointer"
                :value="slot.scheduleId"
                :checked="Number(localSelectedScheduleId) === Number(slot.scheduleId)"
                @change="handleTimeSelect(slot)"
                :disabled="!canReschedule"
              />
              <span>{{ slot.label }}</span>
            </label>
          </div>

          <!-- No Slots Available -->
          <div v-else class="text-gray-400 text-sm mt-4 p-4 bg-gray-50 rounded text-center">
            {{ errorMessage || 'No available time slots for selected date.' }}
          </div>
        </div>
      </div>

      <!-- Error Message -->
      <p v-if="errorMessage && availableTimeSlots.length === 0" class="text-red-600 font-medium mt-4 text-center">{{ errorMessage }}</p>

      <div class="mt-6 flex justify-end">
        <button
          @click="confirmSelection"
          :disabled="!localSelectedScheduleId || !canReschedule"
          class="bg-[#0E5C5C] text-white px-6 py-2 rounded-full hover:bg-[#084646] transition-colors disabled:bg-gray-400 disabled:cursor-not-allowed"
        >
          Confirm Reschedule
        </button>
      </div>
    </div>
  </div>

  <AppModal :show="showConfirmModal" @close="cancelReschedule">
    <template #default>
      <div class="p-4 text-center">
        <h3 class="text-lg font-semibold text-gray-800 mb-2">Confirm Reschedule</h3>
        <p class="text-gray-600">Are you sure you want to reschedule this appointment to</p>
        <p class="text-gray-600">
          <span class="font-semibold">{{ localSelectedDate }}</span> 
          at 
          <span class="font-semibold">{{ availableTimeSlots.find(slot => slot.scheduleId === localSelectedScheduleId)?.label }}</span>?
        </p>
      </div>
    </template>

    <template #actions>
      <div class="flex justify-center items-center w-full pb-4">
        <div class="flex gap-4">
          <button
            @click="cancelReschedule"
            class="px-5 py-2 rounded-lg bg-gray-200 hover:bg-gray-300 text-gray-800 font-medium"
          >
            Cancel
          </button>
          <button
            @click="confirmReschedule"
            class="px-5 py-2 rounded-lg bg-[#0E5C5C] text-white hover:bg-[#084646] font-medium"
          >
            Confirm
          </button>
        </div>
      </div>
    </template>
  </AppModal>
</template>