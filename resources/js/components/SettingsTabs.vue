<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { useLanguage } from '@/composables/useLanguage';
import { useSettingsNavigation } from '@/composables/useSettingsNavigation';

const { isCurrentUrl } = useCurrentUrl();
const { t } = useLanguage();
const items = useSettingsNavigation();
</script>

<template>
    <nav
        class="overflow-x-auto border-b border-border/70"
        :aria-label="t.common.settings"
    >
        <div class="flex min-w-max gap-2">
            <Link
                v-for="item in items"
                :key="item.key ?? item.title"
                :href="item.href"
                class="rounded-t-lg border border-b-0 px-4 py-2 text-sm font-medium transition-colors"
                :class="
                    isCurrentUrl(item.href)
                        ? 'border-border bg-background text-foreground shadow-xs'
                        : 'border-transparent text-muted-foreground hover:border-border/60 hover:bg-muted/60 hover:text-foreground'
                "
                prefetch
            >
                {{ item.title }}
            </Link>
        </div>
    </nav>
</template>
