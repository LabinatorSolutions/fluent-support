<template>
    <div v-loading="loading" class="fs_all_workflows">
        <div class="fs_box_wrapper">
            <div class="fs_box_header">
                <div class="fs_box_head">
                    <h3>{{ $t('All Workflows') }}</h3>
                </div>
                <div class="fs_box_actions fs_ticket_orders">
                    <el-button size="mini" icon="el-icon-plus">{{ $t('Add New WorkFlow') }}</el-button>
                </div>
            </div>
            <div class="fs_box_body fs_padded_20">
                <pre>{{workflows}}</pre>
            </div>
        </div>
    </div>
</template>

<script type="text/babel">
export default {
    name: 'AllWorkflows',
    data() {
        return {
            workflows: [],
            loading: false,
            pagination: {
                per_page: 10,
                current_page: 1,
                total: 0
            }
        }
    },
    methods: {
        fetch() {
            this.loading = true;
            this.$get('workflows', {
                per_page: this.pagination.per_page,
                page: this.pagination.current_page,
                search: this.search
            })
                .then((response) => {
                    this.workflows = response.workflows.data;
                    this.pagination.total = response.workflows.total;
                })
                .catch((errors) => {
                    this.$handleError(errors);
                })
                .always(() => {
                    this.loading = false;
                });
        }
    },
    mounted() {
        if (this.has_pro) {
            this.fetch();
        }
    }
}
</script>
