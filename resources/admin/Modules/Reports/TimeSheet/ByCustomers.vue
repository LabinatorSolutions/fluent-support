<template>
    <div class="fs_time_sheet" v-if="isLoaded" >
        <div class="fs_time_sheet_box_header">
            <div class="fs_time_sheet_box_head">
                {{translate("Customer Time Sheet Report")}}
            </div>
            <div class="fs_time_sheet_box_actions">
                <el-select
                    v-model="selectedCustomer"
                    @change="fetchReportsByCustomers"
                    placeholder="Select a customer"
                    clearable
                    filterable
                    multiple
                    class="fs_time_sheet_select"
                >
                    <el-option
                        v-for="customer in allCustomers"
                        :key="customer.key"
                        :label="customer.label"
                        :value="customer.key"
                    />

                </el-select>
                <div class="fs_time_sheet_export">
                    <el-button type="default" @click="exportReport">
                        <el-icon>
                            <Download/>
                        </el-icon>
                        {{ translate('Export') }}
                    </el-button>
                </div>
            </div>
        </div>
        <div class="fs_time_sheet_box_body">
            <el-table :data="customers" style="width: 100%">
                <el-table-column fixed="left" prop="customer" label="Customer" min-width="120">
                    <template #default="{ row }">
                        <div class="fs_time_sheet_person">
                            <el-avatar :src="row.photo" size="small"></el-avatar>
                            <router-link
                                :to="{ name: 'view_customer', params: { customer_id: row.id }, }"
                                target="_blank"
                                style="text-decoration: none;">
                                <strong>{{ row.full_name }}</strong>
                            </router-link>
                        </div>
                    </template>
                </el-table-column>
                <el-table-column
                    :min-width="100"
                    v-for="date in dateLabels"
                    :key="date"
                    :prop="date"
                    :label="date"
                    min-width="100"
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
            </el-table>
        </div>
    </div>
    <div class="fs_time_sheet_skeleton"  v-else>
        <el-skeleton :animated="true" :rows="10"/>
    </div>
</template>

<script>
import {onMounted, ref} from 'vue';
import {useFluentHelper} from '@/admin/Composable/FluentFrameworkHelper';
import Modal from "@/admin/Pieces/Modal.vue";
import {timesheetUtils} from "@/admin/Modules/Reports/TimeSheet/Pieces/TimeSheetUtils";
import UserAgentDateSheetPop from "@/admin/Modules/Reports/TimeSheet/_UserAgentDateSheetPop.vue";

export default {
    name: 'ByCustomers',
    components: {UserAgentDateSheetPop, Modal},
    props: {
        date_range: {
            type: Array,
            required: true
        }
    },
    setup(props) {
        const {get, handleError, smartDate, translate} = useFluentHelper();
        const allCustomers = ref([]);
        const customers = ref([]);
        const totalMinutes = ref(0);
        const timeSheets = ref({});
        const dateLabels = ref([]);
        const isLoaded = ref(false);
        const selectedCustomer = ref(null);

        const fetchReportsByCustomers = async () => {
            isLoaded.value = false;
            try {
                const params = {
                    customer_id: selectedCustomer.value,
                    date_range: props.date_range
                };

                const response = await get('reports/timesheet/by-customers', params);
                customers.value = response.customers;
                timeSheets.value = response.time_sheets;
                dateLabels.value = response.date_labels;
                totalMinutes.value = response.total_minutes;

                if (!params.customer_id) {
                    allCustomers.value = response.all_customers.map(customer => ({
                        key: customer.id,
                        label: customer.full_name
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
                selectedItems: selectedCustomer.value,
                dateRange: props.date_range,
                action: "fluent_support_export_customers_timesheet",
                itemKey: "selectedCustomers"
            });
        }

        onMounted(() => fetchReportsByCustomers());

        return {
            allCustomers,
            customers,
            totalMinutes,
            timeSheets,
            dateLabels,
            isLoaded,
            fetchReportsByCustomers,
            getUserTotal,
            formatMinutes,
            smartDate,
            selectedCustomer,
            translate,
            exportReport
        };
    }
};
</script>


