// The one ECharts entry for the admin app. Register only the chart types and
// components a report uses; the full `echarts` entry ships every chart type.
// Import `echarts` from here, never from the `echarts` package directly.
import * as echarts from 'echarts/core';
import {BarChart, LineChart, PieChart} from 'echarts/charts';
import {TooltipComponent, GridComponent, DataZoomComponent} from 'echarts/components';
import {SVGRenderer} from 'echarts/renderers';

echarts.use([
    BarChart,
    LineChart,
    PieChart,
    TooltipComponent,
    GridComponent,
    DataZoomComponent,
    SVGRenderer
]);

export default echarts;

// Reads a --fs-* token at render time, so charts follow the dark theme.
export function cssVar(name, fallback) {
    const value = getComputedStyle(document.body).getPropertyValue(name).trim();
    return value || fallback;
}

export function withOpacity(color, opacity) {
    const hex = color.replace('#', '');
    if (!/^([0-9a-f]{3}|[0-9a-f]{6})$/i.test(hex)) {
        return color;
    }
    const full = hex.length === 3 ? hex.split('').map(c => c + c).join('') : hex;
    const value = parseInt(full, 16);
    return `rgba(${(value >> 16) & 255}, ${(value >> 8) & 255}, ${value & 255}, ${opacity})`;
}

export function tooltipStyle() {
    return {
        backgroundColor: cssVar('--fs-bg-primary', '#FFFFFF'),
        borderColor: cssVar('--fs-stroke-soft', '#E1E4EA'),
        borderWidth: 1,
        textStyle: {color: cssVar('--fs-text-primary', '#0E121B')}
    };
}

// Category x-axis and value y-axis shared by the bar and line charts:
// dashed horizontal grid lines, whole-number ticks, no vertical grid.
export function categoryAxes(labels) {
    const labelColor = cssVar('--fs-text-muted', '#99A0AE');
    const lineColor = cssVar('--fs-stroke-soft', '#E1E4EA');
    return {
        xAxis: {
            type: 'category',
            data: labels,
            axisLabel: {color: labelColor, hideOverlap: true},
            axisLine: {lineStyle: {color: lineColor}},
            axisTick: {show: false}
        },
        yAxis: {
            type: 'value',
            minInterval: 1,
            axisLabel: {color: labelColor},
            splitLine: {lineStyle: {color: lineColor, type: [4, 4]}}
        }
    };
}
