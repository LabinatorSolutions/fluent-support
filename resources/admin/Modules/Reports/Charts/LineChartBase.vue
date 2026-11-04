<template>
    <e-chart :option="buildOption"></e-chart>
</template>

<script type="text/babel">
import EChart from './EChart.vue';
import {categoryAxes, tooltipStyle, withOpacity} from './echarts';

// Smooth line chart. chartData: { labels: [], datasets: [{ label, data, color, fill? }] }
// A dataset with fill: true gets a faint area under its line.
export default {
    name: 'LineChartBase',
    components: {
        EChart
    },
    props: {
        chartData: {
            type: Object,
            required: true
        }
    },
    methods: {
        buildOption() {
            return {
                animationDuration: 300,
                grid: {left: 8, right: 16, top: 16, bottom: 8, containLabel: true},
                tooltip: {
                    trigger: 'axis',
                    ...tooltipStyle()
                },
                ...categoryAxes(this.chartData.labels),
                series: this.chartData.datasets.map(dataset => ({
                    type: 'line',
                    name: dataset.label,
                    data: dataset.data,
                    smooth: true,
                    showSymbol: false,
                    symbolSize: 8,
                    lineStyle: {width: 2.5, color: dataset.color},
                    itemStyle: {color: dataset.color},
                    areaStyle: dataset.fill ? {color: withOpacity(dataset.color, 0.12)} : undefined
                }))
            };
        }
    }
};
</script>
