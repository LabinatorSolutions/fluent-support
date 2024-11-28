<template>
    <div class="fs_time_sheet" v-if="isLoaded" >
        <div class="fs_time_sheet_box_header">
            <div class="fs_time_sheet_box_head">
                {{translate("Agent Time Sheet Report")}}
            </div>
            <div class="fs_time_sheet_box_actions">
                <el-select
                    v-model="selectedAgent"
                    @change="fetchReportsByAgents"
                    placeholder="Select an Agent"
                    clearable
                    class="fs_time_sheet_select"
                >
                    <el-option
                        v-for="agent in allAgents"
                        :key="agent.key"
                        :label="agent.label"
                        :value="agent.key"
                    />
                </el-select>
                <div class="fs_time_sheet_export">
                    <el-button type="default" @click="openSettings = true">
                        <el-icon>
                            <Download/>
                        </el-icon>
                        {{ translate('Export') }}
                    </el-button>
                </div>
            </div>
        </div>
        <div class="fs_time_sheet_box_body">
            <el-table :data="agents" style="width: 100%">
                <el-table-column fixed="left" prop="agent" label="Agent" min-width="120">
                    <template #default="{ row }">
                        <div class="fs_time_sheet_person">
                            <el-avatar :src="row.photo" size="small"></el-avatar>
                            <a
                                :href="`/wp-admin/user-edit.php?user_id=${row.user_id}`"
                                target="_blank"
                                style="text-decoration: none; margin-left: 8px;">
                                <strong> {{ row.full_name }} </strong>
                            </a>
                        </div>
                    </template>
                </el-table-column>
                <el-table-column
                    :min-width="100"
                    v-for="date in dateLabels"
                    :key="date"
                    :prop="date"
                    :label="date"
                >
                    <template #header="{ column }">
                        <span>{{ smartDate(column.label) }}</span>
                    </template>
                    <template #default="{ row }">
                        <UserAgentDateSheetPop
                            :user_id="row.id"
                            :date="date"
                            :timeSheets="timeSheets"
                        />
                    </template>
                </el-table-column>
                <el-table-column label="Total" width="160" fixed="right">
                    <template #header="{ column }">
                        <span>Total ({{ formatMinutes(totalMinutes) }})</span>
                    </template>
                    <template #default="{ row }">
                        <strong>{{ getUserTotal(row.id) }}</strong>
                    </template>
                </el-table-column>

                <Teleport to="body">
                    <modal :show="openSettings" :title="translate('Include Customers in Time Sheet')"
                           @close="openSettings = false">
                        <template #body>
                            <div class="fs_summary_settings">
                                <el-row :gutter="20">
                                    <el-col :span="18">
                                        <span> {{ translate("If you don't select any customer then all the customers report will be displayed here otherwise it will show only selected agents report.") }}</span>
                                    </el-col>
                                </el-row>

                                <el-transfer
                                    v-model="includeOrExcludeAgents"
                                    :data="allAgents"
                                    :titles="['Available Agents', 'Selected Agents']"
                                    filterable
                                />
                            </div>
                        </template>
                        <template #footer>
                            <el-button @click="openSettings = false">{{ translate('Cancel') }}</el-button>
                            <el-button type="primary" @click="exportReport">{{ translate('Export') }}</el-button>
                        </template>
                    </modal>
                </Teleport>
            </el-table>

        </div>
    </div>

    <div class="fs_time_sheet_skeleton"  v-else>
        <el-skeleton :animated="true" :rows="10"/>
    </div>
</template>


<script>
import {onMounted, ref} from 'vue';
import UserAgentDateSheetPop from './_UserAgentDateSheetPop.vue'
import {useFluentHelper} from "@/admin/Composable/FluentFrameworkHelper";
import {timesheetUtils}from "@/admin/Modules/Reports/TimeSheet/Pieces/TimeSheetUtils"
import Modal from "@/admin/Pieces/Modal.vue";

export default {
    name: 'ByAgents',
    components: {Modal, UserAgentDateSheetPop},
    props: {
        date_range: {
            type: Array,
            required: true
        }
    },
    setup(props) {
        const {get, translate, handleError, setTitle, appVars, smartDate} = useFluentHelper();
        const agents = ref([]);
        const totalMinutes = ref(0);
        const timeSheets = ref({});
        const dateLabels = ref([]);
        const isLoaded = ref(false);
        const selectedAgent = ref(null);
        const allAgents = ref(null);
        const openSettings = ref(false);
        const includeOrExcludeAgents = ref([]);

        const fetchReportsByAgents = async () => {
            isLoaded.value = false;
            const params = {
                agent_id: selectedAgent.value,
                date_range: props.date_range
            };

            try {
                const response = await get('reports/timesheet/by-agents', params);
                agents.value = response.agents;
                timeSheets.value = response.time_sheets;
                dateLabels.value = response.date_labels;
                totalMinutes.value = response.total_minutes;

                if (!params.agent_id) {
                    allAgents.value = response.all_agents.map(agent => ({
                        key: agent.id,
                        label: agent.full_name
                    }));
                }
            } catch (error) {
                handleError(error);
            } finally {
                isLoaded.value = true;
            }
        };

        const getUserTotal = (userId) =>
            timesheetUtils.calculateEntityTotalMinutes(dateLabels.value, timeSheets.value, userId);

        const formatMinutes = (minutes) =>
            timesheetUtils.formatMinutes(minutes);
        const exportReport = () => {
            timesheetUtils.exportReport({
                selectedItems: includeOrExcludeAgents.value,
                dateRange: props.date_range,
                action: "fluent_support_export_agents_timesheet",
                itemKey: "selectedAgents"
            });
        }

        onMounted(fetchReportsByAgents);

        return {
            agents,
            totalMinutes,
            timeSheets,
            dateLabels,
            isLoaded,
            fetchReportsByAgents,
            getUserTotal,
            formatMinutes,
            smartDate,
            selectedAgent,
            allAgents,
            exportReport,
            openSettings,
            includeOrExcludeAgents,
            translate
        };
    }
};
</script>


