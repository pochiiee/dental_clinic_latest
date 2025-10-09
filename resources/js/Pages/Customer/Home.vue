<script setup>
import PrimaryButton from '@/Components/PrimaryButton.vue';
import CustomerLayout from '@/Layouts/CustomerLayout.vue';
</script>

<template>
  <CustomerLayout>
    <div class="min-h-screen  flex items-center justify-center py-10 px-6">
      <div class="shadow-xl bg-[#EFEFEF]/20 rounded-2xl p-10 w-full max-w-5xl flex flex-col justify-center">

        <h1 class="text-3xl font-extrabold text-dark mb-10 uppercase ">
          HOME
        </h1>

        <div class="flex flex-col lg:flex-row items-center justify-center gap-10 w-full">
          <!-- Image -->
          <div class="flex justify-center w-full lg:w-1/2">
            <img
              v-lazy="'/images/customer_homepage.jpg'"
              alt="Dental Clinic"
              class="rounded-2xl shadow-lg w-full max-w-md object-cover"
            />
          </div>

          <div class="flex flex-col items-center text-center w-full lg:w-1/2 mb-12 mx-6">
            <h2 class="text-3xl font-semibold text-dark mb-12">
              District Smiles Dental Center welcomes you!
            </h2>
            <p class="text-lg text-gray-700 mb-8 leading-relaxed max-w-md">
              Let’s create a healthy, confident smile together. Schedule your appointment today
              your comfort and well-being are our top priority.
            </p>
            <PrimaryButton
              class=" bg-dark hover:bg-light text-white text-lg font-semibold w-44 py-3 rounded-full transition-all duration-300 shadow-md">
              BOOK NOW
            </PrimaryButton>
          </div>
        </div>
        <div class="mt-20 items-center text-center justify-center">  
          <h2 class="font-bold text-2xl text-dark">See your past visit here</h2>
          <PrimaryButton class=" bg-dark hover:bg-light text-white text-lg font-semibold w-54 py-4 rounded-full transition-all duration-300 shadow-md mt-4">
            Appointment History
          </PrimaryButton>
        </div>
      </div>
    </div>
  </CustomerLayout>
</template>
