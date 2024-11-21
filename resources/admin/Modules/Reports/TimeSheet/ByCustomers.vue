<template>
    <div class="fs_time_sheet_box_header">
        <div class="fs_time_sheet_box_head">
            {{translate("Customer Time Sheet Report")}}
        </div>
        <div class="fs_time_sheet_box_actions">
            <el-select
                v-model="selectedCustomer"
                @change="fetchReportsByCustomers"
                placeholder="Select a customer"
                class="fs_time_sheet_select"
                clearable
            >
                <el-option
                    v-for="customer in allCustomers"
                    :key="customer.key"
                    :label="customer.label"
                    :value="customer.key"
                />

            </el-select>
            <div class="fs_time_sheet_export">
                <el-button type="default" @click="openSettings=true">
                    <el-icon>
                        <Download/>
                    </el-icon>
                    {{ translate('Export') }}
                </el-button>
            </div>
        </div>
    </div>

    <div class="fs_time_sheet_box_body">
        <el-table v-if="isLoaded" :data="customers" style="width: 100%">
            <el-table-column fixed="left" prop="customer" label="Customer" min-width="120">
                <template #default="{ row }">
                    <div class="fs_time_sheet_person">
                        <el-avatar :src="row.photo" size="small"></el-avatar>
                        <span>{{ row.full_name }}</span>
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
                    <CustomerDataSheetPop
                        :customer_id="row.id"
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
                                v-model="includeOrExcludeCustomers"
                                :data="allCustomers"
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
        <el-skeleton :animated="true" :rows="10" v-else/>
    </div>
</template>

<script>
import {onMounted, ref} from 'vue';
import CustomerDataSheetPop from './_CustomerDataSheetPop.vue';
import {useFluentHelper} from '@/admin/Composable/FluentFrameworkHelper';
import Modal from "@/admin/Pieces/Modal.vue";
import {timesheetUtils} from "@/admin/Modules/Reports/TimeSheet/Pieces/TimeSheetUtils";

export default {
    name: 'ByCustomers',
    components: {Modal, CustomerDataSheetPop},
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
        const openSettings = ref(false);
        const includeOrExcludeCustomers = ref([]);

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
                selectedItems: includeOrExcludeCustomers.value,
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
            openSettings,
            includeOrExcludeCustomers,
            translate,
            exportReport
        };
    }
};
</script>


