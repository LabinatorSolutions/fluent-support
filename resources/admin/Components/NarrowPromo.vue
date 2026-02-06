<template>
    <div class="fs_narrow_promo" :class="customClass">
        <img v-if="imageSrc" :src="imageSrc" :alt="heading" />
        <h3 v-if="heading">{{ heading }}</h3>
        <p v-if="description">{{ description }}</p>
        <a
            v-if="buttonText"
            :href="buttonUrl"
            :target="buttonTarget"
            :rel="buttonRel"
            :class="buttonClass"
        >
            {{ buttonText }}
        </a>
        <slot></slot>
    </div>
</template>

<script type="text/babel">
export default {
    name: 'NarrowPromo',
    props: {
        heading: {
            type: String,
            default: ''
        },
        description: {
            type: String,
            default: ''
        },
        buttonText: {
            type: String,
            default: ''
        },
        buttonUrl: {
            type: String,
            default: 'https://fluentsupport.com'
        },
        buttonTarget: {
            type: String,
            default: '_blank'
        },
        buttonRel: {
            type: String,
            default: 'noopener'
        },
        buttonClass: {
            type: String,
            default: 'el-button fs_filled_btn'
        },
        imageSrc: {
            type: String,
            default: ''
        },
        customClass: {
            type: String,
            default: ''
        }
    }
};
</script>

