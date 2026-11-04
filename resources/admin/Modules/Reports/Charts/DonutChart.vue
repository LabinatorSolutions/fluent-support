<template>
    <div class="fs_donut_chart">
        <e-chart
            :option="buildOption"
            @mouseover="handleHover"
            @mouseout="hovered = null"
        ></e-chart>
        <div class="fs_donut_center">
            <span class="fs_donut_center_label">{{ hovered ? hovered.name : totalLabel }}</span>
            <span class="fs_donut_center_value">{{ formatValue(hovered ? hovered.value : total) }}</span>
        </div>
    </div>
</template>

<script type="text/babel">
import EChart from './EChart.vue';
import {cssVar} from './echarts';

// Donut chart with the total in the middle; hovering a slice shows that slice instead.
// items: [{ name, value, color }]
export default {
    name: 'DonutChart',
    components: {
        EChart
    },
    props: {
        items: {
            type: Array,
            default: () => []
        },
        total: {
            type: [Number, String],
            default: 0
        },
        totalLabel: {
            type: String,
            required: true
        },
        formatValue: {
            type: Function,
            default: value => value
        }
    },
    data() {
        return {
            hovered: null
        };
    },
    methods: {
        buildOption() {
            // With nothing to show, ECharts splits the ring evenly; draw one muted ring instead.
            const isEmpty = !this.items.some(item => Number(item.value) > 0);
            const data = isEmpty
                ? [{value: 1, itemStyle: {color: cssVar('--fs-stroke-soft', '#E1E4EA')}}]
                : this.items.map(item => ({
                    name: item.name,
                    value: item.value,
                    itemStyle: {color: item.color}
                }));

            return {
                animationDuration: 300,
                series: [{
                    type: 'pie',
                    radius: ['70%', '90%'],
                    silent: isEmpty,
                    label: {show: false},
                    labelLine: {show: false},
                    emphasis: {scale: true, scaleSize: 4},
                    itemStyle: {borderColor: cssVar('--fs-bg-primary', '#FFFFFF'), borderWidth: 2},
                    data
                }]
            };
        },
        handleHover(event) {
            if (event.componentType === 'series') {
                this.hovered = {name: event.name, value: event.value};
            }
        }
    }
};
</script>
