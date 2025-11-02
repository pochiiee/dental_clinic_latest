<script setup>
import { ref, watch } from 'vue'
import { usePage, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import FeedbackModal from '@/Components/FeedbackModal.vue'
import { ChevronDownIcon } from '@heroicons/vue/24/outline'

// Get backend data
const { props } = usePage()
const feedbackData = ref(props.feedbacks ?? [])
const selectedFilter = ref(props.filters?.current ?? 'All')

// Modal state
const showModal = ref(false)
const selectedFeedback = ref({})

// Watch for filter change → reload data from backend
watch(selectedFilter, (newFilter) => {
  router.get(
    route('admin.feedback.index'),
    { filter: newFilter },
    { preserveScroll: true, preserveState: true }
  )
})

// Open modal
const handleViewFeedback = (feedback) => {
  selectedFeedback.value = feedback
  showModal.value = true
}

// Close modal
const handleCloseModal = () => {
  showModal.value = false
  selectedFeedback.value = {}
}

// Count images - returns 1 if image_url exists, 0 if not
const countImages = (imageUrl) => {
  return imageUrl ? 1 : 0
}
</script>

<template>
  <AdminLayout>
    <div class="min-h-screen p-8">
      <!-- Header -->
      <div class="max-w-7xl mx-auto mb-8">
        <div class="flex justify-between items-center">
          <h1 class="text-3xl font-bold text-dark">FEEDBACK</h1>
        </div>
      </div>

      <!-- Filter Dropdown -->
      <div class="max-w-7xl mx-auto mb-6">
        <div class="relative inline-block w-64">
          <select
            v-model="selectedFilter"
            class="w-full px-4 py-2 pr-10 bg-white border border-gray-300 rounded-lg appearance-none focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-transparent cursor-pointer"
          >
            <option value="All">All</option>
            <option value="Today">Today</option>
            <option value="This Week">This Week</option>
            <option value="This Month">This Month</option>
          </select>
          <ChevronDownIcon
            class="absolute right-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-500 pointer-events-none"
          />
        </div>
      </div>

      <!-- Feedback Content -->
      <div class="max-w-7xl mx-auto">
        <div class="bg-white rounded-lg shadow-lg p-8">
          <template v-if="feedbackData.length > 0">
            <!-- Group by date -->
            <div
              v-for="group in feedbackData"
              :key="group.date"
              class="mb-8 last:mb-0"
            >
              <h2 class="text-xl font-bold text-gray-800 mb-6">
                {{ group.date }}
              </h2>

              <!-- Cards -->
              <div
                class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-8"
              >
                <div
                  v-for="item in group.items"
                  :key="item.id"
                  class="bg-teal-50 rounded-lg p-5 hover:shadow-md transition-shadow cursor-pointer"
                  @click="handleViewFeedback(item)"
                >
                  <div class="mb-3">
                    <p class="text-sm font-semibold text-gray-700 mb-1">Name:</p>
                    <p class="text-base text-gray-900">{{ item.name }}</p>
                  </div>

                  <div class="mb-3">
                    <p class="text-sm font-semibold text-gray-700 mb-1">
                      Feedback:
                    </p>
                    <p class="text-sm text-gray-800 truncate">
                      {{ item.feedback }}
                    </p>
                  </div>

                  <div>
                    <p class="text-sm text-gray-700">
                      <span class="font-semibold">Uploaded Images:</span>
                      <span 
                        :class="[
                          'ml-2 px-2 py-1 rounded-full text-xs font-medium',
                          countImages(item.image_url) > 0 
                        ]"
                      >
                        {{ countImages(item.image_url) }}
                      </span>
                    </p>
                  </div>
                </div>
              </div>
            </div>
          </template>

          <template v-else>
            <p class="text-center text-gray-500">
              No feedbacks found for this filter.
            </p>
          </template>
        </div>
      </div>
    </div>

    <!-- Feedback Modal -->
    <FeedbackModal
      :show="showModal"
      :feedback="selectedFeedback"
      @close="handleCloseModal"
    />
  </AdminLayout>
</template>

<style scoped>
::-webkit-scrollbar {
  width: 8px;
  height: 8px;
}
::-webkit-scrollbar-track {
  background: #f1f1f1;
  border-radius: 4px;
}
::-webkit-scrollbar-thumb {
  background: #14b8a6;
  border-radius: 4px;
}
::-webkit-scrollbar-thumb:hover {
  background: #0f766e;
}
</style>