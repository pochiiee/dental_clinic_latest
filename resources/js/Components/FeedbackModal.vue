<script setup>
import { ref } from 'vue'
import { XMarkIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  show: {
    type: Boolean,
    default: false
  },
  feedback: {
    type: Object,
    default: () => ({})
  }
})

const emit = defineEmits(['close'])

const imageLoadError = ref(false)

// Image URL generation
const getImageUrl = (imagePath) => {
  if (!imagePath) return null;
  const filename = imagePath.split('/').pop();
  return route('admin.feedback.image', { filename: filename });
};

const getImageUrlDirect = (imagePath) => {
  if (!imagePath) return null;
  const filename = imagePath.split('/').pop();
  return `/admin/feedback-image/${filename}`;
};

const handleImageError = (event) => {
  console.error('Image failed to load:', event.target.src)
  imageLoadError.value = true
  event.target.style.display = 'none'
}

const handleClose = () => {
  imageLoadError.value = false
  emit('close')
}
</script>

<template>
  <transition name="fade">
    <div
      v-if="show"
      class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4"
      @click="handleClose">
      <div
        class="bg-white rounded-xl shadow-2xl w-full max-w-xl max-h-[90vh] overflow-y-auto relative"
        @click.stop>
        <!-- Header with Close Button -->
        <div class="sticky top-0 bg-white border-b border-gray-200 px-6 py-4 flex items-center justify-between">
          <h2 class="text-xl font-bold text-teal-700">
            Patient Feedback
          </h2>
          <button
            @click="handleClose"
            class="text-gray-400 hover:text-gray-600 transition-colors p-1 rounded-lg hover:bg-gray-100">
            <XMarkIcon class="w-6 h-6" />
          </button>
        </div>

        <!-- Content -->
        <div class="px-6 py-6 space-y-6">
          <!-- Name -->
          <div>
            <label class="block text-sm font-semibold text-gray-900 mb-2">
              Name:
            </label>
            <p class="text-base text-gray-700">
              {{ feedback.name || 'N/A' }}
            </p>
          </div>

          <!-- Feedback -->
          <div>
            <label class="block text-sm font-semibold text-gray-900 mb-2">
              Feedback:
            </label>
            <p class="text-base text-gray-700 whitespace-pre-wrap">
              {{ feedback.feedback || 'No feedback provided' }}
            </p>
          </div>

          <!-- Uploaded Images -->
          <div>
            <label class="block text-sm font-semibold text-gray-900 mb-3">
              Uploaded Image: {{ feedback.image_url ? '1' : '0' }}
            </label>
            
            <div v-if="feedback.image_url" class="space-y-4">
              <div v-if="imageLoadError" class="text-center border-2 border-dashed border-gray-300 rounded-lg p-8 bg-gray-50">
                <div class="text-gray-500 text-sm space-y-2">
                  <p class="text-lg">⚠️ Image cannot be displayed</p>
                  <p>The image file may not exist or is inaccessible</p>
                  <a 
                    :href="getImageUrlDirect(feedback.image_url)" 
                    target="_blank" 
                    class="inline-block bg-teal-600 hover:bg-teal-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors mt-2">
                    Try opening image directly
                  </a>
                </div>
              </div>
              
              <div v-else class="grid grid-cols-3 gap-4">
                <!-- Display image (repeated 3 times for multiple images effect as shown in design) -->
                <div 
                  v-for="i in (feedback.uploadedImages || 1)" 
                  :key="i"
                  class="aspect-square bg-gray-200 rounded-lg overflow-hidden">
                  <img
                    :src="getImageUrl(feedback.image_url)"
                    @error="handleImageError"
                    alt="Feedback Image"
                    class="w-full h-full object-cover"
                  />
                </div>
              </div>
            </div>
            
            <div v-else class="grid grid-cols-3 gap-4">
              <div 
                v-for="i in 3" 
                :key="i"
                class="aspect-square bg-gray-200 rounded-lg">
              </div>
            </div>
          </div>

        </div>
      </div>
    </div>
  </transition>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.3s;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}

/* Custom scrollbar */
::-webkit-scrollbar {
  width: 8px;
}

::-webkit-scrollbar-track {
  background: #f1f1f1;
}

::-webkit-scrollbar-thumb {
  background: #14b8a6;
  border-radius: 4px;
}

::-webkit-scrollbar-thumb:hover {
  background: #0f766e;
}
</style>