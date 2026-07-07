<template>
    <div
        class="min-h-screen bg-[radial-gradient(circle_at_top,_color-mix(in_oklab,var(--primary)_16%,transparent),transparent_34%),linear-gradient(180deg,color-mix(in_oklab,var(--background)_92%,white)_0%,var(--background)_100%)]"
    >
        <div
            class="mx-auto min-h-screen w-full max-w-[1600px] px-4 py-4 sm:px-6 lg:px-8"
        >
            <slot />
        </div>
    </div>
</template>
