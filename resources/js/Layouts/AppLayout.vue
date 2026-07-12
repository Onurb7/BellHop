<script setup>
import { Link, usePage, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import SidebarNavGroup from '../Components/SidebarNavGroup.vue';

const page = usePage();
const user = computed(() => page.props.auth.user);
const roleNames = computed(() => (user.value?.roles ?? []).map((role) => role.name));
const isAdmin = computed(() => roleNames.value.some((role) => ['admin', 'super-admin'].includes(role)));
const flashSuccess = computed(() => page.props.flash?.success);

const roomsAndServicesItems = [
    { label: 'Room Types', href: '/admin/room-types' },
    { label: 'Rooms', href: '/admin/rooms' },
    { label: 'Services', href: '/admin/services' },
];

function logout() {
    router.post('/logout');
}
</script>

<template>
    <div class="flex min-h-screen bg-[#FAF8F3] text-[#1b1b18]">
        <aside class="w-64 shrink-0 border-r border-gold-500/20 bg-white">
            <div class="border-b border-gold-500/10 px-6 py-5">
                <Link href="/dashboard" class="font-serif text-lg">🛎️ Bellhop</Link>
            </div>

            <nav class="space-y-1 p-4">
                <SidebarNavGroup
                    v-if="isAdmin"
                    label="Rooms & Services"
                    :items="roomsAndServicesItems"
                />
            </nav>
        </aside>

        <div class="flex flex-1 flex-col">
            <header class="flex items-center justify-between border-b border-gold-500/20 bg-white px-6 py-4">
                <div>
                    <slot name="header" />
                </div>

                <div class="flex items-center gap-4">
                    <div class="text-right">
                        <p class="text-sm font-medium">{{ user?.name }}</p>
                        <p class="text-xs capitalize text-gold-600">{{ roleNames.join(', ') }}</p>
                    </div>
                    <button
                        type="button"
                        @click="logout"
                        class="rounded-md border border-black/10 px-3 py-1.5 text-sm hover:bg-black/5"
                    >
                        Log out
                    </button>
                </div>
            </header>

            <main class="flex-1 p-8">
                <div
                    v-if="flashSuccess"
                    class="mb-6 rounded-md border border-gold-500/30 bg-gold-50 px-4 py-2 text-sm text-gold-700"
                >
                    {{ flashSuccess }}
                </div>

                <slot />
            </main>
        </div>
    </div>
</template>
