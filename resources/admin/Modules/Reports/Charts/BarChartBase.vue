<template>
    <e-chart :option="buildOption"></e-chart>
</template>

<script type="text/babel">
import EChart from './EChart.vue';
import {categoryAxes, tooltipStyle} from './echarts';

// Vertical bar chart. chartData: { labels: [], datasets: [{ label, data, backgroundColor }] }
export default {
    name: 'BarChartBase',
    components: {
        EChart
    },
    props: {
        chartData: {
            type: Object,
            required: true
        },
        stacked: {
            type: Boolean,
            default: false
        },
        // Bars are this wide when there is room and narrower when the range is long.
        barMaxWidth: {
            type: Number,
            default: 8
        },
        barRadius: {
            type: Number,
            default: 0
        }
    },
    methods: {
        buildOption() {
            const radius = [this.barRadius, this.barRadius, 0, 0];
            return {
                animationDuration: 300,
                grid: {left: 16, right: 24, top: 16, bottom: 8, containLabel: true},
                tooltip: {
                    trigger: 'axis',
                    axisPointer: {type: 'shadow'},
                    ...tooltipStyle()
                },
                ...categoryAxes(this.chartData.labels),
                series: this.chartData.datasets.map(dataset => ({
                    type: 'bar',
                    name: dataset.label,
                    data: dataset.data,
                    stack: this.stacked ? 'total' : undefined,
                    barMaxWidth: this.barMaxWidth,
                    itemStyle: {
                        color: dataset.backgroundColor,
                        borderRadius: radius
                    }
                }))
            };
        }
    }
};
</script>
