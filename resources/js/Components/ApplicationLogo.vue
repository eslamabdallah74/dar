<script setup lang="ts">
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        variant?: 'mark' | 'full' | 'horizontal';
        size?: 'sm' | 'md' | 'lg' | 'xl';
        withText?: boolean;
        withSlogan?: boolean;
        dark?: boolean;
        alt?: string;
    }>(),
    {
        variant: 'mark',
        size: 'md',
        withText: false,
        withSlogan: false,
        dark: false,
        alt: 'Nook Mate — اعثر على مساحتك واستأجر ما تحتاج إليه',
    }
);

const iconSizeClass = computed(() => {
    switch (props.size) {
        case 'sm':
            return 'w-8 h-8';
        case 'lg':
            return 'w-14 h-14';
        case 'xl':
            return 'w-20 h-20';
        case 'md':
        default:
            return 'w-10 h-10';
    }
});

const titleSizeClass = computed(() => {
    switch (props.size) {
        case 'sm':
            return 'text-lg';
        case 'lg':
            return 'text-3xl';
        case 'xl':
            return 'text-4xl';
        case 'md':
        default:
            return 'text-2xl';
    }
});
</script>

<template>
    <div class="inline-flex items-center gap-3 select-none" v-bind="$attrs">
        <!-- SVG Nested Boxes Mark -->
        <div
            :class="[
                iconSizeClass,
                'flex items-center justify-center shrink-0 transition-transform duration-200'
            ]"
            :title="alt"
        >
            <svg
                viewBox="0 0 500 500"
                class="w-full h-full drop-shadow-xs"
                fill="none"
                xmlns="http://www.w3.org/2000/svg"
            >
                <!-- Outer House Frame (FLAT #163E3E) -->
                <path
                    fill-rule="evenodd"
                    clip-rule="evenodd"
                    d="M 250 25 C 258 25, 265 29, 271 35 L 442 188 C 450 196, 455 208, 455 220 L 455 412 C 455 442, 430 466, 400 466 L 100 466 C 70 466, 45 442, 45 412 L 45 220 C 45 208, 50 196, 58 188 L 229 35 C 235 29, 242 25, 250 25 Z M 250 102 C 245 102, 240 105, 236 109 L 115 216 C 110 221, 106 228, 106 236 L 106 398 C 106 405, 111 410, 118 410 L 382 410 C 389 410, 394 405, 394 398 L 394 236 C 394 228, 390 221, 385 216 L 264 109 C 260 105, 255 102, 250 102 Z"
                    :fill="dark ? '#1DE9B6' : '#163E3E'"
                />

                <!-- Middle Box Frame (ROOM #F8F4EA) -->
                <rect
                    x="112"
                    y="166"
                    width="276"
                    height="244"
                    rx="44"
                    ry="44"
                    :fill="dark ? '#163E3E' : '#F8F4EA'"
                    :stroke="dark ? '#204E4E' : '#E5DEC9'"
                    stroke-width="2.5"
                />

                <!-- Subtle inner inset frame for Room -->
                <rect
                    x="136"
                    y="190"
                    width="228"
                    height="196"
                    rx="30"
                    ry="30"
                    fill="none"
                    :stroke="dark ? '#255858' : '#ECE4D3'"
                    stroke-width="2"
                />

                <!-- Inner Box Frame (BED #F7C33D) -->
                <path
                    fill-rule="evenodd"
                    clip-rule="evenodd"
                    d="M 215 226 L 285 226 C 322 226, 345 249, 345 286 L 345 320 C 345 357, 322 380, 285 380 L 215 380 C 178 380, 155 357, 155 320 L 155 286 C 155 249, 178 226, 215 226 Z M 228 274 C 222 274, 218 278, 218 284 L 218 322 C 218 328, 222 332, 228 332 L 272 332 C 278 332, 282 328, 282 322 L 282 284 C 282 278, 278 274, 272 274 L 228 274 Z"
                    fill="#F7C33D"
                />
            </svg>
        </div>

        <!-- Typography Lockup (optional or withText) -->
        <div v-if="withText || variant === 'full' || variant === 'horizontal'" class="flex flex-col text-right leading-none">
            <div dir="ltr" class="flex items-center gap-1.5 tracking-tight font-extrabold font-montserrat" :class="titleSizeClass">
                <span :class="dark ? 'text-white' : 'text-[#163E3E]'">Nook</span>
                <span class="text-[#F7C33D]">Mate</span>
            </div>
            <span
                v-if="withSlogan || variant === 'full' || variant === 'horizontal'"
                class="text-[11px] font-medium font-poppins mt-1"
                :class="dark ? 'text-[#F8F4EA]/80' : 'text-[#163E3E]/80'"
            >
                اعثر على مساحتك واستأجر ما تحتاج إليه
            </span>
        </div>
    </div>
</template>
