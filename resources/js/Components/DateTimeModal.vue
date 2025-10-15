<script setup>
import { ref, computed } from 'vue'

const props = defineProps({
  modelValue: Boolean,
  selectedDate: String,
  selectedTime: String,
  selectedTimeLabel: String
})

const emits = defineEmits([
  "update:modelValue",
  "update:selectedDate",
  "update:selectedTime",
  "update:selectedTimeLabel"
])

const localSelectedDate = ref(props.selectedDate)
const localSelectedTime = ref(props.selectedTime)
const errorMessage = ref("")

// Calendar state
const currentMonth = ref(new Date().getMonth())
const currentYear = ref(new Date().getFullYear())
const timeSlots = [
  { label: "10:00 am - 12:00 pm", value: "10:00:00" },
  { label: "1:00 pm - 3:00 pm", value: "13:00:00" },
  { label: "3:00 pm - 5:00 pm", value: "15:00:00" }
]

const monthNames = [
  "January","February","March","April","May","June",
  "July","August","September","October","November","December"
]
const dayNames = ["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"]

// Helpers
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

const isUnavailable = (day) => {
  if (!day) return false
  const date = new Date(currentYear.value, currentMonth.value, day)
  const weekday = date.getDay()
  return weekday === 0 || weekday === 1
}

const selectDate = (day) => {
  if (!isUnavailable(day)) {
    const formatted = `${currentYear.value}-${String(currentMonth.value + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`
    localSelectedDate.value = formatted
    emits("update:selectedDate", formatted)
  }
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

// ✅ Validation before closing
const confirmSelection = () => {
  if (!localSelectedDate.value) {
    errorMessage.value = "Please select a date."
    return
  }
  if (!props.selectedTime) {
    errorMessage.value = "Please select a time."
    return
  }

  errorMessage.value = "" // clear error
  emits("update:modelValue", false)
}
</script>

<template>
  <div v-if="modelValue" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white p-6 rounded-xl shadow-lg w-[90%] md:w-[700px] relative">

      <!-- Close button -->
      <button @click="$emit('update:modelValue', false)" class="absolute top-3 right-5 text-2xl text-gray-600 hover:text-black">✕</button>

      <h2 class="text-xl font-semibold mb-4 text-dark">Choose Available Slots</h2>
      <p class="font-medium">Date</p>

      <div class="flex flex-col md:flex-row gap-10">
        <!-- Calendar -->
        <div class="flex-1 p-2 border-2 rounded-xl border-black">

        <div class="flex items-center justify-between mb-4">
        <span class="font-semibold">{{ monthNames[currentMonth] }} {{ currentYear }}</span>

        <!-- Arrow Buttons Group -->
        <div class="flex items-center space-x-0">
            <button @click="prevMonth" class="p-1 hover:scale-105 transition-transform">
            <img src="/icons/arrow_back.png" alt="Previous" class="w-4 h-4" />
            </button>
            <button @click="nextMonth" class="p-1 hover:scale-105 transition-transform -ml-1">
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
              class="p-2 text-center rounded cursor-pointer"
              :class="{
                'bg-red-400 rounded-lg cursor-not-allowed': isUnavailable(day),
                'bg-dark text-white': selectedDate?.endsWith(String(day).padStart(2, '0'))
              }"
              @click="selectDate(day)"
            >
              {{ day }}
            </div>
          </div>
        </div>

        <!-- Time -->
        <div class="flex-1">
          <h2 class="text-lg font-semibold mb-2">Time</h2>
          <div class="flex flex-col space-y-4">
            <label v-for="slot in timeSlots" :key="slot.value" class="flex items-center font-semibold space-x-3">
            <input
            type="radio"
            class="w-5 h-5 accent-[#0E5C5C] focus:ring-0 cursor-pointer"
            :value="slot.value"
            :checked="slot.value === selectedTime"
            @change="
                $emit('update:selectedTime', slot.value);
                $emit('update:selectedTimeLabel', slot.label)
            "
            />

              <span>{{ slot.label }}</span>
            </label>
          </div>
        </div>

      </div>

      <!-- Error Message -->
      <p v-if="errorMessage" class="text-red-600 font-medium mt-4">{{ errorMessage }}</p>

      <!-- Confirm -->
      <div class="mt-6 flex justify-end">
        <button @click="confirmSelection" class="bg-neutral text-white px-6 py-2 rounded-full hover:bg-dark transition-colors">
          Confirm
        </button>
      </div>
    </div>
  </div>
</template>
