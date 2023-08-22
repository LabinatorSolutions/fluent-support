<template>
    <div class="activities fs_box fs_box_wrapper">
        <div v-if="!loading" class="fs_box_header">
            <div class="fs_box_head">
                {{ translate("Overall Activities") }}
            </div>
            <div class="fs_box_actions">
                <el-date-picker
                    @change="fetchActivities"
                    v-model="date_range"
                    type="daterange"
                    :range-separator="translate('To')"
                    :start-placeholder="translate('Start')"
                    :end-placeholder="translate('End')"
                />

                <el-select
                    clearable
                    filterable
                    size="small"
                    @change="fetchActivities()"
                    v-model="filters.agent_id"
                    :placeholder="translate('All Support Staff')"
                    style="margin-right: 10px"
                >
                    <el-option
                        v-for="agent in appVars.support_agents"
                        :key="agent.id"
                        :value="agent.id"
                        :label="agent.full_name"
                    ></el-option>
                </el-select>
                <el-button
                    v-loading="loading"
                    @click="fetchActivities()"
                    icon="Refresh"
                ></el-button>
                <el-button
                    v-if="me.permissions.indexOf('fst_manage_settings') != -1"
                    @click="showSettingsModal = true"
                    type="default"
                    icon="Setting"
                ></el-button>
            </div>
        </div>
        <div class="fs_box fs_activity_box" v-if="activities">
            <div class="fs_box_body">
                <div v-if="settings.disable_logs == 'yes'">
                    <h3 class="text-align-center">
                        {{ translate("Activity Logs are currently disabled") }}
                    </h3>
                </div>

                <template v-if="!loading">
                    <ul v-if="activities.length > 0" class="fs_activities">
                        <li
                            v-for="activity in activities"
                            @click.prevent="handleClick(activity, $event)"
                            :key="activity.id"
                            :class="
                                activity.person_type == 'agent'
                                    ? 'fs_agent_activity'
                                    : 'fs_customer_activity'
                            "
                        >
                            <div class="fs_activity">
                                <div class="fs_activity_avatar">
                                    <img
                                        v-if="activity.person"
                                        :src="activity.person?.photo"
                                        :alt="activity.person.full_name"
                                    />
                                </div>
                                <div class="fs_activity_info">
                                    <span v-html="activity.description"></span>
                                    <br />
                                    <samp>{{
                                        humanDiffTime(activity.created_at)
                                    }}</samp>
                                </div>
                            </div>
                        </li>
                    </ul>
                    <div
                        v-if="
                            settings.disable_logs == 'no' &&
                            activities.length < 1
                        "
                        class="text-align-center"
                    >
                        <h3>{{ translate("activity_not_found") }}</h3>
                    </div>
                    <div
                        style="padding-bottom: 20px"
                        class="fframe_pagination_wrapper"
                        v-if="activities.length"
                    >
                        <pagination
                            @fetch="fetchActivities()"
                            :pagination="pagination"
                        />
                    </div>
                </template>
                <div v-if="loading" class="fs_padded_20">
                    <el-skeleton :rows="3" animated />
                </div>
            </div>
        </div>

        <el-dialog
            v-model="showSettingsModal"
            :title="translate('Activity Log Settings')"
            width="60%"
            :append-to-body="true"
        >
            <activity-settings
                @updated="
                    showSettingsModal = false;
                    fetchActivities();
                "
            />
        </el-dialog>
    </div>
</template>

<script type="text/babel">
import Pagination from "../../Pieces/Pagination";
import ActivitySettings from "./_ActivitySettings";
import dayjs from "dayjs";
import { useRouter } from "vue-router";
import {  onMounted, reactive, toRefs } from "vue";
import { useFluentHelper } from "@/admin/Composable/FluentFrameworkHelper";

export default {
    name: "ActivityLogger",
    components: {
        ActivitySettings,
        Pagination,
    },
    setup() {
        const router = useRouter();
        const {
            appVars,
            get,
            translate,
            handleError,
            humanDiffTime,
            setTitle
        } = useFluentHelper();

        const state = reactive({
            activities: [],
            loading: false,
            me: appVars.me,
            showSettingsModal: false,
            pagination: {
                per_page: 10,
                current_page: 1,
                total: 0,
            },
            settings: {},
            filters: {},
            date_range: null,
        });

        const fetchActivities = async () => {
            state.loading = true;
            await get("activity-logger", {
                per_page: state.pagination.per_page,
                page: state.pagination.current_page,
                filters: state.filters,
                from: state.date_range
                    ? dayjs(state.date_range[0]).format("YYYY-MM-DD")
                    : "",
                to: state.date_range
                    ? dayjs(state.date_range[1]).format("YYYY-MM-DD")
                    : "",
            })
                .then((response) => {
                    state.activities = response.activities.data;
                    state.pagination.total = response.activities.total;
                    state.settings = response.settings;
                })
                .catch((error) => {
                    handleError(error);
                })
                .always(() => {
                    state.loading = false;
                });
        };

        const handleClick = (activity, $event) => {
            const route = $event.target.getAttribute("href");

            if (!route) {
                return false;
            }

            const routerData = {
                    name: "view_ticket",
                    params: { ticket_id: activity.object_id },
                };

            if (state.settings.open_link_in_new_tab == 'yes' && route == '#view_ticket') {
                const routeData = router.resolve(routerData);

                window.open(routeData.href, '_blank');
            } else {
                router.push(routerData);
            }
        };

        onMounted(() => {
            fetchActivities();
            setTitle('Activities');
        });

        return {
            ...toRefs(state),
            fetchActivities,
            handleClick,
            translate,
            humanDiffTime

        }
    },
};
</script>

<style scoped>
.fs_box_wrapper .fs_box_header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-direction: row;
    font-size: 16px;
}
</style>
