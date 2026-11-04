<template>
    <el-select
        v-model="selected"
        multiple
        filterable
        remote
        collapse-tags
        collapse-tags-tooltip
        reserve-keyword
        :remote-method="remoteSearch"
        :loading="loading"
        :placeholder="$t('Search')"
        class="fs_select_field"
    >
        <el-option
            v-for="opt in options"
            :key="opt.id"
            :value="opt.id"
            :label="opt.title"
        ></el-option>
    </el-select>
</template>

<script type="text/babel">
import { debounce } from "lodash-es";

/**
 * Remote search for the commerce workflow conditions (purchased product / variation).
 * The endpoint and the remote_select condition live in Pro, so this only renders with Pro active.
 */
export default {
    name: "RemoteCommerceSelect",
    props: {
        // Fresh condition rows start with data_value === "" before anything is picked.
        modelValue: { type: [Array, String], default: () => [] },
        provider: { type: String, default: "" },
        optionType: { type: String, default: "" },
    },
    emits: ["update:modelValue"],
    data() {
        return {
            options: [],
            loading: false,
            selected: Array.isArray(this.modelValue) ? this.modelValue : [],
        };
    },
    watch: {
        modelValue(value) {
            this.selected = Array.isArray(value) ? value : [];
        },
        selected(value) {
            this.$emit("update:modelValue", value);
        },
    },
    created() {
        // Only the newest request may apply its result.
        this._reqToken = 0;
        this._debouncedFetch = debounce((query) => this.fetchOptions(query), 300);
    },
    mounted() {
        // Resolve labels for any already-selected values (editing a saved workflow).
        if (this.selected.length) {
            this.fetchOptions("", this.selected);
        }
    },
    methods: {
        remoteSearch(query) {
            this._debouncedFetch(query);
        },
        fetchOptions(query, ids) {
            if (!this.provider) {
                return;
            }
            const params = { provider: this.provider, type: this.optionType };
            if (ids && ids.length) {
                params.ids = ids.join(",");
            } else {
                params.search = query || "";
            }

            const token = ++this._reqToken;
            this.loading = true;
            this.$get("workflows/commerce-options", params)
                .then((response) => {
                    if (token === this._reqToken) {
                        this.options = response.options || [];
                    }
                })
                .catch((errors) => {
                    this.$handleError && this.$handleError(errors);
                })
                .always(() => {
                    if (token === this._reqToken) {
                        this.loading = false;
                    }
                });
        },
    },
};
</script>
