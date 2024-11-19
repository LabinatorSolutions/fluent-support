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

export default {
    name: 'ByCustomers',
    components: {Modal, CustomerDataSheetPop},
    props: {
        mailbox_id: {
            type: String,
            required: true
        },
        date_range: {
            type: Array,
            required: true
        }
    },
    setup(props) {
        const {get, handleError, smartDate, translate} = useFluentHelper();
        const allCustomers = ref([]); // Holds all customer data for dropdown
        const customers = ref([]); // Holds filtered customer data
        const totalMinutes = ref(0);
        const timeSheets = ref({});
        const dateLabels = ref([]);
        const isLoaded = ref(false);
        const selectedCustomer = ref(null); // For selected customer ID
        const openSettings = ref(false);
        const includeOrExcludeCustomers = ref([]);

        // Fetch data based on customer selection
        const fetchReportsByCustomers = async () => {
            isLoaded.value = false;
            try {
                const params = {
                    mailbox_id: props.mailbox_id,
                    date_range: props.date_range
                };
                // // Include the selected customer ID in the API request if one is selected
                if (selectedCustomer.value) {
                    console.log(selectedCustomer)
                    params.customer_id = selectedCustomer.value;
                }

                const response = await get('reports/timesheet/by-customers', params);
                customers.value = response.customers;
                timeSheets.value = response.time_sheets;
                dateLabels.value = response.date_labels;
                totalMinutes.value = response.totalMinutes;

                // Load all customers for dropdown only once
                if (!params.customer_id) {
                    allCustomers.value = response.customers.map(customer => ({
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

        const getUserTotal = (userId) => {
            const minutes = dateLabels.value.reduce((acc, date) => {
                const sheets = timeSheets.value?.[date]?.[userId] || [];
                return acc + sheets.reduce((acc, sheet) => acc + sheet.billable_minutes, 0);
            }, 0);
            return formatMinutes(minutes);
        };

        const formatMinutes = (minutes) => {
            let intMinutes = parseInt(minutes);
            if (!intMinutes) return '--';

            const hours = Math.floor(intMinutes / 60);
            intMinutes %= 60;

            if (!hours) return `${intMinutes}m`;
            if (!intMinutes) return `${hours}h`;
            return `${hours}h ${intMinutes}m`;
        };

        const exportReport = () => {
            location.href = `${window.fluentSupportAdmin.ajaxurl}?${new URLSearchParams({
                action: "fluent_support_export_customers_timesheet",
                selectedCustomers: includeOrExcludeCustomers.value,
                dateRange: props.date_range,
            }).toString()}`;
        };

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


