<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import { Key, Mail, MapPin, Phone, User } from '@lucide/vue';
import AppLayout from '../../Layouts/AppLayout.vue';

const props = defineProps({
    is_guest: Boolean,
    first_name: String,
    last_name: String,
    email: String,
    phone: String,
    address: String,
});

const profileForm = useForm({
    first_name: props.first_name,
    last_name: props.last_name,
    email: props.email,
    phone: props.phone ?? '',
    address: props.address ?? '',
});

const passwordForm = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

function submitProfile() {
    profileForm.put('/profile');
}

function submitPassword() {
    passwordForm.put('/profile/password', {
        onSuccess: () => passwordForm.reset(),
    });
}
</script>

<template>
    <Head title="Profile" />
    <AppLayout>
        <template #header>
            <h1 class="font-serif text-xl">Profile</h1>
        </template>

        <div class="max-w-lg space-y-6">
            <form @submit.prevent="submitProfile" class="space-y-6 rounded-lg border border-gold-500/20 bg-white p-6">
                <h2 class="flex items-center gap-2 font-serif text-lg">
                    <User class="h-5 w-5 text-gold-600" />
                    Profile information
                </h2>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm font-medium">First name</label>
                        <input
                            v-model="profileForm.first_name"
                            type="text"
                            class="w-full rounded-md border border-black/10 px-3 py-2 text-sm focus:border-gold-500 focus:outline-none focus:ring-2 focus:ring-gold-500/30"
                        />
                        <p v-if="profileForm.errors.first_name" class="mt-1 text-sm text-red-600">{{ profileForm.errors.first_name }}</p>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium">Last name</label>
                        <input
                            v-model="profileForm.last_name"
                            type="text"
                            class="w-full rounded-md border border-black/10 px-3 py-2 text-sm focus:border-gold-500 focus:outline-none focus:ring-2 focus:ring-gold-500/30"
                        />
                        <p v-if="profileForm.errors.last_name" class="mt-1 text-sm text-red-600">{{ profileForm.errors.last_name }}</p>
                    </div>
                </div>

                <div>
                    <label class="mb-1 flex items-center gap-1.5 text-sm font-medium">
                        <Mail class="h-3.5 w-3.5 opacity-50" />
                        Email
                    </label>
                    <input
                        v-model="profileForm.email"
                        type="email"
                        class="w-full rounded-md border border-black/10 px-3 py-2 text-sm focus:border-gold-500 focus:outline-none focus:ring-2 focus:ring-gold-500/30"
                    />
                    <p v-if="profileForm.errors.email" class="mt-1 text-sm text-red-600">{{ profileForm.errors.email }}</p>
                </div>

                <template v-if="is_guest">
                    <div>
                        <label class="mb-1 flex items-center gap-1.5 text-sm font-medium">
                            <Phone class="h-3.5 w-3.5 opacity-50" />
                            Phone
                        </label>
                        <input
                            v-model="profileForm.phone"
                            type="tel"
                            class="w-full rounded-md border border-black/10 px-3 py-2 text-sm focus:border-gold-500 focus:outline-none focus:ring-2 focus:ring-gold-500/30"
                        />
                        <p v-if="profileForm.errors.phone" class="mt-1 text-sm text-red-600">{{ profileForm.errors.phone }}</p>
                    </div>

                    <div>
                        <label class="mb-1 flex items-center gap-1.5 text-sm font-medium">
                            <MapPin class="h-3.5 w-3.5 opacity-50" />
                            Address
                        </label>
                        <textarea
                            v-model="profileForm.address"
                            rows="2"
                            class="w-full rounded-md border border-black/10 px-3 py-2 text-sm focus:border-gold-500 focus:outline-none focus:ring-2 focus:ring-gold-500/30"
                        ></textarea>
                        <p class="mt-1 text-xs opacity-50">Used to pre-fill your details when you make a reservation.</p>
                        <p v-if="profileForm.errors.address" class="mt-1 text-sm text-red-600">{{ profileForm.errors.address }}</p>
                    </div>
                </template>

                <div class="flex justify-end gap-3 border-t border-black/5 pt-4">
                    <button
                        type="submit"
                        :disabled="profileForm.processing"
                        class="rounded-md bg-gradient-to-r from-gold-500 to-gold-600 px-5 py-2 text-sm font-medium text-white disabled:opacity-50"
                    >
                        Save changes
                    </button>
                </div>
            </form>

            <form @submit.prevent="submitPassword" class="space-y-6 rounded-lg border border-gold-500/20 bg-white p-6">
                <h2 class="flex items-center gap-2 font-serif text-lg">
                    <Key class="h-5 w-5 text-gold-600" />
                    Update password
                </h2>

                <div>
                    <label class="mb-1 block text-sm font-medium">Current password</label>
                    <input
                        v-model="passwordForm.current_password"
                        type="password"
                        autocomplete="current-password"
                        class="w-full rounded-md border border-black/10 px-3 py-2 text-sm focus:border-gold-500 focus:outline-none focus:ring-2 focus:ring-gold-500/30"
                    />
                    <p v-if="passwordForm.errors.current_password" class="mt-1 text-sm text-red-600">{{ passwordForm.errors.current_password }}</p>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium">New password</label>
                    <input
                        v-model="passwordForm.password"
                        type="password"
                        autocomplete="new-password"
                        class="w-full rounded-md border border-black/10 px-3 py-2 text-sm focus:border-gold-500 focus:outline-none focus:ring-2 focus:ring-gold-500/30"
                    />
                    <p v-if="passwordForm.errors.password" class="mt-1 text-sm text-red-600">{{ passwordForm.errors.password }}</p>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium">Confirm new password</label>
                    <input
                        v-model="passwordForm.password_confirmation"
                        type="password"
                        autocomplete="new-password"
                        class="w-full rounded-md border border-black/10 px-3 py-2 text-sm focus:border-gold-500 focus:outline-none focus:ring-2 focus:ring-gold-500/30"
                    />
                </div>

                <div class="flex justify-end gap-3 border-t border-black/5 pt-4">
                    <button
                        type="submit"
                        :disabled="passwordForm.processing"
                        class="rounded-md bg-gradient-to-r from-gold-500 to-gold-600 px-5 py-2 text-sm font-medium text-white disabled:opacity-50"
                    >
                        Update password
                    </button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
