<script setup>
import CustomerSidebar from '@/Components/CustomerSidebar.vue';
</script>

<template>
  <div class="flex min-h-screen">
    <!-- Sidebar -->
    <CustomerSidebar />

    <!-- Main content -->
    <main class="flex-1 p-8 overflow-y-auto font-rem">
      <slot />
    </main>
  </div>
</template>
