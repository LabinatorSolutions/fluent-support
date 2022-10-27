<template>
    {{ component_collapse_state }}
    <div class="dashboard fs_box_wrapper">
        <h1>Dynamic Dashboard</h1>
        <div v-html="dashboard_notice"></div>
        <el-row :gutter="20">
            <el-col :span="12">
                <draggable
                    v-model="dashboard_settings_data.second_row"
                    ghost-class="ghost"
                    group="people"
                    item-key="id"
                >
                    <template #item="{ element }">
                        <div class="draggable">
                            <component
                                v-if="element.show"
                                :is="element.component"
                                :data="total_data"
                                :component_collapse_state="
                                    component_collapse_state
                                "
                                @component_state="changeComponentState"
                            ></component>
                        </div>
                    </template>
                </draggable>
            </el-col>
            <el-col :span="12">
                <draggable
                    v-model="dashboard_settings_data.first_row"
                    ghost-class="ghost"
                    group="people"
                    item-key="id"
                >
                    <template #item="{ element }">
                        <div class="draggable">
                            <component
                                v-if="element.show"
                                :is="element.component"
                                :data="total_data"
                                :component_collapse_state="
                                    component_collapse_state
                                "
                                @component_state="changeComponentState"
                            ></component>
                        </div>
                    </template>
                </draggable>
            </el-col>

            <el-button @click="saveDashboardSettings()">
                {{ $t("Save") }}
            </el-button>
        </el-row>

        <el-button
            type="primary"
            style="margin-left: 16px"
            @click="drawer = true"
        >
            open
        </el-button>

        <el-drawer v-model="drawer" title="I am the title" :with-header="false">
            <div
                class="fs_padded_20"
                v-for="result in dashboard_settings_data.first_row"
            >
                <el-skeleton
                    :rows="5"
                    :count="4"
                    style="width: 240px; --el-skeleton-circle-size: 20px"
                >
                    <template #template>
                        <div
                            style="
                                display: flex;
                                align-items: center;
                                justify-items: space-between;
                                margin-bottom: 5px;
                                height: 100%;
                            "
                        >
                            <el-skeleton-item
                                variant="circle"
                                style="
                                    margin-right: 16px;
                                    --el-skeleton-circle-size: 20px;
                                "
                            />
                            <el-skeleton-item
                                variant="text"
                                style="width: 80%"
                            />
                        </div>
                    </template>
                </el-skeleton>
                <el-checkbox v-model="result.show" :label="result.component" />
            </div>

            <div
                class="fs_padded_20"
                v-for="result in dashboard_settings_data.second_row"
            >
                <el-skeleton
                    :rows="5"
                    :count="4"
                    style="width: 240px; --el-skeleton-circle-size: 20px"
                >
                    <template #template>
                        <div
                            style="
                                display: flex;
                                align-items: center;
                                justify-items: space-between;
                                margin-bottom: 5px;
                                height: 100%;
                            "
                        >
                            <el-skeleton-item
                                variant="circle"
                                style="
                                    margin-right: 16px;
                                    --el-skeleton-circle-size: 20px;
                                "
                            />
                            <el-skeleton-item
                                variant="text"
                                style="width: 80%"
                            />
                        </div>
                    </template>
                </el-skeleton>
                <el-checkbox v-model="result.show" :label="result.component" />
            </div>
        </el-drawer>
    </div>
</template>

<script type="text/babel">
import draggable from "vuedraggable";
import DashboardBox from "./DashboardBox.vue";
import SuggestedTicket from "./SuggestedTicket.vue";
import MentionedTticket from "./MentionedTticket.vue";
import Component1 from "./Component1.vue";
import Component2 from "./Component2.vue";
export default {
    name: "DynamicDashboard",
    components: {
        draggable,
        DashboardBox,
        SuggestedTicket,
        Component1,
        Component2,
        MentionedTticket,
    },
    data() {
        return {
            drawer: false,
            me: this.appVars.me,
            can_access_unassigned_tickets: false,
            loading: false,
            stats: {},
            suggested_tickets: [],
            ticket_to_watch: [],
            overall_stats: false,
            individual_stat: false,
            dashboard_notice: "",
            total_data: [],
            dashboard_settings_data: {
                first_row: [
                    {
                        text: "MentionedTticket",
                        id: 3,
                        component: "MentionedTticket",
                        show: true,
                        fixed: true,
                    },
                    {
                        text: "Ticket1",
                        id: 1,
                        component: "Component2",
                        show: true,
                        fixed: true,
                    },
                ],

                second_row: [
                    {
                        text: "Ticket2",
                        id: 2,
                        component: "DashboardBox",
                        show: true,
                        fixed: true,
                    },
                    {
                        text: "Ticket4",
                        id: 4,
                        component: "SuggestedTicket",
                        show: true,
                        fixed: true,
                    },
                ],
            },
            settings_data: {},
            default_component_collapse_state: {
                dashboardBox: true,
                mentionedTicket: true,
                suggestedTicket: true,
            },
            component_collapse_state: {},
        };
    },
    watch: {
        // whenever question changes, this function will run
        dashboard_settings_data: {
            handler(newValue, oldValue) {
                // alert(this.dashboard_settings_data);
                // console.log(this.dashboard_settings_data);
                this.$saveData("dashboard_settings_data", newValue);
                //    this.getDashboardSettings();
                //    this.$getData('tickets_filter_type', 'simple');
            },
            deep: true,
        },
    },
    computed: {
        greetingTime() {
            const m = this.moment();
            let g = null; //return g

            if (!m || !m.isValid()) {
                return;
            } //if we can't find a valid or filled moment, we return.

            const split_afternoon = 12; //24hr time to split the afternoon
            const split_evening = 17; //24hr time to split the evening
            const currentHour = parseFloat(m.format("HH"));

            if (
                currentHour >= split_afternoon &&
                currentHour <= split_evening
            ) {
                g = this.$t("afternoon");
            } else if (currentHour >= split_evening) {
                g = this.$t("evening");
            } else {
                g = this.$t("morning");
            }

            return g;
        },
    },
    methods: {
        checkMove: function (e) {
            window.console.log("Future index: " + e.draggedContext.futureIndex);
        },
        getDashboardSettings() {
            this.settings_data = this.$getData("dashboard_settings_data");
            if (this.settings_data) {
                this.dashboard_settings_data = this.settings_data;
            }
            console.log(this.settings_data);
        },
        saveDashboardSettings() {
            console.log(this.dashboard_settings_data);
            this.$saveData(
                "dashboard_settings_data",
                this.dashboard_settings_data
            );
            //    this.$getData('tickets_filter_type', 'simple');
        },
        changeComponentState() {
            // console.log(this.component_collapse_sate);
            this.$saveData("component_state", this.component_collapse_state);
            // console.log(this.$getData('component_state'));
            // this.getComponentState()
            this.component_collapse_state = this.$getData("component_state");
            // console.log(this.component_collapse_sate);
            if (!this.component_collapse_state) {
                this.component_collapse_state =
                    this.default_component_collapse_state;
            }
        },
        getComponentState() {
            this.component_collapse_state = this.$getData("component_state");
            // console.log(this.component_collapse_sate);
            if (!this.component_collapse_state) {
                this.component_collapse_state =
                    this.default_component_collapse_state;
            }
        },
        fetchStat() {
            this.loading = true;
            this.$get("tickets/my_stats", {
                with: [
                    "suggested_tickets",
                    "overall_stats",
                    "individual_stat",
                    "ticket_to_watch",
                ],
            })
                .then((response) => {
                    this.total_data = response;
                    this.stats = response.stats;
                    this.suggested_tickets = response.suggested_tickets;
                    this.overall_stats = response.overall_stats;
                    this.individual_stat = response.individual_stat;
                    this.dashboard_notice = response.dashboard_notice;
                    this.ticket_to_watch = response.ticket_to_watch;
                })
                .catch((errors) => {
                    this.$handleError(errors);
                })
                .always(() => {
                    this.loading = false;
                });
        },
    },
    mounted() {
        if (!this.appVars.mailboxes.length) {
            this.$router.push({ name: "setup", query: { t: Date.now() } });
        }
        this.can_access_unassigned_tickets =
            this.appVars.me.permissions.indexOf(
                "fst_manage_unassigned_tickets"
            ) > -1;
        this.fetchStat();
        this.getDashboardSettings();
        this.getComponentState();
        jQuery(
            ".update-nag,.notice, #wpbody-content > .updated, #wpbody-content > .error"
        ).remove();
    },
};
</script>

<style scoped>
/* .dashboard .fs_box_header{
        padding: 20px 15px;
        font-size: 16px;
        border-radius: 5px 5px 0 0;
        background-color: dimgray;
        color: white; 
        background-color: #69696947;
         color: black;
    } */
/* .draggable{
        border: 1px solid black;
        width: 100%;
        text-align: center;
      
    } */
/* .show_title{
        color:red;
        cursor: move;
    } */

.ghost {
    opacity: 0.2;
    background: gray;
    color: white;
    width: 100%;
    margin: auto;
    display: block;
    overflow: hidden;
    text-align: center;
    /* border: 5px dotted black; */
}

.fs_padded_20 {
    border: 1px solid black;
    width: 70%;
    border-radius: 10px;
    /* height: 70%; */
    padding: 10px;
    margin-bottom: 10px;
}
</style>
