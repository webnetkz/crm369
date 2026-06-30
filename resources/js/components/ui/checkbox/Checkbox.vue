<script setup lang="ts">
import type { CheckboxRootProps } from "reka-ui"
import type { HTMLAttributes } from "vue"
import { computed } from "vue"
import { Check } from "@lucide/vue"
import { CheckboxIndicator, CheckboxRoot } from "reka-ui"
import { cn } from "@/lib/utils"

type CheckboxValue = CheckboxRootProps["modelValue"]

const props = defineProps<
    CheckboxRootProps & {
        checked?: CheckboxValue
        class?: HTMLAttributes["class"]
    }
>()

const emit = defineEmits<{
    (event: "update:modelValue", value: CheckboxValue): void
    (event: "update:checked", value: CheckboxValue): void
}>()

const delegatedProps = computed(() => {
    const { checked, class: _class, modelValue, ...rest } = props

    return {
        ...rest,
        modelValue: checked === undefined ? modelValue : checked,
    }
})

const handleUpdate = (value: CheckboxValue): void => {
    emit("update:modelValue", value)
    emit("update:checked", value)
}
</script>

<template>
  <CheckboxRoot
    v-slot="slotProps"
    data-slot="checkbox"
    v-bind="delegatedProps"
    @update:model-value="handleUpdate"
    :class="
      cn('peer border-input data-[state=checked]:bg-primary data-[state=checked]:text-primary-foreground data-[state=checked]:border-primary focus-visible:border-ring focus-visible:ring-ring/50 aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive size-4 shrink-0 rounded-[4px] border shadow-xs transition-shadow outline-none focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50',
         props.class)"
  >
    <CheckboxIndicator
      data-slot="checkbox-indicator"
      class="grid place-content-center text-current transition-none"
    >
      <slot v-bind="slotProps">
        <Check class="size-3.5" />
      </slot>
    </CheckboxIndicator>
  </CheckboxRoot>
</template>
