<script setup lang="ts">
import type { HTMLAttributes } from "vue"
import { cn } from "@/lib/utils"

const props = defineProps<{
  class?: HTMLAttributes["class"]
}>()
</script>

<template>
  <main
    data-slot="sidebar-inset"
    :class="cn(
      'bg-background relative flex min-h-0 w-full flex-1 flex-col group-data-[has-background=true]/app-shell:bg-transparent group-data-[has-background=true]/app-shell:supports-[backdrop-filter]:bg-background/18 group-data-[has-background=true]/app-shell:supports-[backdrop-filter]:backdrop-blur-[var(--app-glass-blur)]',
      'md:peer-data-[variant=inset]:m-2 md:peer-data-[variant=inset]:ml-0 md:peer-data-[variant=inset]:rounded-xl md:peer-data-[variant=inset]:shadow-sm md:peer-data-[variant=inset]:peer-data-[state=collapsed]:ml-2',
      props.class,
    )"
  >
    <slot />
  </main>
</template>
