<script setup>
import { ref, computed } from 'vue'
import { useForm, usePage, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'
import { EyeIcon, EyeSlashIcon } from '@heroicons/vue/24/outline'
import { useToast } from 'vue-toastification'
import StaffDetailsModal from '@/Components/StaffDetailsModal.vue'

const page = usePage()
const staffList = ref(page.props.staff || [])
const toast = useToast()

const activeTab = ref('creation')
const showPassword = ref(false)
const showConfirmPassword = ref(false)
const showModal = ref(false)
const selectedStaff = ref(null)

// Create Staff
const form = useForm({
  first_name: '',
  last_name: '',
  email: '',
  username: '',
  contact_no: '',
  password: '',
  password_confirmation: ''
})

const handleCreate = () => {
  if (form.password !== form.password_confirmation) {
    toast.error('Passwords do not match!')
    return
  }

  form.post(route('admin.staff.store'), {
    preserveScroll: true,
    onSuccess: () => {
      toast.success('Staff account created successfully!')
      form.reset()
      router.reload({ only: ['staff'] })
    },
    onError: () => {
      toast.error('Error creating staff account. Please check the input fields.')
    }
  })
}

// View Details Modal
function viewDetails(staff) {
  selectedStaff.value = staff
  showModal.value = true
}

function closeModal() {
  showModal.value = false
  selectedStaff.value = null
}

// Active or Deactive Staff
function toggleStaffStatus(staff, action) {
  const isActivate = action === 'activate'

  router.post(
    route('admin.staff.toggle', staff.user_id),
    { active: isActivate },
    {
      onSuccess: () => {
        toast.success(`Staff ${isActivate ? 'activated' : 'deactivated'} successfully!`)
        router.reload({ only: ['staff'] })
        closeModal()
      },
      onError: () => {
        toast.error(`Failed to ${isActivate ? 'activate' : 'deactivate'} staff.`)
      }
    }
  )
}

function activateStaff(staff) {
  toggleStaffStatus(staff, 'activate')
}

function deactivateStaff(staff) {
  toggleStaffStatus(staff, 'deactivate')
}
</script>

<template>
  <AdminLayout>
    <div class="min-h-screen bg-light/30 p-8 font-rem">
      <!-- Header -->
      <div class="max-w-6xl mx-auto mb-8 flex justify-between items-center">
        <h1 class="text-3xl font-bold text-dark">MANAGE STAFF ACCOUNT</h1>
      </div>

      <!-- Tabs -->
      <div class="max-w-6xl mx-auto mb-6">
        <div class="flex items-center bg-white py-1.5 px-12 w-fit rounded-lg border border-dark mt-2">
          <button
            @click="activeTab = 'creation'"
            :class="activeTab === 'creation' ? 'font-bold text-dark' : 'text-gray-500'"
            class="px-4">
            Account Creation
          </button>
          <div class="h-6 border-r-2 border-dark mx-3"></div>
          <button
            @click="activeTab = 'list'"
            :class="activeTab === 'list' ? 'font-bold text-dark' : 'text-gray-500'"
            class="px-4">
            List of Staff
          </button>
        </div>
      </div>

      <!-- Content -->
      <div class="max-w-6xl mx-auto bg-white rounded-lg shadow-lg p-8">
        <div v-if="activeTab === 'creation'">
          <h2 class="text-2xl font-bold text-dark text-center mb-8">
            Account Creation
          </h2>

          <div class="max-w-2xl mx-auto space-y-6">
            <div class="flex items-center gap-4">
              <label class="w-48 font-semibold">First Name</label>
              <input
                v-model="form.first_name"
                type="text"
                class="flex-1 px-4 py-2 border rounded-lg"/>
            </div>

            <div class="flex items-center gap-4">
              <label class="w-48 font-semibold">Last Name</label>
              <input
                v-model="form.last_name"
                type="text"
                class="flex-1 px-4 py-2 border rounded-lg"/>
            </div>

            <div class="flex items-center gap-4">
              <label class="w-48 font-semibold">Email</label>
              <input
                v-model="form.email"
                type="email"
                class="flex-1 px-4 py-2 border rounded-lg"
              />
            </div>

            <div class="flex items-center gap-4">
              <label class="w-48 font-semibold">Contact Number</label>
              <input
                v-model="form.contact_no"
                type="text"
                class="flex-1 px-4 py-2 border rounded-lg"/>
            </div>

            <div class="flex items-center gap-4">
              <label class="w-48 font-semibold">Username</label>
              <input
                v-model="form.username"
                type="text"
                class="flex-1 px-4 py-2 border rounded-lg"/>
            </div>

            <!-- Password -->
            <div class="flex items-center gap-4">
              <label class="w-48 font-semibold">Password</label>
              <div class="flex-1 relative">
                <input
                  v-model="form.password"
                  :type="showPassword ? 'text' : 'password'"
                  class="w-full px-4 py-2 pr-10 border rounded-lg"/>
                <button
                  type="button"
                  @click="showPassword = !showPassword"
                  class="absolute right-3 top-1/2 -translate-y-1/2">
                  <EyeIcon v-if="!showPassword" class="w-5 h-5 text-gray-500" />
                  <EyeSlashIcon v-else class="w-5 h-5 text-gray-500" />
                </button>
              </div>
            </div>

            <!-- Confirm Password -->
            <div class="flex items-center gap-4">
              <label class="w-48 font-semibold">Confirm Password</label>
              <div class="flex-1 relative">
                <input
                  v-model="form.password_confirmation"
                  :type="showConfirmPassword ? 'text' : 'password'"
                  class="w-full px-4 py-2 pr-10 border rounded-lg"/>
                <button
                  type="button"
                  @click="showConfirmPassword = !showConfirmPassword"
                  class="absolute right-3 top-1/2 -translate-y-1/2">
                  <EyeIcon
                    v-if="!showConfirmPassword"
                    class="w-5 h-5 text-gray-500"/>
                  <EyeSlashIcon v-else class="w-5 h-5 text-gray-500" />
                </button>
              </div>
            </div>

            <div class="flex justify-end pt-2">
              <PrimaryButton
                @click="handleCreate"
                :disabled="form.processing"
                class="px-8 py-2.5 bg-dark text-white rounded-lg">
                CREATE
              </PrimaryButton>
            </div>
          </div>
        </div>

        <!-- STAFF LIST TABLE -->
        <div v-if="activeTab === 'list'">
          <h2 class="text-3xl font-bold text-dark text-center mb-8">
            List of Staff
          </h2>

          <div class="overflow-x-auto">
            <table class="w-full border-collapse rounded-2xl">
              <thead>
                <tr class="bg-neutral text-white">
                  <th class="px-6 py-3 text-center">ID</th>
                  <th class="px-6 py-3 text-center">Last Name</th>
                  <th class="px-6 py-3 text-center">First Name</th>
                  <th class="px-6 py-3 text-center">Email</th>
                  <th class="px-6 py-3 text-center">Contact No</th>
                  <th class="px-6 py-3 text-center">Action</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="staff in staffList"
                  :key="staff.user_id"
                  class="hover:bg-gray-50">
                  <td class="px-6 py-3 text-center">{{ staff.user_id }}</td>
                  <td class="px-6 py-3 text-center">{{ staff.last_name }}</td>
                  <td class="px-6 py-3 text-center">{{ staff.first_name }}</td>
                  <td class="px-6 py-3 text-center">{{ staff.email }}</td>
                  <td class="px-6 py-3 text-center">{{ staff.contact_no }}</td>
                  <td class="px-6 py-3 text-center space-x-2">
                    <button
                      @click="viewDetails(staff)"
                      class="underline text-teal-700 hover:text-teal-900">
                      View Details
                    </button>
                  </td>
                </tr>

                <tr v-if="staffList.length === 0">
                  <td colspan="6" class="text-center py-6 text-gray-500">
                    No staff records found.
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <StaffDetailsModal
        :visible="showModal"
        :staff="selectedStaff"
        @close="closeModal"
        @activate="activateStaff"
        @deactivate="deactivateStaff"
      />
    </div>
  </AdminLayout>
</template>
