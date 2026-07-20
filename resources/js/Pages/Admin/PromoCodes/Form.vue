<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AppLayout from '../../../Layouts/AppLayout.vue';
import DatePicker from '../../../Components/DatePicker.vue';

const props = defineProps({
    promoCode: Object,
    services: Array,
});

const isEditing = !!props.promoCode;

const form = useForm({
    code: props.promoCode?.code ?? '',
    description: props.promoCode?.description ?? '',
    percentage: props.promoCode?.percentage ?? 10,
    service_ids: props.promoCode?.service_ids ?? [],
    max_uses: props.promoCode?.max_uses ?? '',
    expires_at: props.promoCode?.expires_at ?? '',
    active: props.promoCode?.active ?? true,
});

function submit() {
    if (isEditing) {
        form.transform((data) => ({ ...data, _method: 'put' })).post(`/admin/promo-codes/${props.promoCode.id}`);
    } else {
        form.post('/admin/promo-codes');
    }
}
</script>

<template>
    <Head :title="isEditing ? 'Edit Promo Code' : 'New Promo Code'" />
    <AppLayout>
        <template #header>
            <h1 class="font-serif text-xl">{{ isEditing ? `Edit ${promoCode.code}` : 'New Promo Code' }}</h1>
        </template>

        <form @submit.prevent="submit" class="max-w-lg space-y-6 rounded-lg border border-gold-500/20 bg-white p-6">
            <div>
                <label class="mb-1 block text-sm font-medium">Code</label>
                <input
                    v-model="form.code"
                    type="text"
                    placeholder="e.g. SUMMER10"
                    class="w-full rounded-md border border-black/10 px-3 py-2 text-sm uppercase focus:border-gold-500 focus:outline-none focus:ring-2 focus:ring-gold-500/30"
                />
                <p v-if="form.errors.code" class="mt-1 text-sm text-red-600">{{ form.errors.code }}</p>
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium">Description</label>
                <textarea
                    v-model="form.description"
                    rows="2"
                    placeholder="e.g. 10% off during the whole stay in summer months"
                    class="w-full rounded-md border border-black/10 px-3 py-2 text-sm focus:border-gold-500 focus:outline-none focus:ring-2 focus:ring-gold-500/30"
                ></textarea>
                <p class="mt-1 text-xs opacity-50">Shown to guests when they apply this code. Optional — falls back to a generic "{{ form.percentage }}% off" message.</p>
                <p v-if="form.errors.description" class="mt-1 text-sm text-red-600">{{ form.errors.description }}</p>
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium">Discount (%)</label>
                <input
                    v-model.number="form.percentage"
                    type="number"
                    min="1"
                    max="100"
                    class="w-full rounded-md border border-black/10 px-3 py-2 text-sm focus:border-gold-500 focus:outline-none focus:ring-2 focus:ring-gold-500/30"
                />
                <p v-if="form.errors.percentage" class="mt-1 text-sm text-red-600">{{ form.errors.percentage }}</p>
            </div>

            <div>
                <label class="mb-2 block text-sm font-medium">Scope to specific services</label>
                <div class="flex flex-wrap gap-2">
                    <label
                        v-for="service in services"
                        :key="service.id"
                        class="flex items-center gap-1.5 rounded-md border border-black/10 px-3 py-1.5 text-sm"
                    >
                        <input type="checkbox" :value="service.id" v-model="form.service_ids" class="accent-gold-500" />
                        {{ service.name }}
                    </label>
                </div>
                <p class="mt-1 text-xs opacity-50">Leave everything unchecked for the discount to apply to the room charge instead.</p>
                <p v-if="form.errors.service_ids" class="mt-1 text-sm text-red-600">{{ form.errors.service_ids }}</p>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="mb-1 block text-sm font-medium">Max uses</label>
                    <input
                        v-model="form.max_uses"
                        type="number"
                        min="1"
                        placeholder="Unlimited"
                        class="w-full rounded-md border border-black/10 px-3 py-2 text-sm focus:border-gold-500 focus:outline-none focus:ring-2 focus:ring-gold-500/30"
                    />
                    <p class="mt-1 text-xs opacity-50">Leave blank for unlimited.</p>
                    <p v-if="form.errors.max_uses" class="mt-1 text-sm text-red-600">{{ form.errors.max_uses }}</p>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">Expires</label>
                    <DatePicker v-model="form.expires_at" placeholder="Never" class="w-full" />
                    <p v-if="form.errors.expires_at" class="mt-1 text-sm text-red-600">{{ form.errors.expires_at }}</p>
                </div>
            </div>

            <label class="flex items-center gap-2 text-sm">
                <input v-model="form.active" type="checkbox" class="accent-gold-500" />
                Active
            </label>

            <div class="flex justify-end gap-3 border-t border-black/5 pt-4">
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="rounded-md bg-gradient-to-r from-gold-500 to-gold-600 px-5 py-2 text-sm font-medium text-white disabled:opacity-50"
                >
                    {{ isEditing ? 'Save changes' : 'Create code' }}
                </button>
            </div>
        </form>
    </AppLayout>
</template>
