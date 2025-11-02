<script setup>
import { ref } from 'vue'
import axios from 'axios'
import { Head } from "@inertiajs/vue3"
import CustomerLayout from '@/Layouts/CustomerLayout.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'
import { useToast } from 'vue-toastification' // ✅ assuming you use Vue Toastification

const toast = useToast()

const form = ref({
    name: '',
    feedback: '',
    image: null
})

const imagePreview = ref(null)
const isSubmitting = ref(false)

// Handle image upload
const handleImageUpload = (event) => {
    const file = event.target.files[0]
    if (file) {
        form.value.image = file

        const reader = new FileReader()
        reader.onload = (e) => {
            imagePreview.value = e.target.result
        }
        reader.readAsDataURL(file)
    }
}

const triggerFileInput = () => document.getElementById('imageUpload').click()

const removeImage = () => {
    form.value.image = null
    imagePreview.value = null
    document.getElementById('imageUpload').value = ''
}

// ✅ Submit feedback to backend with toast
const submit = async () => {
    if (!form.value.feedback.trim()) {
        toast.error('Please write some feedback before submitting.', { timeout: 3000 })
        return
    }

    isSubmitting.value = true

    try {
        const formData = new FormData()
        formData.append('message', form.value.feedback)
        if (form.value.image) formData.append('image', form.value.image)
        if (form.value.name) formData.append('name', form.value.name)

        const res = await axios.post('/feedback', formData, {
            headers: { 'Content-Type': 'multipart/form-data' }
        })

        if (res.data.success) {
            toast.success('Thank you! Your feedback has been submitted.', { timeout: 3000 })
            // Reset form
            form.value.feedback = ''
            form.value.image = null
            form.value.name = ''
            imagePreview.value = null
        } else {
            toast.error(res.data.message || 'Something went wrong.', { timeout: 3000 })
        }
    } catch (error) {
        toast.error(error.response?.data?.message || 'Failed to submit feedback.', { timeout: 3000 })
    } finally {
        isSubmitting.value = false
    }
}
</script>

<template>
    <Head title="Feedback" />
    <CustomerLayout>
        <div class="min-h-screen flex items-center justify-center py-10 px-4 sm:px-6 lg:px-8 font-rem">
            <div class="shadow-xl bg-[#EFEFEF]/20 rounded-2xl p-6 sm:p-8 md:p-10 w-full max-w-5xl">
                
                <h1 class="text-2xl sm:text-3xl font-extrabold text-dark mb-8 sm:mb-10 uppercase text-center md:text-left">
                    Feedback
                </h1>

                <div class="space-y-10">
                    <!-- Header Section -->
                    <section class="text-center">
                        <h2 class="text-3xl sm:text-4xl font-bold text-dark mb-4">We Hear You</h2>
                        <p class="text-lg sm:text-2xl font-semibold">Tell us what you think</p>
                    </section>

                    <!-- Name Field -->
                    <section class="px-2 sm:px-4 md:px-8">
                        <label for="name" class="ml-[92px] block text-base sm:text-lg font-semibold text-dark mb-4">
                            Name (optional)
                        </label>
                        <input
                            type="text"
                            id="name"
                            v-model="form.name"
                            class="w-4/5 px-4 py-3 block mx-auto border-2 border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-dark focus:border-transparent bg-white text-gray-700 text-base transition-all duration-200"
                            placeholder="Your name"
                        />
                    </section>

                    <!-- Feedback Text Area -->
                    <section class="px-2 sm:px-4 md:px-8">
                        <label for="feedback" class="ml-[92px] block text-base sm:text-lg font-semibold text-dark mb-3">
                            Do you have any thoughts you'd like to share?
                        </label>
                        <textarea
                            id="feedback"
                            v-model="form.feedback"
                            rows="8"
                            class="w-4/5 px-4 py-3 block mx-auto border-2 border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-dark focus:border-transparent bg-white text-gray-700 text-base resize-none transition-all duration-200"
                            placeholder="Share your feedback."
                        ></textarea>
                    </section>

                    <!-- Image Upload Section -->
                    <section class="px-2 sm:px-4 md:px-8">
                        <input
                            type="file"
                            id="imageUpload"
                            @change="handleImageUpload"
                            accept="image/*"
                            class="hidden"
                        />
                        
                        <PrimaryButton
                            @click="triggerFileInput"
                            class="bg-white text-dark font-semibold border-2 border-black/50 hover:bg-light text-sm px-6 sm:px-10 py-2 ml-[90px] rounded-full transition-all duration-300 shadow-md"
                        >
                            Upload Image
                        </PrimaryButton>

                        <!-- Image Preview -->
                        <div v-if="imagePreview" class="mt-6 relative inline-block">
                            <img :src="imagePreview" alt="Preview" class="max-w-xs rounded-lg shadow-lg" />
                            <button
                                type="button"
                                @click="removeImage"
                                class="absolute -top-2 -right-2 bg-red-500 text-white rounded-full p-2 hover:bg-red-600 transition-colors shadow-md"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </button>
                        </div>
                    </section>

                    <!-- Submit Button -->
                    <div class="flex justify-center sm:justify-end pt-4 sm:pt-6">
                        <PrimaryButton
                            :disabled="isSubmitting"
                            @click="submit"
                            class="bg-dark hover:bg-light text-white text-sm font-semibold px-8 py-2 rounded-full transition-all duration-300 shadow-md uppercase disabled:bg-gray-400 disabled:cursor-not-allowed"
                        >
                            <span v-if="!isSubmitting">Submit</span>
                            <span v-else>Submitting...</span>
                        </PrimaryButton>
                    </div>
                </div>
            </div>
        </div>
    </CustomerLayout>
</template>
