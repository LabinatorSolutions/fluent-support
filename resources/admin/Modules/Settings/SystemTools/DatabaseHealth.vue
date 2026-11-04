<template>
    <div class="fs_box_wrapper fs_system_tools">
        <div class="fs_box_header">
            <div class="fs_box_head">
                <h3>{{ $t('Database Health') }}</h3>
            </div>
        </div>

        <div class="fs_box_body fs_st_body">
            <div class="fs_st_card">
                <div class="fs_st_card_header">
                    <h4>{{ $t('Database Tables') }}</h4>
                    <div class="fs_st_card_actions">
                        <el-button class="fs_outline_btn" :loading="loading" @click="fetchHealth">{{ $t('Re-check') }}</el-button>
                        <el-popconfirm
                            v-if="canManage && needsRepair"
                            :width="280"
                            :title="$t('Re-run the database migrations? This only creates missing tables and columns. It never deletes data.')"
                            :confirm-button-text="$t('Repair Now')"
                            :cancel-button-text="$t('Cancel')"
                            @confirm="repair"
                        >
                            <template #reference>
                                <el-button class="fs_filled_btn" :loading="repairing">{{ $t('Repair Now') }}</el-button>
                            </template>
                        </el-popconfirm>
                    </div>
                </div>

                <div class="fs_st_card_body" v-loading="loading">
                    <p class="fs_st_intro">
                        {{ $t('Fluent Support stores tickets, replies, customers and settings in these tables. If an update was interrupted, a table may be missing. Repairing recreates it safely.') }}
                    </p>

                    <template v-if="loaded">
                        <div v-if="needsRepair" class="fs_st_alert is_error">
                            <el-icon><CircleCloseFilled/></el-icon>
                            <span>{{ problemText }}</span>
                        </div>
                        <div v-else class="fs_st_alert is_ok">
                            <el-icon><CircleCheckFilled/></el-icon>
                            <span>{{ $t('All tables are present.') }}</span>
                        </div>

                        <div class="fs_st_list">
                            <div v-for="table in tables" :key="table.name" class="fs_st_list_item">
                                <div class="fs_st_list_info">
                                    <p class="fs_st_list_name">{{ table.title }}</p>
                                    <p class="fs_st_list_meta">
                                        <span class="fs_st_label">{{ $t('Table:') }}</span>
                                        <span>{{ table.name }}</span>
                                    </p>
                                </div>
                                <el-tag size="small" effect="light" :type="table.status === 'ok' ? 'success' : 'danger'">
                                    {{ table.status === 'ok' ? $t('Healthy') : $t('Missing') }}
                                </el-tag>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </div>
</template>

<script type="text/babel">
import {CircleCheckFilled, CircleCloseFilled} from '@element-plus/icons-vue';

export default {
    name: 'DatabaseHealth',
    components: {
        CircleCheckFilled,
        CircleCloseFilled
    },
    data() {
        return {
            loading: false,
            loaded: false,
            repairing: false,
            canManage: false,
            tables: []
        }
    },
    computed: {
        missingCount() {
            return this.tables.filter(table => table.status !== 'ok').length;
        },
        needsRepair() {
            return this.missingCount > 0;
        },
        problemText() {
            // translators: %d is the number of missing database tables
            return this.$t('%d table(s) are missing.').replace('%d', this.missingCount);
        }
    },
    methods: {
        fetchHealth() {
            this.loading = true;
            this.$get('system-tools/database')
                .then(response => {
                    this.tables = response.tables;
                    this.canManage = response.can_manage;
                    this.loaded = true;
                })
                .catch(errors => {
                    this.$handleError(errors);
                })
                .always(() => {
                    this.loading = false;
                });
        },
        repair() {
            this.repairing = true;
            this.$post('system-tools/database/repair')
                .then(response => {
                    this.$notify({
                        type: 'success',
                        message: response.message,
                        position: 'bottom-right'
                    });
                    this.fetchHealth();
                })
                .catch(errors => {
                    this.$handleError(errors);
                })
                .always(() => {
                    this.repairing = false;
                });
        }
    },
    mounted() {
        this.fetchHealth();
        this.$setTitle(this.$t('Database Health'));
    }
}
</script>
