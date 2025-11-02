<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { ref, computed } from "vue";
import { MagnifyingGlassIcon } from "@heroicons/vue/24/outline";

const props = defineProps({
  payments: Array,
});

const search = ref("");

const filteredPayments = computed(() => {
  return props.payments.filter((p) => {
    const text = `
      ${p.last_name}
      ${p.first_name}
      ${p.procedure}
      ${p.amount}
      ${p.status}
      ${p.paid_at}
    `.toLowerCase();

    return text.includes(search.value.toLowerCase());
  });
});

</script>

<template>
  <AdminLayout>
    <div class="min-h-screen p-8 font-rem">

      <!-- Header -->
      <div class="flex justify-between items-center mb-6">
        <h2 class="font-bold text-4xl text-teal-700 tracking-wide">
          PAYMENTS
        </h2>

      </div>

      <!-- Search -->
      <div class="mb-4 relative w-64">
        <MagnifyingGlassIcon class="absolute left-3 top-2.5 h-5 w-5 text-gray-400" />
        <input
          v-model="search"
          type="text"
          placeholder="Search"
          class="pl-10 pr-3 py-2 border border-gray-300 rounded-md w-full text-sm focus:ring-2 focus:ring-neutral"
        />
      </div>

      <!-- Table -->
      <div class="bg-white p-6 rounded-xl shadow-md border border-gray-200">
        <div class="overflow-auto max-h-[600px]">

          <!-- Rounded Wrapper for Table -->
          <div class="overflow-hidden rounded-xl border border-gray-200">
            <table class="w-full table-auto text-sm border-collapse">
              <thead class="bg-neutral text-white text-xs uppercase">
                <tr>
                  <th class="border px-3 py-2">#</th>
                  <th class="border px-3 py-2">Last Name</th>
                  <th class="border px-3 py-2">First Name</th>
                  <th class="border px-3 py-2">Procedure</th>
                  <th class="border px-3 py-2">Amount</th>
                  <th class="border px-3 py-2">Status</th>
                  <th class="border px-3 py-2">Paid At</th>
                </tr>
              </thead>

              <tbody>
                <tr
                  v-for="(p, i) in filteredPayments"
                  :key="i"
                  class="hover:bg-gray-50 text-center"
                >
                  <td class="border px-3 py-4 text-center font-semibold">{{ i + 1 }}</td>
                  <td class="border px-3 py-4">{{ p.last_name }}</td>
                  <td class="border px-3 py-4">{{ p.first_name }}</td>
                  <td class="border px-3 py-4 max-w-[250px] whitespace-normal">{{ p.procedure }}</td>
                  <td class="border px-3 py-4 ">{{ p.amount }}</td>
                 <td class="border px-3 py-4 text-green-600 font-medium">{{ p.status }}</td>
                  <td class="border px-3 py-4">{{ p.paid_at }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

    </div>
  </AdminLayout>
</template>
