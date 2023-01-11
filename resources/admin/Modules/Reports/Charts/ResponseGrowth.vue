<template>
    <div style="width: 100%; height: 400px">
        <bar-chart-base
            v-if="chartData"
            :chartData="chartData"
            :chartOptions="chartOptions"
        ></bar-chart-base>
    </div>
</template>

<script type="text/babel">
import each from "lodash/each";
import BarChartBase from "./BarChartBase";
import { useFluentHelper } from "@/admin/Composable/FluentFrameworkHelper";
import { reactive, toRefs, onMounted } from "vue";
export default {
    name: "ResponseGrowth",
    props: ["date_range", "url", "agent_id"],
    components: {
        BarChartBase,
    },

    setup(props) {
        
        const { get } = useFluentHelper();

        const state = reactive({
            fetching: false,
            stats: {},
            chartData: false,
            maxCumulativeValue: 0,
            chartOptions: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    yAxes: [
                        {
                            id: "byDate",
                            type: "linear",
                            position: "left",
                            gridLines: {
                                drawOnChartArea: false,
                            },
                            ticks: {
                                beginAtZero: true,
                                userCallback: function (label, index, labels) {
                                    // when the floored value is the same as the value we have a whole number
                                    if (Math.floor(label) === label) {
                                        return label;
                                    }
                                },
                            },
                        },
                    ],
                    xAxes: [
                        {
                            gridLines: {
                                drawOnChartArea: false,
                            },
                            ticks: {
                                beginAtZero: true,
                                autoSkip: true,
                                maxTicksLimit: 10,
                            },
                        },
                    ],
                },
                drawBorder: false,
                layout: {
                    padding: {
                        left: 0,
                        right: 0,
                        top: 0,
                        bottom: 20,
                    },
                },
            },
        });

        const fetchReport = async () => {
            state.fetching = true;
            await get(props.url + "/response-growth", {
                date_range: props.date_range,
                agent_id: props.agent_id,
            }).then((response) => {
                setupChartItems(response.stats);
            });
        };

        const setupChartItems = (stats) => {
            const chartData = {
                labels: [],
                datasets: [],
            };

            const statData = [];
            each(stats, (count, label) => {
                chartData.labels.push(label);
                statData.push(parseInt(count));
            });
            chartData.datasets.push({
                label: "Response Stats",
                backgroundColor: "#0cbe7e",
                data: statData,
            });
            state.chartData = chartData;
        };

        onMounted(() => {
            fetchReport();
        });

        return {
            ...toRefs(state),
            fetchReport,
            setupChartItems,
        };
    },
};
</script>
