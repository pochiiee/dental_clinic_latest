<script setup>
import { ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import Profile from '@/Pages/Customer/Profile.vue';

const profile = ref({
  name: 'Juan Dela Cruz',
  email: 'delacruz_juan@gmail.com',
  contactNumber: '09123456789'
});
</script>

<template>
  <Head title="Personal Information" />
  <Profile>
    <h2 class="text-xl sm:text-2xl font-bold text-dark mb-8">
      Personal Information
    </h2>

    <div class="space-y-4">
      <div>
        <p><strong>Name:</strong> {{ profile.name }}</p>
        <p><strong>Email:</strong> {{ profile.email }}</p>
        <p><strong>Contact Number:</strong> {{ profile.contactNumber }}</p>
      </div>
    </div>
  </Profile>
</template>
