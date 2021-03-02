<script type="text/babel">
import {defineComponent} from 'vue'
import {Bar} from 'vue3-chart-v2'

export default defineComponent({
    name: 'MonthlyChart',
    props: {
        chartData: {
            type: Object,
            required: true
        },
        chartOptions: {
            type: Object,
            required: false
        },
    },
    extends: Bar,
    mounted() {
        this.renderChart(this.chartData, this.chartOptions);
    }
})
</script>
