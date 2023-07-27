<template>
    <div class="fs_agents_report">
        <div v-if="has_pro" class="fs_box_wrapper" v-loading="loading">
            <el-row :gutter="30">
                <el-col :sm="24" :md="16" :lg="18">
                    <div class="fs_box">
                        <div class="fs_box_header">
                            <div class="fs_header_title">
                                <el-dropdown
                                    style="
                                        display: inline-block;
                                        cursor: pointer;
                                        line-height: 100%;
                                    "
                                    @command="handleComponentChange"
                                    trigger="hover"
                                >
                                    <span class="el-dropdown-link">
                                        {{ chartMaps[currently_showing] }}
                                        <el-icon> <ArrowDown /> </el-icon>
                                    </span>
                                    <template #dropdown>
                                        <el-dropdown-menu>
                                            <el-dropdown-item
                                                v-for="(
                                                    mapName, mapKey
                                                ) in chartMaps"
                                                :key="mapKey"
                                                :command="mapKey"
                                            >
                                                {{ mapName }}
                                            </el-dropdown-item>
                                        </el-dropdown-menu>
                                    </template>
                                </el-dropdown>
                                <div class="fs_product_report_rh_filter">
                                    <el-select
                                        clearable
                                        filterable
                                        placeholder="All Product"
                                        @change="filterReport"
                                        v-model="product"
                                        class="fs_report_by_product"
                                    >
                                        <el-option
                                            v-for="product in appVars.support_products"
                                            :key="product.id"
                                            :value="product.id"
                                            :label="product.title"
                                        ></el-option>
                                    </el-select>

                                    <el-date-picker
                                        v-model="date_range"
                                        type="daterange"
                                        :range-separator="translate('To')"
                                        :start-placeholder="translate('Start')"
                                        :end-placeholder="translate('End')"
                                        :unlink-panels="true"
                                        @change="filterReport"
                                        value-format="YYYY-MM-DD"
                                    >
                                    </el-date-picker>
                                </div>
                            </div>
                        </div>
                        <div class="fs_box_body">
                            <component
                                v-if="showing_charts"
                                :is="currently_showing"
                                :date_range="date_range"
                                :url="'product-reports'"
                                :product_id="product"
                            ></component>
                        </div>
                    </div>
                    <product-report-summary
                        :url="'product-reports/product-reports-summary'"
                        :show_settings="true"
                        :show_export_btn="true"
                    />
                </el-col>
            </el-row>
        </div>
        <div class="fs_narrow_promo" style="background: white" v-else>
            <h3>{{ translate("get_overall_reports") }}</h3>
            <p>{{ translate("pro_promo") }}</p>
            <a
                target="_blank"
                rel="noopener"
                href="https://fluentsupport.com"
                class="el-button el-button--success"
            >{{ translate("Upgrade To Pro") }}</a
            >
        </div>
    </div>
</template>

<script type="text/babel">
import TicketsChart from "./Charts/TicketsGrowth";
import ResponseChart from "./Charts/ResponseGrowth";
import ResolveChart from "./Charts/ResolveGrowth";
import ProductReportSummary from "./ProductReportSumary"
import { useFluentHelper } from "@/admin/Composable/FluentFrameworkHelper";
import { reactive, toRefs, onMounted, nextTick } from "vue";

export default {
    name: "ProductReports",
    components: {
        TicketsChart,
        ResponseChart,
        ResolveChart,
        ProductReportSummary
    },
    setup() {
        const { translate, handleError, setTitle } =
            useFluentHelper();

        const state = reactive({
            loading: false,
            stat_loading: false,
            overall_reports: {},
            currently_showing: "tickets-chart",
            date_range: ["", ""],
            showing_charts: true,
            chartMaps: {
                "tickets-chart": "Ticket Stats",
                "resolve-chart": "Resolve Stats",
                "response-chart": "Response Stats",
            },
            product: "",
            today_reports: {},
        });

        const handleComponentChange = (item) => {
            state.currently_showing = item;
        };

        const filterReport = () => {
            const current = state.currently_showing;
            state.currently_showing = {
                render: () => {},
            };

            nextTick(() => {
                state.currently_showing = current;
            })
        };

        onMounted(() => {
            setTitle("Reports");
        });

        return {
            ...toRefs(state),
            translate,
            handleComponentChange,
            filterReport,
        };
    },
};
</script>

<style lang="scss">
.fs_product_report_rh_filter {
    display: flex;
    align-items: center;
    .fs_report_by_product {
        width: auto;
        margin-right: 0.3em;
    }
    @media (max-width: 767px) {
        display: flex;
        flex-direction: column;
        flex-wrap: nowrap;
        justify-content: center;
        padding: 0.7em;
        .fs_report_by_product {
            width: auto;
            margin-right: 0;
            margin-bottom: 0.4em;
        }
    }
}
</style>
