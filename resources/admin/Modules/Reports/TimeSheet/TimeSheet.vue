<template>
    <div v-if="has_pro">
        <div class="fs_inside_menu_component_header">
            <div class="fs_component_head">
                <h3 class="fs_page_title">{{ $t("Time Sheet") }}</h3>
            </div>
            <div class="fs_box_actions">
                <div class="fs_date_button_group">
                    <div class="fs_date_button_group_item fs_date_picker_wrapper">
                        <div class="fs_date_display">
                            <IconPack icon-key="calendar" :width="20" :height="20" class="fs_calendar_icon" />
                            <span v-if="formattedDateRange" class="fs_date_text">{{ formattedDateRange }}</span>
                            <span v-else class="fs_date_placeholder">{{ $t('Select date range') }}</span>
                        </div>
                        <el-date-picker
                            v-model="dateRange"
                            type="daterange"
                            @change="handleDateRangeChange"
                            @calendar-change="handleCalendarChange"
                            range-separator="To"
                            :disabled-date="disableLargeRange"
                            value-format="YYYY-MM-DD"
                            :start-placeholder="$t('Start date')"
                            :end-placeholder="$t('End date')"
                            :shortcuts="shortcuts"
                            class="fs_date_range_picker"
                        />
                    </div>
                </div>
                <div class="fs_time_sheet_export">
                    <el-button type="default" @click="exportReport">
                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M5.25 1.773V3.273H1.5V13.773H13.5V7.023H15V14.523C15 14.7219 14.921 14.9127 14.7803 15.0533C14.6397 15.194 14.4489 15.273 14.25 15.273H0.75C0.551088 15.273 0.360322 15.194 0.21967 15.0533C0.0790176 14.9127 0 14.7219 0 14.523V2.523C0 2.32409 0.0790176 2.13332 0.21967 1.99267C0.360322 1.85202 0.551088 1.773 0.75 1.773H5.25ZM12.7125 3.273L10.5 1.0605L11.5605 0L15.5655 4.005C15.6284 4.06793 15.6711 4.14808 15.6885 4.23532C15.7058 4.32256 15.6969 4.41298 15.6629 4.49515C15.6288 4.57733 15.5712 4.64758 15.4973 4.69704C15.4234 4.74649 15.3364 4.77292 15.2475 4.773H9C8.60218 4.773 8.22064 4.93104 7.93934 5.21234C7.65804 5.49364 7.5 5.87518 7.5 6.273V10.773H6V6.273C6 5.47735 6.31607 4.71429 6.87868 4.15168C7.44129 3.58907 8.20435 3.273 9 3.273H12.7125Z" fill="#525866"/>
                        </svg>

                        <span class="fs-ml-5">{{ $t('Export') }}</span>
                    </el-button>
                </div>
            </div>
        </div>

        <div class="fs_report_table_container">
            <div class="fs_table_header">
                <div class="fs_box_actions">
                    <div class="fs_time_sheet_filter_panel">
                        <el-select
                            v-if="status === 'byTickets'"
                            v-model="selectedMailBoxes"
                            @change="fetchData"
                            :placeholder="$t('Select Mailboxes')"
                            clearable
                            filterable
                            multiple
                            collapse-tags
                            class="fs_time_sheet_select fs_staff_filter fs_select_field"
                        >
                            <el-option
                                v-for="mailBox in allMailBoxes"
                                :key="mailBox.key"
                                :label="mailBox.label"
                                :value="mailBox.key"
                            />
                        </el-select>

                        <el-select
                            v-if="status === 'byAgents'"
                            v-model="selectedAgent"
                            @change="fetchData"
                            :placeholder="$t('Select an Agent')"
                            clearable
                            filterable
                            multiple
                            collapse-tags
                            class="fs_time_sheet_select fs_staff_filter fs_select_field"
                        >
                            <el-option
                                v-for="agent in allAgents"
                                :key="agent.key"
                                :label="agent.label"
                                :value="agent.key"
                            />
                        </el-select>

                        <el-select
                            v-if="status === 'byCustomers'"
                            v-model="selectedCustomer"
                            @change="fetchData"
                            :placeholder="$t('Select a customer')"
                            clearable
                            filterable
                            multiple
                            collapse-tags
                            class="fs_time_sheet_select fs_staff_filter fs_select_field"
                        >
                            <el-option
                                v-for="customer in allCustomers"
                                :key="customer.key"
                                :label="customer.label"
                                :value="customer.key"
                            />
                        </el-select>
                    </div>
                    <div class="fs_status_tabs">
                        <div class="fs_segmented_control">
                            <button
                                v-for="(statusKey, statusName) in statusFilters"
                                :key="statusName"
                                @click="setStatus(statusName)"
                                :class="['fs_segment_button', { 'fs_segment_active': status === statusName }]"
                            >
                                {{ statusKey }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <template v-if="appReady">
                <ByTickets
                    ref="byTickets"
                    v-if="status === 'byTickets'"
                    :date_range="dateRange"
                    :tasks="ticketsData.tasks"
                    :timeSheets="ticketsData.timeSheets"
                    :dateLabels="ticketsData.dateLabels"
                    :totalMinutes="ticketsData.totalMinutes"
                    :isLoaded="ticketsData.isLoaded"
                    :selectedMailBoxes="selectedMailBoxes"
                />
                <ByAgents
                    ref="byAgents"
                    v-else-if="status === 'byAgents'"
                    :date_range="dateRange"
                    :agents="agentsData.agents"
                    :timeSheets="agentsData.timeSheets"
                    :dateLabels="agentsData.dateLabels"
                    :totalMinutes="agentsData.totalMinutes"
                    :isLoaded="agentsData.isLoaded"
                    :selectedAgent="selectedAgent"
                />
                <ByCustomers
                    ref="byCustomers"
                    v-else-if="status === 'byCustomers'"
                    :date_range="dateRange"
                    :customers="customersData.customers"
                    :timeSheets="customersData.timeSheets"
                    :dateLabels="customersData.dateLabels"
                    :totalMinutes="customersData.totalMinutes"
                    :isLoaded="customersData.isLoaded"
                    :selectedCustomer="selectedCustomer"
                />
            </template>
        </div>


    </div>

    <NarrowPromo
        v-else
        :heading="$t('get_overall_reports')"
        :description="$t('pro_promo')"
        :button-text="$t('Upgrade To Pro')"
    />
</template>

<script type="text/babel">
import ByTickets from "./ByTickets";
import ByAgents from "./ByAgents";
import ByCustomers from "./ByCustomers";
import IconPack from "@/admin/Components/IconPack";
import NarrowPromo from "@/admin/Components/NarrowPromo";
import dayjs from 'dayjs';
import { shortcuts, additionalShortcuts } from "../Utils/dateShortCuts";
import { formatDateRangeForDisplay, getDefaultDateRange } from "../Utils/reportHelpers";

export default {
    name: "TimeSheet",
    components: {
        ByTickets,
        ByAgents,
        ByCustomers,
        NarrowPromo,
        IconPack
    },
    props: ["url", "date_range"],
    data() {
        const initialDateRange = this.validateAndAdjustDateRange(this.date_range);
        return {
            pickerStartDate: null, // Only set during active selection, not from existing ranges
            dateRange: initialDateRange,
            appReady: false,
            shortcuts: additionalShortcuts,
            statusFilters: {
                byTickets: this.$t("By Tickets"),
                byAgents: this.$t("By Agents"),
                byCustomers: this.$t("By Customers"),
            },
            status: "byTickets",
            selectedMailBoxes: [],
            allMailBoxes: [],
            ticketsData: {
                tasks: [],
                timeSheets: {},
                dateLabels: [],
                totalMinutes: 0,
                isLoaded: false,
            },
            // Agents data
            selectedAgent: [],
            allAgents: [],
            agentsData: {
                agents: [],
                timeSheets: {},
                dateLabels: [],
                totalMinutes: 0,
                isLoaded: false,
            },
            // Customers data
            selectedCustomer: [],
            allCustomers: [],
            customersData: {
                customers: [],
                timeSheets: {},
                dateLabels: [],
                totalMinutes: 0,
                isLoaded: false,
            }
        };
    },
    computed: {
        formattedDateRange() {
            return formatDateRangeForDisplay(this.dateRange);
        },
    },
    watch: {
        date_range: {
            handler(newVal) {
                this.dateRange = this.validateAndAdjustDateRange(newVal);
                this.pickerStartDate = null;
                this.fetchData();
            },
            deep: true
        }
    },
    methods: {
        /**
         * Validates the date range format and enforces a max span of 1 month.
         * @param {Array} dateRange - Array of two date strings [start, end]
         * @returns {Array} Validated/clamped date range
         */
        validateAndAdjustDateRange(dateRange) {
            if (!dateRange || dateRange.length !== 2 || !dateRange[0] || !dateRange[1]) {
                return dateRange;
            }

            const startDate = dayjs(dateRange[0]);
            const endDate = dayjs(dateRange[1]);

            if (!startDate.isValid() || !endDate.isValid()) {
                return dateRange;
            }

            // Ensure end date is not before start date
            if (endDate.isBefore(startDate)) {
                return dateRange;
            }

            // Enforce maximum one-month span (inclusive)
            const maxEndDate = startDate.add(1, 'month');
            if (endDate.isAfter(maxEndDate)) {
                return [startDate.format('YYYY-MM-DD'), maxEndDate.format('YYYY-MM-DD')];
            }

            return dateRange;
        },

        handleCalendarChange([startDate, endDate]) {
            if (startDate && !endDate) {
                const normalizedDate = startDate instanceof Date ? new Date(startDate.getFullYear(), startDate.getMonth(), startDate.getDate()) : new Date(startDate);
                this.pickerStartDate = normalizedDate;
            } else {
                this.pickerStartDate = null;
            }
        },

        disableLargeRange(date) {
            const normalizedDate = new Date(date.getFullYear(), date.getMonth(), date.getDate());
            const today = new Date();
            today.setHours(0, 0, 0, 0);

            // Disallow selecting future dates
            if (normalizedDate.getTime() > today.getTime()) {
                return true;
            }

            // When a start date is selected, block dates more than one month ahead
            if (this.pickerStartDate) {
                const start = dayjs(this.pickerStartDate).startOf('day');
                const candidate = dayjs(normalizedDate).startOf('day');
                const maxEnd = start.add(1, 'month');

                if (candidate.isAfter(maxEnd)) {
                    return true;
                }
            }

            return false;
        },

        handleDateRangeChange() {
            this.dateRange = this.validateAndAdjustDateRange(this.dateRange);
            this.pickerStartDate = null;
            this.$emit('date-change', this.dateRange);
            this.fetchData();
        },

        setStatus(statusValue) {
            this.status = statusValue;
            this.fetchData();
        },

        async fetchData() {
            if (!this.dateRange || this.dateRange.length !== 2) {
                return;
            }

            if (this.status === 'byTickets') {
                await this.fetchReportsByTickets();
            } else if (this.status === 'byAgents') {
                await this.fetchReportsByAgents();
            } else if (this.status === 'byCustomers') {
                await this.fetchReportsByCustomers();
            }
        },

        async fetchReportsByTickets() {
            this.ticketsData.isLoaded = false;
            const params = {
                mailbox_id: this.selectedMailBoxes,
                date_range: this.dateRange
            };
            try {
                const response = await this.$get('reports/timesheet/by-tickets', params);
                this.ticketsData.tasks = response.tickets;
                this.ticketsData.timeSheets = response.time_sheets;
                this.ticketsData.dateLabels = response.date_labels;
                this.ticketsData.totalMinutes = response.total_minutes;

                this.allMailBoxes = response.mail_boxes.map(mailBox => ({
                    key: mailBox.id,
                    label: mailBox.name
                }));

            } catch (error) {
                this.$handleError(error)
            } finally {
                this.ticketsData.isLoaded = true;
            }
        },

        async fetchReportsByAgents() {
            this.agentsData.isLoaded = false;
            const params = {
                agent_id: this.selectedAgent,
                date_range: this.dateRange
            };

            try {
                const response = await this.$get('reports/timesheet/by-agents', params);
                this.agentsData.agents = response.agents;
                this.agentsData.timeSheets = response.time_sheets;
                this.agentsData.dateLabels = response.date_labels;
                this.agentsData.totalMinutes = response.total_minutes;

                this.allAgents = response.all_agents.map(agent => ({
                    key: agent.id,
                    label: agent.full_name
                }));

            } catch (error) {
                this.$handleError(error);
            } finally {
                this.agentsData.isLoaded = true;
            }
        },

        async fetchReportsByCustomers() {
            this.customersData.isLoaded = false;
            try {
                const params = {
                    customer_id: this.selectedCustomer,
                    date_range: this.dateRange
                };

                const response = await this.$get('reports/timesheet/by-customers', params);
                this.customersData.customers = response.customers;
                this.customersData.timeSheets = response.time_sheets;
                this.customersData.dateLabels = response.date_labels;
                this.customersData.totalMinutes = response.total_minutes;

                this.allCustomers = response.all_customers.map(customer => ({
                    key: customer.id,
                    label: customer.full_name
                }));

            } catch (error) {
                this.$handleError(error);
            } finally {
                this.customersData.isLoaded = true;
            }
        },

        exportReport() {
            if (this.status === 'byTickets') {
                if (this.$refs.byTickets && typeof this.$refs.byTickets.exportReport === 'function') {
                    this.$refs.byTickets.exportReport();
                }
            } else if (this.status === 'byAgents') {
                if (this.$refs.byAgents && typeof this.$refs.byAgents.exportReport === 'function') {
                    this.$refs.byAgents.exportReport();
                }
            } else if (this.status === 'byCustomers') {
                if (this.$refs.byCustomers && typeof this.$refs.byCustomers.exportReport === 'function') {
                    this.$refs.byCustomers.exportReport();
                }
            }
        },
    },
    mounted() {
        if (this.has_pro) {
            this.appReady = true;
            this.fetchData();
        }
    },
};
</script>

