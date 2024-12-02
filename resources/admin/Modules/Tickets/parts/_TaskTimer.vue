<template>
    <div class="fs-task-timer-wrap">
        <div class="fs-side-header">
            <h4 class="fs-sidebar-title">{{ translate('Time Tracking') }}</h4>
            <el-button @click="toggleLog" size="small">
                <el-icon>
                    <Plus/>
                </el-icon>
                <span>{{ translate('Log') }}</span>
            </el-button>
        </div>
        <div class="fs-timer-container">
            <div class="fs-estimated-wrap">
                <el-icon style="font-size: 30px;">
                    <Clock/>
                </el-icon>
                <el-progress :color="progressColor" style="width: 100%;"
                             :percentage="percentileCompleted"
                             :text-inside="true" :stroke-width="20">
                    <span v-if="estimatedMinutes">{{ percentileCompleted }}%</span>
                    <span v-else>{{ translate('n/a') }}</span>
                </el-progress>
            </div>
            <div class="fs-estimated-labels">
                <el-popover ref="estimatedTimePop" popper-class="fs-timer-log-popover"
                            :placement="estimateTimePopoverPlacement" :width="400" trigger="click">
                    <template #reference>
                        <div style="cursor: pointer;">{{ totalLoggedLabel }} logged</div>
                    </template>
                    <el-table stripe :data="tracks" style="width: 100%">
                        <el-table-column type="expand" width="30">
                            <template #default="{ row }">
                                <div class="fs_detail_log">
                                    <p><b>{{ translate('Logged at:') }}</b> {{ smartDate(row.created_at, true) }}</p>
                                    <p><b>{{ translate('Work Description:') }}</b> {{ row.message }}</p>
                                </div>
                            </template>
                        </el-table-column>
                        <el-table-column prop="agent" label="Agent">
                            <template #default="{ row }">
                                <div class="fs-time-track-agent">
                                    <el-avatar :src="row.agent?.photo" size="small"></el-avatar>
                                    <a
                                        :href="`/wp-admin/user-edit.php?user_id=${row.agent.user_id}`"
                                        target="_blank"
                                        style="text-decoration: none; margin-left: 8px;">
                                        <strong> {{ row.agent?.full_name  }} </strong>
                                    </a>
                                </div>
                            </template>
                        </el-table-column>
                        <el-table-column prop="billable_minutes" label="Time" width="80">
                            <template #default="{ row }">
                                <span>{{ formatMinutes(row.billable_minutes) }}</span>
                            </template>
                        </el-table-column>
                        <el-table-column width="80" prop="created_at" label="Date">
                            <template #default="{ row }">
                                <span>{{ smartDate(row.created_at) }}</span>
                            </template>
                        </el-table-column>
                    </el-table>
                </el-popover>
                <div>
                    <el-popover ref="estimatedTimeForm" placement="right" :width="400" :visible="estimatedForm">
                        <template #reference>
                            <span @click="toggleEstimatedForm" style="cursor: pointer;" v-if="estimatedMinutes">
                                {{ totalEstimationLabel }} estimated</span>
                            <el-button @click="toggleEstimatedForm" v-else type="info" size="small">
                                {{ translate('Set Estimation') }}
                            </el-button>
                        </template>
                        <h4 class="fs-estimation-title">{{translate('Set Estimated Time')}}</h4>
                        <HourMinuteInput v-if="estimatedForm" v-model="estimatedMinutes"/>
                        <el-button :disabled="updating" v-loading="updating" @click="updateEstimatedTime"
                                   class="update-btn" type="success">
                            {{ translate('Update Estimation') }}
                        </el-button>
                    </el-popover>
                </div>
            </div>
            <div v-if="logForm" class="fs-new-timer">
                <el-form ref="newLogForm" v-loading="updating" label-position="top">
                    <HourMinuteInput v-model="newLog.billable_minutes"/>
                    <el-form-item label="Work Description">
                        <el-input placeholder="Describe this log entry" v-model="newLog.message" type="textarea"
                                  rows="2"/>
                    </el-form-item>
                    <el-form-item>
                        <el-button type="success" :disabled="updating" v-loading="updating" @click="logTime()">
                            {{ translate('Log Time') }}
                        </el-button>
                        <el-button text @click="logForm = false">{{ translate('Cancel') }}</el-button>
                    </el-form-item>
                </el-form>
            </div>
        </div>
    </div>
</template>

<script>
import {computed, onMounted, reactive, toRefs} from 'vue';
import {useFluentHelper} from "../../../Composable/FluentFrameworkHelper";
import {Clock, Plus} from '@element-plus/icons-vue';
import HourMinuteInput from './_HourMinuteInput.vue';

export default {
    name: 'TaskTimer',
    props: ['ticket_id', 'customer_id'],
    components: {
        Plus, Clock, HourMinuteInput
    },
    setup(props) {
        const {
            translate,
            smartDate,
            get,
            post
        } = useFluentHelper();

        const state = reactive({
            tracks: [],
            estimatedMinutes: 0,
            loading: false,
            updating: false,
            estimatedForm: false,
            logForm: false,
            newLog: {
                billable_minutes: '',
                message: '',
                started_at: ''
            },
            estimateTimePopoverPlacement: 'left'
        });

        const percentileCompleted = computed(() => state.estimatedMinutes ? Math.round((totalLoggedMinutes.value / state.estimatedMinutes) * 100) : 0);
        const progressColor = computed(() => percentileCompleted.value > 100 ? '#FF5722' : '#409eff');
        const totalLoggedMinutes = computed(() => state.tracks.reduce((sum, track) => sum + track.billable_minutes, 0));
        const totalLoggedLabel = computed(() => formatMinutes(totalLoggedMinutes.value));
        const totalEstimationLabel = computed(() => formatMinutes(state.estimatedMinutes));

        const toggleLog = () => {
            state.logForm = !state.logForm;
        }

        const toggleEstimatedForm = () => {
            state.estimatedForm = !state.estimatedForm;
        }

        const fetchTracks = () => {
            state.loading = true;
            get(`time-tracks/${props.ticket_id}`)
                .then(response => {
                    state.tracks = response.tracks;
                    state.estimatedMinutes = response.estimated_minutes;
                })
                .catch(error => {
                    this.$handleError(error);
                })
        };

        const formatMinutes = (minutes) => {
            const intMinutes = parseInt(minutes);
            if (!intMinutes) return '';
            const hours = Math.floor(intMinutes / 60);
            const remMinutes = intMinutes % 60;
            return hours ? `${hours}h ${remMinutes}m` : `${remMinutes}m`;
        }

        const logTime = () => {
            if (state.newLog.billable_minutes < 1) {
                alert('Please enter a valid time');
                return;
            }
            state.updating = true;
            post(`time-tracks/${props.ticket_id}`, {
                customer_id: props.customer_id,
                billable_minutes: state.newLog.billable_minutes,
                message: state.newLog.message,
                started_at: state.newLog.started_at
            })
                .then(response => {
                    state.tracks.push(response.track);

                    state.logForm = false;
                    state.updating = false;
                    state.newLog = {
                        billable_minutes: '',
                        message: '',
                        started_at: ''
                    };
                })
                .catch(error => {
                    this.logForm = false;
                    state.updating = false;
                    console.error('Error posting time track:', error);
                });
        }

        const updateEstimatedTime = () => {
            state.updating = true;
            post('time-tracks/' + props.ticket_id + '/estimated-time', {
                estimated_minutes: state.estimatedMinutes
            })
                .then(response => {
                    state.updating = false;
                    state.estimatedForm = false;
                })
                .catch(error => {
                    this.$handleError(error);
                })
        };

        onMounted(() => {
            fetchTracks();
            if (window.innerWidth < 817) state.estimateTimePopoverPlacement = 'bottom';
        });

        return {
            ...toRefs(state),
            smartDate,
            totalLoggedMinutes,
            totalLoggedLabel,
            totalEstimationLabel,
            percentileCompleted,
            progressColor,
            toggleLog,
            toggleEstimatedForm,
            logTime,
            formatMinutes,
            updateEstimatedTime,
            translate
        };
    }
};
</script>




