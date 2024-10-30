<template>
    <div class="fs-task-timer-wrap">
        <div class="fs-side-header">
            <h4 class="sidebar-title">Time Tracking</h4>
            <el-button @click="toggleLog" size="small">
                <el-icon>
                    <Plus/>
                </el-icon>
                <span>Log</span>
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
                    <span v-else>n/a</span>
                </el-progress>
            </div>
            <div class="fs-estimated-labels">
                <el-popover ref="estimatedTimePop" popper-class="fs-timer-log-popover" :placement="estimateTimePopoverPlacement" :width="400" trigger="click">
                    <template #reference>
                        <div style="cursor: pointer;">{{ totalLoggedLabel }} logged</div>
                    </template>
                    <el-table stripe :data="tracks" style="width: 100%">
                        <el-table-column type="expand" width="30">
                            <template #default="{ row }">
                                <div class="detail-log">
                                    <p><b>Logged at:</b> {{ smartDate(row.created_at, true) }}</p>
                                    <p><b>Work Description:</b> {{ row.message }}</p>
                                </div>
                            </template>
                        </el-table-column>
                        <el-table-column prop="user" label="User">
                            <template #default="{ row }">
                                <div class="person">
                                    <el-avatar :src="row.user?.photo" size="small"></el-avatar>
                                    <span>{{ row.user?.display_name }}</span>
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
                                Set Estimation
                            </el-button>
                        </template>
                        <h4 class="estimation-title">Set Estimated Time</h4>
                        <HourMinuteInput v-if="estimatedForm" v-model="estimatedMinutes"/>
                        <el-button :disabled="updating" v-loading="updating" @click="updateEstimatedTime" class="update-btn" type="success">
                            Update Estimation
                        </el-button>
                    </el-popover>
                </div>
            </div>
            <div v-if="logForm" class="fs-new-timer">
                <el-form ref="newLogForm" v-loading="updating" label-position="top">
                    <HourMinuteInput v-model="newLog.billable_minutes"/>
                    <el-form-item label="Work Description">
                        <el-input placeholder="Describe this log entry" v-model="newLog.message" type="textarea" rows="2"/>
                    </el-form-item>
                    <el-form-item>
                        <el-button type="success" :disabled="updating" v-loading="updating" @click="logTime()">Log Time</el-button>
                        <el-button text @click="logForm = false">Cancel</el-button>
                    </el-form-item>
                </el-form>
            </div>
        </div>
    </div>
</template>

<script>
import { reactive, computed, onMounted, toRefs } from 'vue';
import { useFluentHelper } from "../../../Composable/FluentFrameworkHelper";
import { Plus, Clock } from '@element-plus/icons-vue';
import HourMinuteInput from './_HourMinuteInput.vue';

export default {
    name: 'TaskTimer',
    props: ['ticket_id'],
    components: {
        Plus, Clock, HourMinuteInput
    },
    setup(props) {
        const { smartDate, get, post } = useFluentHelper();

        const state = reactive({
            tracks: [],
            estimatedMinutes: 0,
            loading: false,
            updating: false,
            estimatedForm: false,
            logForm: false,
            newLog: { billable_minutes: '', message: '', started_at: '' },
            estimateTimePopoverPlacement: 'left'
        });

        const percentileCompleted = computed(() => state.estimatedMinutes ? Math.round((totalLoggedMinutes.value / state.estimatedMinutes) * 100) : 0);
        const progressColor = computed(() => percentileCompleted.value > 100 ? '#FF5722' : '#409eff');
        const totalLoggedMinutes = computed(() => state.tracks.reduce((sum, track) => sum + track.billable_minutes, 0));
        const totalLoggedLabel = computed(() => formatMinutes(totalLoggedMinutes.value));
        const totalEstimationLabel = computed(() => formatMinutes(state.estimatedMinutes));

        function toggleLog() {
            state.logForm = !state.logForm;
        }

        function toggleEstimatedForm() {
            state.estimatedForm = !state.estimatedForm;
        }

        // function fetchTracks() {
        //     state.loading = true;
        //     setTimeout(() => { state.tracks = []; state.loading = false; }, 1000);
        // }

        const fetchTracks = () => {
            state.loading = true;
            get('tickets/' + props.ticket_id + '/time-tracks')
                .then(response => {
                    // state.tracks = response.tracks;
                    // state.estimatedMinutes = response.estimated_minutes;
                    state.estimatedMinutes = response.estimated_minutes;
                })
                .catch(error => {
                    this.$handleError(error);
                })
        };

        function formatMinutes(minutes) {
            const intMinutes = parseInt(minutes);
            if (!intMinutes) return '';
            const hours = Math.floor(intMinutes / 60);
            const remMinutes = intMinutes % 60;
            return hours ? `${hours}h ${remMinutes}m` : `${remMinutes}m`;
        }

        function logTime() {
            if (state.newLog.billable_minutes < 1) {
                alert('Please enter a valid time');
                return;
            }
            state.updating = true;
            setTimeout(() => {
                state.tracks.push({ ...state.newLog });
                state.logForm = false;
                state.newLog = { billable_minutes: '', message: '', started_at: '' };
                state.updating = false;
            }, 1000);
        }

        const updateEstimatedTime = () => {
            state.updating = true;
            post('tickets/' + props.ticket_id + '/time-tracks/estimated-time', {
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
            formatMinutes,
            logTime,
            updateEstimatedTime
        };
    }
};
</script>

<style lang="scss">
.fs-side-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 12px;
    padding-bottom: 8px;
    border-bottom: 1px solid #e0e0e0;

    h4 {
        margin: 0;
        font-weight: 500;
        font-size: 16px;
        color: #444;
    }
}

.fs-estimated-wrap {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    padding: 10px;
    background: #fafafa;
    border-radius: 6px;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.08);

    .el-progress-bar__inner {
        max-width: 100%;
        border-radius: 3px;
    }

    .el-progress-bar__innerText {
        color: #333;
        font-size: 12px;
        font-weight: 400;
    }
}

.fs-estimated-labels {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-top: 12px;
    gap: 6px;

    .el-popover {
        font-size: 12px;
        color: #666;
    }

    .el-table {
        font-size: 12px;
        border-radius: 4px;
        overflow: hidden;
    }
}

.fs-new-timer {
    background: #ffffff;
    padding: 15px;
    margin: 12px 0;
    border-radius: 8px;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.08);

    .el-form-item {
        margin-bottom: 12px;
    }

    .el-input {
        border-radius: 4px;
    }

    .el-button {
        border-radius: 4px;
        font-weight: 500;
        padding: 4px 12px;
    }

    .update-btn {
        margin-top: 15px;
    }
}

.estimation-title {
    font-weight: 500;
    font-size: 14px;
    color: #555;
    margin-bottom: 6px;
    padding: 8px 0;
    border-bottom: 1px solid #eaeaea;
}

.fs-task-timer-wrap {
    background-color: #ffffff;
}

.fs-new-log {
    .el-input__inner {
        background-color: #fbfbfb;
        color: #333;
        font-size: 13px;
    }
}
</style>


