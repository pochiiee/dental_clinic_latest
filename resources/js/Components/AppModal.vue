<script setup>
import { ref, watch, onUnmounted } from "vue";
import { XMarkIcon } from "@heroicons/vue/24/outline";

const props = defineProps({
  show: Boolean,
});

const emit = defineEmits(["close"]);

const handleKey = (e) => {
  if (e.key === "Escape") emit("close");
};

watch(
  () => props.show,
  (val) => {
    if (val) document.addEventListener("keydown", handleKey);
    else document.removeEventListener("keydown", handleKey);
  }
);

onUnmounted(() => document.removeEventListener("keydown", handleKey));
</script>

<template>
  <transition name="fade">
    <div
      v-if="show"
      class="fixed inset-0 flex items-center justify-center bg-black/50 backdrop-blur-sm z-50"
      @click.self="emit('close')">

      <div
        class="relative bg-white rounded-2xl shadow-xl p-8 
               w-[550px] max-w-[100vw] h-[45vh] 
               flex flex-col justify-start animate-modal-enter">
        <!-- ✖️ Close button -->
        <button
          @click="emit('close')"
          class="absolute top-4 right-4 text-gray-400 hover:text-red-500 transition-transform hover:scale-110"
          aria-label="Close">
          <XMarkIcon class="w-6 h-6" />
        </button>

        <!-- Header -->
        <h2 class="text-2xl font-bold text-dark text-center mb-5">
          Appointment Details
        </h2>

        <!-- slot content -->
        <div class="space-y-4 leading-relaxed text-lg">
          <slot />
        </div>

        <!-- actions slot -->
        <div class="gap-8 mt-8">
          <slot name="actions" />
        </div>
      </div>
    </div>
  </transition>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.25s ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}

/* scale-in animation */
@keyframes modal-enter {
  0% {
    transform: scale(0.95) translateY(15px);
    opacity: 0;
  }
  100% {
    transform: scale(1) translateY(0);
    opacity: 1;
  }
}
.animate-modal-enter {
  animation: modal-enter 0.25s ease-out;
}
</style>
