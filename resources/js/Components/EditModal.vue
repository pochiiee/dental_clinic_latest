<script setup>
import { watch } from 'vue'
import { useForm } from '@inertiajs/vue3'
import PrimaryButton from '@/Components/PrimaryButton.vue'
import InputLabel from '@/Components/InputLabel.vue'
import TextInput from '@/Components/TextInput.vue'
import InputError from '@/Components/InputError.vue'

const props = defineProps({
  show: Boolean,
  title: String,
  fields: Array,
  routeName: String,
})

const emit = defineEmits(['close'])

// Create initial data object from fields
const getInitialData = () => {
  const data = {}
  props.fields.forEach(field => {
    data[field.name] = field.value || ''
  })
  return data
}

const form = useForm(getInitialData())

// Update form when fields change
watch(
  () => props.fields,
  (newFields) => {
    if (newFields && newFields.length) {
      newFields.forEach((f) => {
        form[f.name] = f.value || ''
      })
    }
  },
  { immediate: true }
)

const submit = () => {
  console.log('Submitting form data:', form.data())

  form.patch(route(props.routeName), {
    preserveScroll: true,
    onSuccess: () => {
      emit('close')
      form.reset()
    },
    onError: (errors) => {
      console.error('Validation errors:', errors)
    },
  })
}
</script>

<template>
  <div
    v-if="show"
    class="fixed inset-0 bg-black/40 flex justify-center items-center z-50"
  >
    <div
      class="bg-white rounded-2xl shadow-lg p-8 w-full max-w-md relative animate-fadeIn"
    >
      <!-- Close Button -->
      <button
        @click="emit('close')"
        class="absolute top-3 right-3 text-gray-500 hover:text-gray-700 text-lg"
      >
        ✕
      </button>

      <!-- Title -->
      <h2 class="text-2xl font-semibold text-cyan-800 mb-6 text-center">
        {{ title }}
      </h2>

      <!-- Form -->
      <form @submit.prevent="submit" class="space-y-4">
        <div v-for="(field, index) in fields" :key="index">
          <InputLabel :for="field.name" :value="field.label" />
          <TextInput
            :id="field.name"
            v-model="form[field.name]"
            class="w-full rounded-xl border-gray-300"
            type="text"
          />
          <InputError :message="form.errors[field.name]" class="mt-2" />
        </div>

        <div class="flex justify-center mt-6">
          <PrimaryButton
            :disabled="form.processing"
            class="bg-dark hover:bg-light text-white px-8 py-1.5 rounded-full"
          >
            Save
          </PrimaryButton>
        </div>
      </form>
    </div>
  </div>
</template>

<style scoped>
@keyframes fadeIn {
  from {
    opacity: 0;
    transform: scale(0.97);
  }
  to {
    opacity: 1;
    transform: scale(1);
  }
}
.animate-fadeIn {
  animation: fadeIn 0.25s ease-in-out;
}
</style>