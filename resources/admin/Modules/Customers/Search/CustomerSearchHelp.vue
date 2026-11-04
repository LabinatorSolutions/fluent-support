<template>
    <el-dialog
        v-model="visible"
        :title="$t('Search tips')"
        width="640px"
        :append-to-body="true"
        align-center
        class="fs_dialog fs_customer_search_help"
    >
        <p class="fs_customer_search_help_intro">{{ $t('Customer_Search_Quick_Help') }}</p>

        <section class="fs_customer_search_help_section">
            <h4 class="fs_customer_search_help_heading">{{ $t('Just type') }}</h4>
            <div class="fs_customer_search_help_examples">
                <button
                    v-for="example in examples"
                    :key="example.query"
                    type="button"
                    class="fs_customer_search_help_example"
                    @click="$emit('apply', example.query)"
                >
                    <code>{{ example.query }}</code>
                    <span class="fs_customer_search_help_desc">{{ example.description }}</span>
                    <span class="fs_customer_search_help_try">{{ $t('Try') }}</span>
                </button>
            </div>
        </section>

        <section class="fs_customer_search_help_section">
            <h4 class="fs_customer_search_help_heading">{{ $t('Search one field') }}</h4>
            <p class="fs_customer_search_help_note">{{ $t('Customer_Search_Field_Syntax_Help') }}</p>
            <p class="fs_customer_search_help_note">
                <kbd>/</kbd>
                {{ $t('Shortcut: type / in the search box to pick a field.') }}
            </p>
            <div class="fs_customer_search_help_keys">
                <button
                    v-for="field in fields"
                    :key="field.key"
                    type="button"
                    class="fs_customer_search_help_key"
                    :title="field.hint ? field.key + ':' + field.hint : field.key + ':'"
                    @click="$emit('insert', field.key + ':')"
                >
                    <code>{{ field.key }}:</code>
                    <span>{{ field.label }}</span>
                </button>
            </div>
        </section>

        <p class="fs_customer_search_help_note fs_mt-16">
            <a
                class="fs_doc_link"
                href="https://docs.fluentsupport.com/customer-search"
                target="_blank"
                rel="noopener"
            >{{ $t('Read the customer search guide') }}</a>
        </p>
    </el-dialog>
</template>

<script>
import { getSearchFields } from './customerSearchFields';

/**
 * Search tips for the CustomerSearch syntax. Emits `apply` (run an example) and `insert` (add a `key:`).
 */
export default {
    name: 'CustomerSearchHelp',
    props: {
        modelValue: Boolean
    },
    emits: ['update:modelValue', 'apply', 'insert'],
    computed: {
        visible: {
            get() {
                return this.modelValue;
            },
            set(value) {
                this.$emit('update:modelValue', value);
            }
        },
        examples() {
            return [
                { query: 'timothy cook', description: this.$t('Name or email containing every word') },
                { query: '"timothy cook"', description: this.$t('The exact phrase in the full name or email') },
                { query: 'jane@example.com', description: this.$t('Email address') },
                { query: '#1024', description: this.$t('Customer ID') },
                { query: 'open:>0 activity:<2026-01-01', description: this.$t('Open tickets, but no activity since the start of 2026') },
                { query: 'status:blocked', description: this.$t('Blocked customers') }
            ];
        },
        fields() {
            return getSearchFields(this.$t);
        }
    }
};
</script>
