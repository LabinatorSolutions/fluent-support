<template>
    <div style="width: 100%; height: 400px" v-loading="fetching && !!chartData">
        <bar-chart-base
            v-if="chartData"
            :chartData="chartData"
        ></bar-chart-base>
        <el-skeleton v-else-if="fetching" animated style="height: 100%;" aria-busy="true">
            <template #template>
                <div style="display: flex; align-items: flex-end; gap: 16px; height: 100%; padding: 12px 24px 24px 16px; box-sizing: border-box;">
                    <el-skeleton-item variant="rect" style="flex: 1; height: 55%;" />
                    <el-skeleton-item variant="rect" style="flex: 1; height: 80%;" />
                    <el-skeleton-item variant="rect" style="flex: 1; height: 40%;" />
                    <el-skeleton-item variant="rect" style="flex: 1; height: 95%;" />
                    <el-skeleton-item variant="rect" style="flex: 1; height: 65%;" />
                    <el-skeleton-item variant="rect" style="flex: 1; height: 30%;" />
                    <el-skeleton-item variant="rect" style="flex: 1; height: 70%;" />
                    <el-skeleton-item variant="rect" style="flex: 1; height: 85%;" />
                    <el-skeleton-item variant="rect" style="flex: 1; height: 50%;" />
                    <el-skeleton-item variant="rect" style="flex: 1; height: 60%;" />
                    <el-skeleton-item variant="rect" style="flex: 1; height: 90%;" />
                    <el-skeleton-item variant="rect" style="flex: 1; height: 45%;" />
                </div>
            </template>
        </el-skeleton>
    </div>
</template>

<script type="text/babel">
import BarChartBase from "./BarChartBase";

export default {
    name: "ResponseGrowth",
    props: ["date_range", "url", "agent_id", "product_id", "mailbox_id", "agent_group_id", "type", "compact"],
    components: {
        BarChartBase,
    },
    data() {
        return {
            fetching: true,
            stats: {},
            chartData: false,
            maxCumulativeValue: 0,
        };
    },
    methods: {
        async fetchReport() {
            this.fetching = true;
            const data = {
                date_range: this.date_range,
                agent_id: this.agent_id,
                product_id: this.product_id,
                mailbox_id: this.mailbox_id,
                agent_group_id: this.agent_group_id,
                type: this.type
            };
            await this.$get(this.url + "/response-growth", data).then((response) => {
                this.setupChartItems(response.stats);
            }).always(() => {
                this.fetching = false;
            });
        },
        setupChartItems(stats) {
            const chartData = {
                labels: [],
                datasets: [],
            };

            const statData = [];
            Object.entries(stats || {}).forEach(([label, count]) => {
                chartData.labels.push(label);
                statData.push(parseInt(count));
            });
            const computedStyle = getComputedStyle(document.documentElement);
            const chartColor = computedStyle.getPropertyValue('--fs-chart-primary').trim() || '#0cbe7e';
            chartData.datasets.push({
                label: this.$t("Response Stats"),
                backgroundColor: chartColor,
                data: statData,
            });
            this.chartData = chartData;
        },
    },
    mounted() {
        this.fetchReport();
    },
    watch: {
        date_range: {
            handler(newVal) {
                if (newVal && newVal.length === 2) {
                    this.fetchReport();
                }
            },
            deep: true
        }
    },
};
</script>
