<template>
    <div class="fs_structured_widget">
        <div v-if="badges.length" class="fs_sw_badges">
            <el-tag
                v-for="(badge, badgeIndex) in badges"
                :key="badgeIndex"
                size="small"
                :type="badgeType(badge.type)"
            >{{ badge.text }}</el-tag>
        </div>

        <div v-if="stats.length && isSummary" class="fs_sw_summary">
            <div class="fs_sw_summary_hero">
                <a
                    v-if="safeUrl(stats[0].url)"
                    :href="safeUrl(stats[0].url)"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="fs_sw_summary_value"
                    :title="fullValue(stats[0].value, stats[0].type)"
                >{{ displayShort(stats[0].value, stats[0].type) }}</a>
                <span v-else class="fs_sw_summary_value" :title="fullValue(stats[0].value, stats[0].type)">
                    {{ displayShort(stats[0].value, stats[0].type) }}
                </span>
                <span class="fs_sw_summary_label">{{ stats[0].label }}</span>
            </div>
            <div v-if="stats.length > 1" class="fs_sw_summary_line">
                <span
                    v-for="(stat, statIndex) in stats.slice(1)"
                    :key="statIndex"
                    class="fs_sw_summary_item"
                    :title="fullValue(stat.value, stat.type)"
                >{{ stat.label }} <strong>{{ displayShort(stat.value, stat.type) }}</strong></span>
            </div>
        </div>

        <div v-else-if="stats.length" class="fs_sw_stats">
            <div v-for="(stat, statIndex) in stats" :key="statIndex" class="fs_sw_stat">
                <div class="fs_sw_stat_label">{{ stat.label }}</div>
                <a
                    v-if="safeUrl(stat.url)"
                    :href="safeUrl(stat.url)"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="fs_sw_stat_value"
                    :title="fullValue(stat.value, stat.type)"
                >{{ displayValue(stat.value, stat.type) }}</a>
                <div v-else class="fs_sw_stat_value" :title="fullValue(stat.value, stat.type)">
                    {{ displayValue(stat.value, stat.type) }}
                </div>
            </div>
        </div>

        <ul v-if="facts.length" class="fs_sw_facts">
            <li v-for="(fact, factIndex) in facts" :key="factIndex" class="fs_sw_fact">
                <span class="fs_sw_fact_label">{{ fact.label }}</span>
                <a
                    v-if="safeUrl(fact.url)"
                    :href="safeUrl(fact.url)"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="fs_sw_fact_value"
                    :title="fullValue(fact.value, fact.type)"
                >{{ displayValue(fact.value, fact.type) }}</a>
                <span v-else class="fs_sw_fact_value" :title="fullValue(fact.value, fact.type)">
                    {{ displayValue(fact.value, fact.type) }}
                </span>
            </li>
        </ul>

        <div v-for="(section, sectionIndex) in sections" :key="sectionIndex" class="fs_sw_section">
            <div v-if="isText(section.label)" class="fs_sw_section_label">{{ section.label }}</div>

            <div v-if="sectionTags(section).length" class="fs_sw_tags">
                <span v-for="(tag, tagIndex) in sectionTags(section)" :key="tagIndex" class="fs_sw_tag">{{ tag }}</span>
            </div>

            <ul v-if="sectionItems(section).length" class="fs_sw_items">
                <li v-for="(item, itemIndex) in sectionItems(section)" :key="itemIndex" class="fs_sw_item">
                    <el-tooltip
                        :disabled="!isText(item.info)"
                        effect="dark"
                        :content="isText(item.info) ? item.info : ''"
                        placement="top"
                        popper-class="fs_tooltip fs_tooltip_compact"
                    >
                        <div
                            class="fs_sw_item_body"
                            :class="{ 'has-info': isText(item.info) }"
                            :tabindex="isText(item.info) ? 0 : null"
                            :aria-label="isText(item.info) ? item.info : null"
                        >
                            <div class="fs_sw_item_main">
                                <a
                                    v-if="safeUrl(item.url)"
                                    :href="safeUrl(item.url)"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="fs_sw_item_title"
                                    :title="item.title"
                                >{{ item.title }}</a>
                                <span v-else class="fs_sw_item_title" :title="item.title">{{ item.title }}</span>
                                <span
                                    v-if="isText(item.meta)"
                                    class="fs_sw_item_meta"
                                    :class="{ 'is-refunded': item.refunded }"
                                    :title="fullValue(item.meta, item.meta_type)"
                                >{{ displayValue(item.meta, item.meta_type) }}</span>
                            </div>
                            <div
                                v-if="isText(item.subtitle) || isText(item.subtitle_meta) || (hasBadge(item) && !hasReference(item))"
                                class="fs_sw_item_sub"
                            >
                                <span v-if="isText(item.subtitle)" class="fs_sw_item_subtitle">{{ item.subtitle }}</span>
                                <span
                                    v-if="isText(item.subtitle_meta)"
                                    class="fs_sw_item_sub_meta"
                                    :title="fullValue(item.subtitle_meta, item.subtitle_meta_type)"
                                >{{ displayShort(item.subtitle_meta, item.subtitle_meta_type) }}</span>
                                <el-tag
                                    v-if="hasBadge(item) && !hasReference(item)"
                                    size="small"
                                    :type="badgeType(item.badge.type)"
                                    class="fs_sw_badge"
                                >{{ item.badge.text }}</el-tag>
                            </div>
                            <div v-if="hasReference(item) || relatedItems(item).length" class="fs_sw_item_ref">
                                <span class="fs_sw_item_ref_main">
                                    <a
                                        v-if="hasReference(item) && safeUrl(item.reference.url)"
                                        :href="safeUrl(item.reference.url)"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="fs_sw_item_reference"
                                    >{{ item.reference.text }}</a>
                                    <span v-else-if="hasReference(item)" class="fs_sw_item_reference">{{ item.reference.text }}</span>
                                    <el-tag
                                        v-if="hasBadge(item) && hasReference(item)"
                                        size="small"
                                        :type="badgeType(item.badge.type)"
                                        class="fs_sw_badge"
                                    >{{ item.badge.text }}</el-tag>
                                </span>
                                <button
                                    v-if="relatedItems(item).length"
                                    type="button"
                                    class="fs_sw_item_toggle"
                                    :aria-expanded="isExpanded(sectionIndex, itemIndex) ? 'true' : 'false'"
                                    @click="toggleRelated(sectionIndex, itemIndex)"
                                >
                                    <span>{{ $t('Related') }}</span>
                                    <svg
                                        class="fs_sw_item_toggle_icon"
                                        :class="{ 'is-open': isExpanded(sectionIndex, itemIndex) }"
                                        width="16"
                                        height="16"
                                        viewBox="0 0 16 16"
                                        fill="none"
                                        aria-hidden="true"
                                    >
                                        <path d="M8 8.7032L10.97 5.7332L11.8184 6.5816L8 10.4L4.1816 6.5816L5.03 5.7332L8 8.7032Z" fill="currentColor"/>
                                    </svg>
                                </button>
                            </div>
                            <div v-if="isText(item.detail)" class="fs_sw_item_detail">{{ item.detail }}</div>
                        </div>
                    </el-tooltip>

                    <ul v-if="relatedItems(item).length && isExpanded(sectionIndex, itemIndex)" class="fs_sw_related" role="list">
                        <li v-for="(related, relatedIndex) in relatedItems(item)" :key="relatedIndex" class="fs_sw_related_item">
                            <div class="fs_sw_item_main">
                                <a
                                    v-if="safeUrl(related.url)"
                                    :href="safeUrl(related.url)"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="fs_sw_item_title"
                                    :title="related.title"
                                >{{ related.title }}</a>
                                <span v-else class="fs_sw_item_title" :title="related.title">{{ related.title }}</span>
                                <span v-if="isText(related.meta)" class="fs_sw_item_meta">{{ displayValue(related.meta, related.meta_type) }}</span>
                            </div>
                            <div v-if="isText(related.subtitle) || isText(related.subtitle_meta)" class="fs_sw_item_sub">
                                <span v-if="isText(related.subtitle)" class="fs_sw_item_subtitle">{{ related.subtitle }}</span>
                                <span v-if="isText(related.subtitle_meta)" class="fs_sw_item_sub_meta">{{ displayShort(related.subtitle_meta, related.subtitle_meta_type) }}</span>
                            </div>
                            <div v-if="hasReference(related) || hasBadge(related)" class="fs_sw_item_ref">
                                <span class="fs_sw_item_ref_main">
                                    <a
                                        v-if="hasReference(related) && safeUrl(related.reference.url)"
                                        :href="safeUrl(related.reference.url)"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="fs_sw_item_reference"
                                    >{{ related.reference.text }}</a>
                                    <span v-else-if="hasReference(related)" class="fs_sw_item_reference">{{ related.reference.text }}</span>
                                    <el-tag
                                        v-if="hasBadge(related)"
                                        size="small"
                                        :type="badgeType(related.badge.type)"
                                        class="fs_sw_badge"
                                    >{{ related.badge.text }}</el-tag>
                                </span>
                            </div>
                        </li>
                    </ul>
                </li>
            </ul>
        </div>

        <div v-if="links.length" class="fs_sw_links">
            <a
                v-for="(link, linkIndex) in links"
                :key="linkIndex"
                :href="safeUrl(link.url)"
                target="_blank"
                rel="noopener noreferrer"
                class="fs_sw_link"
            >{{ isText(link.label) ? link.label : link.url }}</a>
        </div>
    </div>
</template>

<script type="text/babel">
const BADGE_TYPES = ['success', 'warning', 'danger', 'info', 'primary'];

// Icons a widget may name for its header (registered in Bits/elements.js).
export const WIDGET_ICONS = ['ShoppingCart', 'ShoppingBag', 'UserFilled', 'Calendar', 'Box', 'Star', 'DataAnalysis', 'Link'];

const STRUCTURED_KEYS = ['badges', 'stats', 'facts', 'sections', 'links'];

// PHP sends an array with gaps in its keys as a JSON object: accept both.
export const toList = (value) => {
    if (Array.isArray(value)) {
        return value;
    }
    return value && typeof value === 'object' ? Object.values(value) : [];
};

const isPlainObject = (value) => value && typeof value === 'object' && !Array.isArray(value);

export const isStructuredWidget = (widget) => {
    return !!widget && STRUCTURED_KEYS.some(key => toList(widget[key]).length);
};

// The widgets the sidebar can draw: structured ones, or ones with body_html.
export const filterRenderableWidgets = (widgets) => {
    const renderable = {};
    if (!widgets || typeof widgets !== 'object') {
        return renderable;
    }
    Object.keys(widgets).forEach(key => {
        const widget = widgets[key];
        if (isStructuredWidget(widget) || (widget && widget.body_html)) {
            renderable[key] = widget;
        }
    });
    return renderable;
};

/**
 * Renders a customer widget that sends badges / stats / facts / sections / links
 * instead of body_html.
 * See the fluent_support/customer_extra_widgets docs in ProfileInfoService.
 * Everything is rendered as text, so widgets from another site never inject HTML.
 */
export default {
    name: 'StructuredWidget',
    props: {
        widget: {
            type: Object,
            required: true
        }
    },
    data() {
        return {
            // "sectionIndex:itemIndex" keys of items whose related rows are open
            expanded: []
        };
    },
    computed: {
        badges() {
            return toList(this.widget.badges).filter(badge => isPlainObject(badge) && this.isText(badge.text));
        },
        facts() {
            return toList(this.widget.facts).filter(fact => isPlainObject(fact) && this.isText(fact.label));
        },
        links() {
            return toList(this.widget.links).filter(link => isPlainObject(link) && this.safeUrl(link.url));
        },
        stats() {
            return toList(this.widget.stats).filter(stat => isPlainObject(stat) && this.isText(stat.label));
        },
        sections() {
            return toList(this.widget.sections).filter(isPlainObject);
        },
        isSummary() {
            return this.widget.stats_style === 'summary';
        }
    },
    watch: {
        // Rows are keyed by position, so another customer's data starts collapsed
        widget() {
            this.expanded = [];
        }
    },
    methods: {
        isText(value) {
            return (typeof value === 'string' && value !== '') || typeof value === 'number';
        },
        hasBadge(item) {
            return isPlainObject(item.badge) && this.isText(item.badge.text);
        },
        hasReference(item) {
            return isPlainObject(item.reference) && this.isText(item.reference.text);
        },
        relatedItems(item) {
            return toList(item.related).filter(related => isPlainObject(related) && this.isText(related.title));
        },
        isExpanded(sectionIndex, itemIndex) {
            return this.expanded.includes(`${sectionIndex}:${itemIndex}`);
        },
        toggleRelated(sectionIndex, itemIndex) {
            const key = `${sectionIndex}:${itemIndex}`;
            const index = this.expanded.indexOf(key);
            if (index > -1) {
                this.expanded.splice(index, 1);
            } else {
                this.expanded.push(key);
            }
        },
        sectionTags(section) {
            return toList(section.tags).filter(this.isText);
        },
        sectionItems(section) {
            return toList(section.items).filter(item => isPlainObject(item) && this.isText(item.title));
        },
        // Dates are ISO 8601 strings; a number would be read as milliseconds.
        isDate(value, type) {
            return type === 'date' && typeof value === 'string' && value !== '' && this.$dayjs(value).isValid();
        },
        displayValue(value, type) {
            if (this.isDate(value, type)) {
                return this.$dayjs(value).fromNow();
            }
            return this.isText(value) ? value : '-';
        },
        // Absolute short date ("Oct 15, 2026") for summary figures and item dates.
        displayShort(value, type) {
            if (this.isDate(value, type)) {
                return this.$dayjs(value).format('MMM D, YYYY');
            }
            return this.isText(value) ? value : '-';
        },
        fullValue(value, type) {
            if (this.isDate(value, type)) {
                return this.$dayjs(value).format('MMM D, YYYY h:mm A');
            }
            return '';
        },
        safeUrl(url) {
            return typeof url === 'string' && /^https?:\/\//i.test(url) ? url : '';
        },
        badgeType(type) {
            return BADGE_TYPES.includes(type) ? type : 'info';
        }
    }
}
</script>
