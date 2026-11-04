<template>
    <section class="fs_pro_showcase" :aria-labelledby="headingId">
        <div class="fs_pro_showcase_hero" :class="{ fs_pro_showcase_hero_single: !$slots.preview }">
            <div class="fs_pro_showcase_copy">
                <div v-if="!hideEyebrow" class="fs_pro_showcase_eyebrow">
                    <span class="fs_pro_badge">{{ $t('Pro') }}</span>
                    <span v-if="feature">{{ feature }}</span>
                </div>
                <h4 :id="headingId" class="fs_pro_showcase_headline">{{ heading }}</h4>
                <p v-if="description" class="fs_pro_showcase_support">{{ description }}</p>
                <ul v-if="specifics.length" class="fs_pro_showcase_specifics">
                    <li v-for="(item, index) in specifics" :key="index">
                        <svg width="18" height="18" viewBox="0 0 18 18" fill="none" aria-hidden="true">
                            <path d="M3.5 9.5l3.5 3.5 7.5-8" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        <span>{{ item }}</span>
                    </li>
                </ul>
                <div class="fs_pro_showcase_actions">
                    <a class="el-button fs_filled_btn" :href="$upgradeUrl(utmContent)" target="_blank" rel="noopener">
                        {{ $t('Upgrade to Pro') }}
                        <span class="screen-reader-text">{{ $t('(opens in a new tab)') }}</span>
                    </a>
                    <a v-if="docsUrl" class="el-button fs_outline_btn" :href="docsUrl" target="_blank" rel="noopener">
                        {{ $t('See how it works') }}
                        <span class="screen-reader-text">{{ $t('(opens in a new tab)') }}</span>
                    </a>
                </div>
            </div>
            <div v-if="$slots.preview" class="fs_pro_showcase_stage" aria-hidden="true">
                <slot name="preview"></slot>
            </div>
        </div>
    </section>
</template>

<script type="text/babel">
export default {
    name: 'ProShowcase',
    props: {
        // Feature name shown next to the Pro badge, e.g. "Ticket Tags".
        feature: {
            type: String,
            default: ''
        },
        // Hide the Pro badge line when the container already shows it (e.g. a dialog title).
        hideEyebrow: {
            type: Boolean,
            default: false
        },
        heading: {
            type: String,
            required: true
        },
        description: {
            type: String,
            default: ''
        },
        // Short benefit lines rendered as a checklist.
        specifics: {
            type: Array,
            default: () => []
        },
        // Placement identifier for the upgrade link's utm_content, e.g. "feature_lock_ticket_tags".
        utmContent: {
            type: String,
            required: true
        },
        // Feature docs for "See how it works"; the button is hidden without it.
        docsUrl: {
            type: String,
            default: ''
        }
    },
    computed: {
        headingId() {
            return 'fs_pro_showcase_heading_' + this.$.uid;
        }
    }
};
</script>
