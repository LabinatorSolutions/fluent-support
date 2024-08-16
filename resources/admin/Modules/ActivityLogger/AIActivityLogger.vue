<template>
    <div>
        <div class="fs_ai_activity_box_header">
            <div class="fs_ai_filters">
                <div class="fs_ai_activities_select_agents">
                    <el-select
                        clearable
                        filterable
                        size="small"
                        @change="fetchAIActivities()"
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
                </div>
                <div class="fs_ai_activities_date_picker">
                    <el-date-picker
                        @change="fetchAIActivities"
                        v-model="date_range"
                        type="daterange"
                        :range-separator="translate('To')"
                        :start-placeholder="translate('Start')"
                        :end-placeholder="translate('End')"
                    />
                </div>
            </div>
            <div class="fs_ai_box_actions">
                <div class="fs_ai_activities_refresh">
                    <el-button
                    v-loading="loading"
                    @click="fetchAIActivities()"
                    icon="Refresh"
                ></el-button>
                </div>
                <el-button
                    v-if="me.permissions.indexOf('fst_manage_settings') != -1"
                    @click="showSettingsModal = true"
                    type="default"
                    icon="Setting"
                ></el-button>
            </div>
        </div>
        <div>
            <el-table :data="aiActivities" style="width: 100%">
                <el-table-column prop="person.full_name" label="Agent" width="180" />
                <el-table-column label="Ticket">
                    <template v-slot="scope">
                        <router-link v-if="scope.row.ticket && scope.row.ticket.id"
                                     class="fs_ticket_link_preview"
                                     :to="{ name: 'view_ticket', params: { ticket_id: scope.row.ticket.id } }">
                            <p>{{ scope.row.ticket.title }}</p>
                        </router-link>
                        <span v-else class="fs_ticket_not_available">#{{ scope.row.ticket_id }} - This ticket has been deleted or is not available.</span>
                    </template>
                </el-table-column>
                <el-table-column :width="160" prop="model_name" label="Model" />
                <el-table-column :width="90" prop="tokens" label="Tokens" />
                <el-table-column :min-width="230" prop="prompt" label="Prompt" />
            </el-table>

            <div
                style="padding-bottom: 20px"
                class="fframe_pagination_wrapper"
                v-if="aiActivities.length"
            >
                <pagination
                    @fetch="fetchAIActivities()"
                    :pagination="pagination"
                />
            </div>
        </div>
        <el-dialog
            v-model="showSettingsModal"
            :title="translate('Activity Log Settings')"
            width="60%"
            :append-to-body="true"
        >
            <AIActivitySettings
                @updated="
                    showSettingsModal = false;
                    fetchAIActivities();
                "
            />
        </el-dialog>
    </div>
</template>

<script>
import Pagination from "../../Pieces/Pagination";
import AIActivitySettings from "./_AIActivitySettings";
import dayjs from "dayjs";
import { useRouter } from "vue-router";
import {  onMounted, reactive, toRefs } from "vue";
import { useFluentHelper } from "@/admin/Composable/FluentFrameworkHelper";
import ActivitySettings from "@/admin/Modules/ActivityLogger/_ActivitySettings.vue";
export default {
    name: "ActivityLogger",
    components:{
        ActivitySettings,
        AIActivitySettings,
        Pagination,
    },

    setup() {
        const router = useRouter();
        const {
            appVars,
            get,
            translate,
        } = useFluentHelper();

        const state = reactive({
            aiActivities: [],
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


        const fetchAIActivities = () => {
            state.loading = true;
            get("ai-activity-logger", {
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
                state.aiActivities = response.data;
                state.pagination.total = response.total;
                state.loading = false;
            })
        };

        onMounted(() => {
            fetchAIActivities();
        });

        return {
            ...toRefs(state),
            fetchAIActivities,
            translate,
            appVars
        }
    }
}
</script>

