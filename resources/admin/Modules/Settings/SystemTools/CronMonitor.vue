<template>
    <div class="fs_box_wrapper fs_system_tools">
        <div class="fs_box_header">
            <div class="fs_box_head">
                <h3>{{ $t('Cron Job Monitor') }}</h3>
            </div>
        </div>

        <div class="fs_box_body fs_st_body">
            <div class="fs_st_card">
                <div class="fs_st_card_header">
                    <h4>{{ $t('REST API Status') }}</h4>
                </div>
                <div class="fs_st_card_body">
                    <div class="fs_st_rest_grid">
                        <div v-for="check in restChecks" :key="check.method" class="fs_st_rest_tile">
                            <p class="fs_st_label">{{ check.label }}</p>
                            <div class="fs_st_rest_badge" :class="'is_' + check.state">
                                <el-icon v-if="check.state === 'ok'"><CircleCheckFilled/></el-icon>
                                <el-icon v-else-if="check.state === 'error'"><CircleCloseFilled/></el-icon>
                                <el-icon v-else><WarningFilled/></el-icon>
                                <span>{{ restStateText(check.state) }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div v-if="server && !server.has_server_cron" class="fs_st_notice">
                <el-icon><InfoFilled/></el-icon>
                <p>{{ $t('Server side cron is not enabled. Please consider enabling server side cron.') }}</p>
                <a :href="serverCronDocUrl" target="_blank" rel="noopener">{{ $t('Read More') }}</a>
            </div>

            <div class="fs_st_card">
                <div class="fs_st_card_header">
                    <h4>{{ $t('Cron Job Status') }}</h4>
                </div>
                <div class="fs_st_card_body" v-loading="loading">
                    <div v-if="server" class="fs_st_server_stats">
                        <div class="fs_st_server_stat">
                            <p class="fs_st_label">{{ $t('Server Memory Limit') }}</p>
                            <p class="fs_st_value">{{ server.memory_limit }}</p>
                        </div>
                        <div class="fs_st_divider"></div>
                        <div class="fs_st_server_stat">
                            <p class="fs_st_label">{{ $t('Current Usage') }}</p>
                            <p class="fs_st_value">{{ server.usage_percent }}%</p>
                        </div>
                        <div class="fs_st_divider"></div>
                        <div class="fs_st_server_stat">
                            <p class="fs_st_label">{{ $t('Max Execution Time') }}</p>
                            <p class="fs_st_value">{{ server.max_execution_time }}</p>
                        </div>
                    </div>

                    <div v-if="events.length" class="fs_st_list">
                        <div v-for="event in events" :key="event.hook" class="fs_st_list_item">
                            <div class="fs_st_list_info">
                                <p class="fs_st_list_name">{{ event.title }}</p>
                                <p class="fs_st_list_meta">
                                    <span class="fs_st_label">{{ $t('Next Run:') }}</span>
                                    <span :class="{fs_st_overdue: event.status !== 'ok'}">{{ nextRunText(event) }}</span>
                                </p>
                            </div>
                            <el-popconfirm
                                v-if="canManage"
                                :width="260"
                                :title="runConfirmText(event)"
                                :confirm-button-text="$t('Run')"
                                :cancel-button-text="$t('Cancel')"
                                @confirm="runEvent(event)"
                            >
                                <template #reference>
                                    <el-button
                                        :icon="VideoPlay"
                                        :loading="runningHook === event.hook"
                                        :disabled="!!runningHook && runningHook !== event.hook"
                                    >
                                        {{ $t('Run Manually') }}
                                    </el-button>
                                </template>
                            </el-popconfirm>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script type="text/babel">
import {markRaw} from 'vue';
import {CircleCheckFilled, CircleCloseFilled, VideoPlay, WarningFilled} from '@element-plus/icons-vue';

export default {
    name: 'CronMonitor',
    components: {
        CircleCheckFilled,
        CircleCloseFilled,
        WarningFilled
    },
    data() {
        return {
            VideoPlay: markRaw(VideoPlay),
            loading: false,
            events: [],
            server: null,
            canManage: false,
            runningHook: '',
            restChecks: [
                {method: '$get', label: this.$t('GET Request Status'), state: 'checking'},
                {method: '$post', label: this.$t('POST Request Status'), state: 'checking'},
                {method: '$put', label: this.$t('PUT Request Status'), state: 'checking'},
                {method: '$del', label: this.$t('DELETE Request Status'), state: 'checking'}
            ],
            serverCronDocUrl: 'https://developer.wordpress.org/plugins/cron/hooking-wp-cron-into-the-system-task-scheduler/'
        }
    },
    methods: {
        fetchEvents() {
            this.loading = true;
            this.$get('system-tools/cron')
                .then(response => {
                    this.events = response.events;
                    this.server = response.server;
                    this.canManage = response.can_manage;
                })
                .catch(errors => {
                    this.$handleError(errors);
                })
                .always(() => {
                    this.loading = false;
                });
        },
        runEvent(event) {
            this.runningHook = event.hook;
            this.$post('system-tools/cron/run', {hook: event.hook})
                .then(response => {
                    this.$notify({
                        type: 'success',
                        message: response.message,
                        position: 'bottom-right'
                    });
                    this.fetchEvents();
                })
                .catch(errors => {
                    this.$handleError(errors);
                })
                .always(() => {
                    this.runningHook = '';
                });
        },
        // Sequential so a failing method is attributed to the right tile.
        checkRestMethods(index = 0) {
            const check = this.restChecks[index];
            if (!check) {
                return;
            }

            const startedAt = Date.now();
            this[check.method]('system-tools/rest-test')
                .then(() => {
                    check.state = (Date.now() - startedAt) > 2000 ? 'slow' : 'ok';
                })
                .catch(() => {
                    check.state = 'error';
                })
                .always(() => {
                    this.checkRestMethods(index + 1);
                });
        },
        restStateText(state) {
            const texts = {
                ok: this.$t('Operational'),
                slow: this.$t('Slow Response'),
                error: this.$t('Down'),
                checking: this.$t('Checking...')
            };

            return texts[state];
        },
        nextRunText(event) {
            if (event.status === 'not_scheduled') {
                return this.$t('Not scheduled');
            }
            if (event.status === 'overdue') {
                // translators: %s is when the overdue cron event was due, e.g. "5 minutes ago"
                return this.$t('Overdue: %s').replace('%s', event.next_run_human);
            }

            return event.next_run_human;
        },
        runConfirmText(event) {
            // translators: %s is the cron event name
            return this.$t('Run %s now?').replace('%s', event.title);
        }
    },
    mounted() {
        this.fetchEvents();
        this.checkRestMethods();
        this.$setTitle(this.$t('Cron Job Monitor'));
    }
}
</script>
