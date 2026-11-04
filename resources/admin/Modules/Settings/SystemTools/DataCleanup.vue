<template>
    <div class="fs_box_wrapper fs_system_tools">
        <div class="fs_box_header">
            <div class="fs_box_head">
                <h3>{{ $t('Data Cleanup') }}</h3>
            </div>
        </div>

        <div class="fs_box_body fs_st_body">
            <div class="fs_st_card">
                <div class="fs_st_card_body">
                    <el-form label-position="top">
                        <el-form-item :label="$t('Time Range (days)')">
                            <el-input v-model="daysBefore" type="number" min="7" class="fs_text_input fs_st_days_input"/>
                            <p v-if="belowMinDays" class="fs_st_field_help is_error">
                                {{ $t('Time range must be at least 7 days.') }}
                            </p>
                            <p v-else class="fs_st_field_help">
                                {{ daysHelp[0] }}<strong>{{ daysBefore }}</strong>{{ daysHelp[1] }}
                            </p>
                        </el-form-item>
                        <el-form-item :label="$t('Select logs you want to delete')">
                            <el-checkbox-group v-model="selectedLogs" class="fs_st_list">
                                <div v-for="log in logTypes" :key="log.key" class="fs_st_list_item fs_st_checkbox_item">
                                    <el-checkbox :value="log.key">
                                        <span class="fs_st_list_name">{{ log.title }}</span>
                                        <span class="fs_st_checkbox_desc">{{ log.description }}</span>
                                    </el-checkbox>
                                </div>
                            </el-checkbox-group>
                        </el-form-item>
                    </el-form>

                    <div class="fs_st_alert is_warning">
                        <el-icon><WarningFilled/></el-icon>
                        <span>{{ $t('Selected logs will be deleted permanently. Tickets, replies, customers and attachments are never deleted.') }}</span>
                    </div>

                    <el-button v-if="selectedLogs.length" class="fs_filled_btn" :disabled="belowMinDays" @click="openPreview">
                        {{ $t('Preview Log Summary') }}
                    </el-button>
                </div>
            </div>
        </div>

        <el-dialog
            v-model="previewVisible"
            :title="$t('Preview Log Summary')"
            width="440px"
            class="fs_dialog fs_st_cleanup_dialog"
            append-to-body
            :close-on-click-modal="!deleting"
            :close-on-press-escape="!deleting"
            :show-close="!deleting"
        >
            <div v-loading="previewLoading">
                <div class="fs_st_count_table">
                    <div class="fs_st_count_row is_head">
                        <span>{{ $t('Type') }}</span>
                        <span>{{ $t('Count') }}</span>
                    </div>
                    <div v-for="row in logCounts" :key="row.key" class="fs_st_count_row">
                        <span>{{ row.title }}</span>
                        <span>{{ Number(row.count).toLocaleString() }}</span>
                    </div>
                </div>
                <p class="fs_st_field_help">{{ $t('Please review before you delete your old logs.') }}</p>
            </div>
            <template #footer>
                <div class="fs_popconfirm_actions">
                    <el-button class="fs_outline_btn" :disabled="deleting" @click="previewVisible = false">
                        {{ $t('Cancel') }}
                    </el-button>
                    <el-button
                        v-if="canManage"
                        class="fs_filled_btn"
                        :loading="deleting"
                        :disabled="previewLoading || !totalCount"
                        @click="deleteOldLogs"
                    >
                        {{ $t('Yes, Delete Old Logs') }}
                    </el-button>
                </div>
            </template>
        </el-dialog>
    </div>
</template>

<script type="text/babel">
import {WarningFilled} from '@element-plus/icons-vue';

export default {
    name: 'DataCleanup',
    components: {
        WarningFilled
    },
    data() {
        return {
            daysBefore: 30,
            selectedLogs: [],
            logTypes: [
                {
                    key: 'activity_logs',
                    title: this.$t('Activity Logs'),
                    description: this.$t('Ticket and agent actions shown on the Activities page.')
                },
                {
                    key: 'ai_activity_logs',
                    title: this.$t('AI Activity Logs'),
                    description: this.$t('Records of AI replies, summaries and their token usage.')
                },
                {
                    key: 'internal_notifications',
                    title: this.$t('Internal Notifications'),
                    description: this.$t('In-app notifications sent to agents, such as mentions and assignments.')
                }
            ],
            previewVisible: false,
            previewLoading: false,
            deleting: false,
            canManage: false,
            logCounts: []
        }
    },
    computed: {
        daysHelp() {
            // translators: %d is the number of days entered in the form
            return this.$t('Only logs older than %d days will be deleted.').split('%d');
        },
        belowMinDays() {
            return Number(this.daysBefore) < 7;
        },
        totalCount() {
            return this.logCounts.reduce((total, row) => total + Number(row.count), 0);
        },
        cleanupParams() {
            return {
                days_before: this.daysBefore,
                selected_logs: this.selectedLogs
            };
        }
    },
    methods: {
        openPreview() {
            this.previewVisible = true;
            this.previewLoading = true;
            this.logCounts = [];
            this.$get('system-tools/cleanup', this.cleanupParams)
                .then(response => {
                    this.logCounts = response.log_counts;
                    this.canManage = response.can_manage;
                })
                .catch(errors => {
                    this.previewVisible = false;
                    this.$handleError(errors);
                })
                .always(() => {
                    this.previewLoading = false;
                });
        },
        // The API deletes in chunks and reports has_more, so keep calling until it's done.
        deleteOldLogs() {
            const params = {...this.cleanupParams, selected_logs: [...this.selectedLogs]};
            const deleteChunk = () => this.$del('system-tools/cleanup', params)
                .then(response => response.has_more ? deleteChunk() : response);

            this.deleting = true;
            deleteChunk()
                .then(response => {
                    this.$notify({
                        type: 'success',
                        message: response.message,
                        position: 'bottom-right'
                    });
                    this.previewVisible = false;
                    this.selectedLogs = [];
                })
                .catch(errors => {
                    this.$handleError(errors);
                })
                .always(() => {
                    this.deleting = false;
                });
        }
    },
    mounted() {
        this.$setTitle(this.$t('Data Cleanup'));
    }
}
</script>
