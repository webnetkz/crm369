<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { home } from '@/routes';

const page = usePage();
</script>

<template>
    <div class="min-h-svh bg-background">
        <header class="border-b border-border/70 bg-background/90">
            <div class="mx-auto flex max-w-7xl items-center px-4 py-4 sm:px-6">
                <Link
                    :href="home()"
                    class="flex items-center gap-3 font-semibold"
                >
                    <div
                        class="flex size-9 items-center justify-center rounded-lg"
                        :class="
                            page.props.portal.logoUrl ? 'bg-white' : 'bg-muted'
                        "
                    >
                        <img
                            v-if="page.props.portal.logoUrl"
                            :src="page.props.portal.logoUrl"
                            :alt="page.props.portal.companyName"
                            class="size-8 object-contain"
                        />
                        <AppLogoIcon
                            v-else
                            class="size-7 fill-current text-foreground"
                        />
                    </div>
                    <span>{{ page.props.portal.companyName }}</span>
                </Link>
            </div>
        </header>

        <main>
            <slot />
        </main>
    </div>
</template>
