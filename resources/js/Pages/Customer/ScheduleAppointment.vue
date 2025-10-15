<script setup>
import { ref } from 'vue';
import { Head } from "@inertiajs/vue3"
import CustomerLayout from '@/Layouts/CustomerLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import DateTimeModal from '@/Components/DateTimeModal.vue';

const form = ref({
  firstName: 'Juan',
  lastName: 'Dela Cruz',
  email: 'delacruz_juan@gmail.com',
  contactNumber: '09123456789',
  service: '',
  date: '',
  dateTime: '',
  timeLabel: ''
});

const services = [
  'General Checkup',
  'Teeth Cleaning',
  'Teeth Whitening',
  'Dental Filling',
  'Root Canal',
  'Tooth Extraction',
  'Dental Crown',
  'Orthodontics/Braces',
  'Dental Implants'
];

const showSlotPicker = ref(false);

const chooseSlots = () => {
  if (!form.value.service || form.value.service === 'Select') {
    alert('Please select a dental service first');
    return;
  }
  showSlotPicker.value = true;
};

const submitAppointment = () => {
  if (!form.value.service || form.value.service === 'Select') {
    alert('Please select a dental service');
    return;
  }
  if (!form.value.dateTime) {
    alert('Please choose an available time slot');
    return;
  }

  console.log('Submitting appointment:', form.value);
  alert('Appointment submitted successfully!');
};
</script>

<template>
    <Head title="Schedule Appointment" />
  <CustomerLayout>
    <div class="min-h-screen flex items-center justify-center py-10 px-4 sm:px-6 lg:px-8 font-rem">
      <div class="shadow-xl bg-[#EFEFEF]/20 rounded-2xl p-6 sm:p-8 md:p-10 w-full max-w-5xl">
   
        <h1 class="text-2xl sm:text-3xl font-extrabold text-dark mb-8 sm:mb-10 uppercase text-center md:text-left">
          Schedule Appointment
        </h1>

        <div class="space-y-10">
          <!-- Personal Information Section -->
          <section class="px-2 sm:px-4 md:px-8">
            <h2 class="text-lg sm:text-xl font-bold text-dark mb-6">Personal Information</h2>
            <div
              class="grid grid-cols-1 sm:grid-cols-2 gap-x-8 sm:gap-x-16 gap-y-4 sm:gap-y-6"
            >
              <div>
                <p class="text-base sm:text-lg">
                  <span class="font-semibold">First Name:</span>
                  <span class="ml-2 break-all">{{ form.firstName }}</span>
                </p>
              </div>

              <div>
                <p class="text-base sm:text-lg">
                  <span class="font-semibold">Email:</span>
                  <span class="ml-2 break-all">{{ form.email }}</span>
                </p>
              </div>

              <div>
                <p class="text-base sm:text-lg">
                  <span class="font-semibold">Last Name:</span>
                  <span class="ml-2">{{ form.lastName }}</span>
                </p>
              </div>

              <div>
                <p class="text-base sm:text-lg">
                  <span class="font-semibold">Contact Number:</span>
                  <span class="ml-2">{{ form.contactNumber }}</span>
                </p>
              </div>
            </div>
          </section>

          <!-- Dental Service Section -->
          <section class="px-4 py-10 sm:px-4 md:px-8">
            <h2 class="text-lg sm:text-xl font-bold text-dark mb-4">Dental Service</h2>
            <div class="relative w-full">
              <select
                v-model="form.service"
                class="w-full px-4 py-3 border-2 border-dark rounded-lg focus:outline-none focus:ring-2 focus:ring-dark focus:border-transparent bg-white cursor-pointer text-gray-700 text-base appearance-none transition-all duration-200"
                style="background-image: url('/icons/arrow.svg'); background-repeat: no-repeat; background-position: right 1rem center; background-size: 3em; padding-right: 2.5rem;"
              >
                <option disabled value="">Select</option>
                <option
                  v-for="service in services"
                  :key="service"
                  :value="service"
                  class="text-gray-700 bg-white hover:bg-neutral focus:bg-neutral"
                >
                  {{ service }}
                </option>
              </select>
            </div>
          </section>

          <!-- Date and Time Section -->
          <section class="px-2 sm:px-4 md:px-8">
            <h2 class="text-lg sm:text-xl font-bold text-dark mb-4">Date and Time</h2>
            <PrimaryButton
              @click="chooseSlots"
              class="bg-neutral hover:bg-dark text-sm px-6 sm:px-8 py-2 rounded-full transition-all duration-300 shadow-md"
            >
              Choose Available Slots
            </PrimaryButton>
          </section>

          <!-- Submit Button -->
          <div class="flex justify-center sm:justify-end pt-4 sm:pt-6">
            <PrimaryButton
              @click="submitAppointment"
              class="bg-dark hover:bg-light text-white text-sm font-semibold px-8 py-2 rounded-full transition-all duration-300 shadow-md uppercase"
            >
              Submit
            </PrimaryButton>
          </div>
        </div>
      </div>
    </div>
  </CustomerLayout>

  <DateTimeModal
    v-model="showSlotPicker"
    v-model:selectedDate="form.date"
    v-model:selectedTime="form.dateTime"
    v-model:selectedTimeLabel="form.timeLabel"
  />
</template>
