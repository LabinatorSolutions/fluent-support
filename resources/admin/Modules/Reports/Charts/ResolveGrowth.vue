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
import each from 'lodash/each'
import BarChartBase from "./BarChartBase";
export default {
    name: 'ResolveGrowth',
    props:['date_range', 'url', 'agent_id'],
    components: {
        BarChartBase
    },
    data() {
        return {
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
                            id: 'byDate',
                            type: 'linear',
                            position: 'left',
                            gridLines: {
                                drawOnChartArea: false
                            },
                            ticks: {
                                beginAtZero: true,
                                userCallback: function(label, index, labels) {
                                    // when the floored value is the same as the value we have a whole number
                                    if (Math.floor(label) === label) {
                                        return label;
                                    }
                                }
                            }
                        }
                    ],
                    xAxes: [
                        {
                            gridLines: {
                                drawOnChartArea: false
                            },
                            ticks: {
                                beginAtZero: true,
                                autoSkip: true,
                                maxTicksLimit: 10
                            }
                        }
                    ]
                },
                drawBorder: false,
                layout: {
                    padding: {
                        left: 0,
                        right: 0,
                        top: 0,
                        bottom: 20
                    }
                }
            }
        }
    },
    methods: {
        fetchReport() {
            this.fetching = true;
            this.$get(this.url + '/tickets-resolve-growth', {
                date_range: this.date_range,
                agent_id: this.agent_id
            })
                .then(response => {
                    this.setupChartItems(response.stats);
                });
        },
        setupChartItems(stats) {
            const chartData = {
                labels: [],
                datasets: []
            };

            const statData = [];
            each(stats, (count, label) => {
                chartData.labels.push(label);
                statData.push(parseInt(count));
            });
            chartData.datasets.push({
                label: 'Ticket Resolve Stats',
                backgroundColor: '#0cbe7e',
                data: statData
            });
            console.log(chartData);
            this.chartData = chartData;
        }
    },
    mounted() {
        this.fetchReport();
    }
}
</script>
