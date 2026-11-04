<template>
    <div v-if="meta" class="fs_customer_search_summary" role="status">
        <div class="fs_customer_search_summary_line">
            <span v-for="(part, index) in parts" :key="index" class="fs_customer_search_summary_part">
                <span class="fs_customer_search_summary_label">{{ part.label }}</span>
                <code v-for="(value, valueIndex) in part.values" :key="valueIndex">{{ value }}</code>
            </span>
            <span v-if="meta.ranked" class="fs_customer_search_summary_meta">{{ $t('Best matches first') }}</span>
            <el-button v-else-if="canRank" link class="fs_customer_search_summary_action" @click="$emit('best-match')">
                {{ $t('Sort by best match') }}
            </el-button>
        </div>
        <div v-if="meta.unknown && meta.unknown.length" class="fs_customer_search_summary_warning">
            {{ unknownKeysText }}
        </div>
    </div>
</template>

<script>
/**
 * One line under the search box describing how the search was understood.
 * Built from the server's `search_meta` for the rows on screen, not the live input.
 */
export default {
    name: 'CustomerSearchSummary',
    props: {
        meta: {
            type: Object,
            default: null
        },
        // A column sort replaced best-match order for this search; offer it back.
        canRank: Boolean
    },
    emits: ['best-match'],
    computed: {
        unknownKeysText() {
            const keys = (this.meta && this.meta.unknown) || [];
            const list = keys.map(key => key + ':').join(', ');
            return keys.length === 1
                // translators: %s is a search field key the user typed, such as "zip:"
                ? this.$t('%s is not a search field, so it was matched as plain text.', list)
                // translators: %s is a comma-separated list of search field keys the user typed, such as "zip:, area:"
                : this.$t('%s are not search fields, so they were matched as plain text.', list);
        },
        fieldLabels() {
            return {
                id: this.$t('Customer ID'),
                email: this.$t('Email'),
                name: this.$t('Name'),
                first_name: this.$t('First name'),
                last_name: this.$t('Last name'),
                title: this.$t('Job Title'),
                address: this.$t('Address'),
                city: this.$t('City'),
                state: this.$t('State'),
                zip: this.$t('Postal code'),
                country: this.$t('Country'),
                status: this.$t('Status'),
                user_id: this.$t('WordPress user ID'),
                tickets: this.$t('Tickets'),
                open_tickets: this.$t('Open tickets'),
                created_at: this.$t('Created at'),
                last_response_at: this.$t('Last Activity')
            };
        },
        parts() {
            const meta = this.meta;
            const parts = [];

            if (meta.ids && meta.ids.length) {
                parts.push({ label: this.$t('Customer ID'), values: meta.ids.map(id => '#' + id) });
            }

            if (meta.text) {
                if (meta.mode === 'email') {
                    parts.push({ label: this.$t('Email'), values: [meta.text] });
                } else {
                    parts.push({ label: this.$t('Name or email contains'), values: meta.words });
                }
            }

            (meta.scoped || []).forEach(term => {
                parts.push({
                    label: this.fieldLabels[term.field] || term.key,
                    values: [term.value || this.$t('(empty)')]
                });
            });

            return parts;
        }
    }
};
</script>
