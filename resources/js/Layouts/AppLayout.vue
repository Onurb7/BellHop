<script setup>
import { Link, usePage, router } from '@inertiajs/vue3';
import { Settings, User as UserIcon } from '@lucide/vue';
import { computed } from 'vue';
import SidebarNavGroup from '../Components/SidebarNavGroup.vue';

const page = usePage();
const user = computed(() => page.props.auth.user);
const roleNames = computed(() => (user.value?.roles ?? []).map((role) => role.name));
const isAdmin = computed(() => roleNames.value.some((role) => ['admin', 'super-admin'].includes(role)));
const isStaffOrAdmin = computed(() => roleNames.value.some((role) => ['staff', 'admin', 'super-admin'].includes(role)));
const isDashboardActive = computed(() => page.url.startsWith('/dashboard'));
const isCalendarActive = computed(() => page.url.startsWith('/calendar'));
const isReservationsActive = computed(() => page.url.startsWith('/reservations'));
const isPricingActive = computed(() => page.url.startsWith('/admin/pricing'));
const isPromoCodesActive = computed(() => page.url.startsWith('/admin/promo-codes'));
const isReviewsActive = computed(() => page.url.startsWith('/admin/reviews'));
const flashSuccess = computed(() => page.props.flash?.success);
const flashWarning = computed(() => page.props.flash?.warning);

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
                <Link
                    href="/dashboard"
                    class="block rounded-md px-3 py-2 text-sm font-medium hover:bg-gold-500/10"
                    :class="isDashboardActive ? 'text-gold-700' : 'text-[#1b1b18]'"
                >
                    Dashboard
                </Link>

                <Link
                    v-if="isStaffOrAdmin"
                    href="/calendar"
                    class="block rounded-md px-3 py-2 text-sm font-medium hover:bg-gold-500/10"
                    :class="isCalendarActive ? 'text-gold-700' : 'text-[#1b1b18]'"
                >
                    Calendar
                </Link>

                <Link
                    v-if="isStaffOrAdmin"
                    href="/reservations"
                    class="block rounded-md px-3 py-2 text-sm font-medium hover:bg-gold-500/10"
                    :class="isReservationsActive ? 'text-gold-700' : 'text-[#1b1b18]'"
                >
                    Reservations
                </Link>

                <SidebarNavGroup
                    v-if="isAdmin"
                    label="Rooms & Services"
                    :items="roomsAndServicesItems"
                />

                <Link
                    v-if="isAdmin"
                    href="/admin/pricing"
                    class="block rounded-md px-3 py-2 text-sm font-medium hover:bg-gold-500/10"
                    :class="isPricingActive ? 'text-gold-700' : 'text-[#1b1b18]'"
                >
                    Pricing
                </Link>

                <Link
                    v-if="isAdmin"
                    href="/admin/promo-codes"
                    class="block rounded-md px-3 py-2 text-sm font-medium hover:bg-gold-500/10"
                    :class="isPromoCodesActive ? 'text-gold-700' : 'text-[#1b1b18]'"
                >
                    Promo Codes
                </Link>

                <Link
                    v-if="isAdmin"
                    href="/admin/reviews"
                    class="block rounded-md px-3 py-2 text-sm font-medium hover:bg-gold-500/10"
                    :class="isReviewsActive ? 'text-gold-700' : 'text-[#1b1b18]'"
                >
                    Reviews
                </Link>
            </nav>
        </aside>

        <div class="flex min-w-0 flex-1 flex-col">
            <header class="flex items-center justify-between border-b border-gold-500/20 bg-white px-6 py-4">
                <div>
                    <slot name="header" />
                </div>

                <div class="flex items-center gap-4">
                    <div class="text-right">
                        <p class="text-sm font-medium">{{ user?.name }}</p>
                        <p class="text-xs capitalize text-gold-600">{{ roleNames.join(', ') }}</p>
                    </div>
                    <Link
                        href="/profile"
                        title="Profile"
                        class="rounded-md border border-black/10 p-2 hover:bg-black/5"
                    >
                        <UserIcon class="h-4 w-4" />
                    </Link>
                    <Link
                        href="/settings"
                        title="Settings"
                        class="rounded-md border border-black/10 p-2 hover:bg-black/5"
                    >
                        <Settings class="h-4 w-4" />
                    </Link>
                    <button
                        type="button"
                        @click="logout"
                        class="rounded-md border border-black/10 px-3 py-1.5 text-sm hover:bg-black/5"
                    >
                        Log out
                    </button>
                </div>
            </header>

            <main class="min-w-0 flex-1 p-8">
                <div
                    v-if="flashSuccess"
                    class="mb-6 rounded-md border border-gold-500/30 bg-gold-50 px-4 py-2 text-sm text-gold-700"
                >
                    {{ flashSuccess }}
                </div>

                <div
                    v-if="flashWarning"
                    class="mb-6 rounded-md border border-amber-300 bg-amber-50 px-4 py-2 text-sm text-amber-800"
                >
                    {{ flashWarning }}
                </div>

                <slot />
            </main>
        </div>
    </div>
</template>
