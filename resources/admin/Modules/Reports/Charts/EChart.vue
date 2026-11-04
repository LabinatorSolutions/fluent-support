<template>
    <div ref="chartEl" class="fs_echart" aria-hidden="true"></div>
</template>

<script type="text/babel">
import echarts from './echarts';

// Owns one ECharts instance: init, resize, theme change and dispose.
// `option` is a function so it runs again on theme change and reads the
// current --fs-* tokens; the chart also redraws when data it reads changes.
export default {
    name: 'EChart',
    props: {
        option: {
            type: Function,
            required: true
        }
    },
    emits: ['mouseover', 'mouseout', 'datazoom'],
    data() {
        return {
            themeVersion: 0
        };
    },
    computed: {
        resolvedOption() {
            // Read so a theme change re-runs option().
            this.themeVersion; // eslint-disable-line no-unused-expressions
            return this.option();
        }
    },
    watch: {
        resolvedOption(option) {
            if (this.chart) {
                this.chart.setOption(option, {notMerge: true});
            }
        }
    },
    methods: {
        handleThemeChange() {
            this.themeVersion++;
        },
        getInstance() {
            return this.chart;
        }
    },
    mounted() {
        this.chart = echarts.init(this.$refs.chartEl, null, {renderer: 'svg'});
        this.chart.on('mouseover', event => this.$emit('mouseover', event));
        this.chart.on('mouseout', event => this.$emit('mouseout', event));
        this.chart.on('datazoom', event => this.$emit('datazoom', event));
        this.chart.setOption(this.resolvedOption, {notMerge: true});

        this.resizeObserver = new ResizeObserver(() => {
            if (this.chart) {
                this.chart.resize();
            }
        });
        this.resizeObserver.observe(this.$refs.chartEl);
        window.addEventListener('fluent_theme_changed', this.handleThemeChange);
    },
    beforeUnmount() {
        window.removeEventListener('fluent_theme_changed', this.handleThemeChange);
        if (this.resizeObserver) {
            this.resizeObserver.disconnect();
        }
        if (this.chart) {
            this.chart.dispose();
            this.chart = null;
        }
    }
};
</script>
