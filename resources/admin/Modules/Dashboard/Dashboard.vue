<template>
    <div class="fs_head_section">
        <div v-if="dashboard_param.greetingMessage">
                <h1 style="margin-top: 30px">
                    {{ $t("Good") }} {{ greetingTime }} {{ me.full_name
                    }}{{ $t("!!!") }}
                </h1>
        </div>
        <div v-else style="width: 245px"></div>
        <div class="fs_button_group">
            <el-button
                type="primary"
                class="fs_drawer_button"
                style="margin-left: 16px"
                @click="drawer = true"
            >
                <el-icon>
                    <Setting />
                </el-icon>
            </el-button>

            <el-button
                type="info"
                class="fs_drawer_button"
                style="margin-left: 16px"
                @click="defalutSettings()"
            >
                Reset
            </el-button>
        </div>
    </div>

    <div class="dashboard fs_box_wrapper" v-if="!loading">
        <div v-html="dashboard_notice"></div>
        <el-row :gutter="20">
            <el-col :span="12">
                <draggable
                    v-model="dashboard_param.first_column"
                    ghost-class="ghost"
                    group="dashboard_component"
                    item-key="id"
                >
                    <template v-if="!loading" #item="{ element }">
                        <div v-if="element.show" class="draggable">
                            <div class="draggable_component">
                                <el-collapse
                                    v-model="active_names[element.active_names]"
                                    @change="handleChange"
                                >
                                    <el-collapse-item
                                        :name="element.active_names"
                                        class="fs_box_board"
                                    >
                                        <template #title>
                                            <div class="fs_box_header">
                                                {{ $t(element.heading) }}
                                            </div>
                                        </template>
                                        <component
                                            :is="element.component"
                                            :component_data="
                                                total_data[element.component]
                                            "
                                        >
                                        </component>
                                    </el-collapse-item>
                                </el-collapse>
                            </div>
                        </div>
                    </template>
                </draggable>
            </el-col>
            <el-col :span="12">
                <draggable
                    v-model="dashboard_param.second_column"
                    ghost-class="ghost"
                    group="dashboard_component"
                    item-key="id"
                >
                    <template #item="{ element }">
                        <div v-if="element.show" class="draggable">
                            <div class="draggable_component">
                                <el-collapse
                                    v-model="active_names[element.active_names]"
                                    @change="handleChange"
                                >
                                    <el-collapse-item
                                        :name="element.active_names"
                                        class="fs_box_board"
                                    >
                                        <template #title>
                                            <div class="fs_box_header">
                                                {{ $t(element.heading) }}
                                            </div>
                                        </template>
                                        <component
                                            :is="element.component"
                                            :component_data="
                                                total_data[element.component]
                                            "
                                        >
                                        </component>
                                    </el-collapse-item>
                                </el-collapse>
                            </div>
                        </div>
                    </template>
                </draggable>
            </el-col>
        </el-row>

        <el-drawer v-model="drawer" :with-header="false">
            <div class="fs_drawer_content">
                <el-checkbox v-model="dashboard_param.greetingMessage"
                    >Greeting Message</el-checkbox
                >
                <div v-for="column_data in dashboard_param">
                    <div
                        class="fs_settings_drawer"
                        v-for="component_list_data in column_data"
                    >
                        <el-skeleton
                            :rows="5"
                            :count="4"
                            style="
                                width: 240px;
                                --el-skeleton-circle-size: 20px;
                            "
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
                        <el-checkbox
                            v-model="component_list_data.show"
                            :label="component_list_data.component"
                        />
                    </div>
                </div>
            </div>

            <template #footer>
                <div style="flex: auto">
                    <el-button @click="cancelClick">Close</el-button>
                </div>
            </template>
        </el-drawer>
    </div>

    <div class="fs_padded_20" v-else>
        <el-row :gutter="20">
            <el-col :span="12">
                <div class="fs_component_skeleton">
                    <el-skeleton :rows="6" animated />
                </div>
            </el-col>
            <el-col :span="12">
                <div class="fs_component_skeleton">
                    <el-skeleton :rows="6" animated />
                </div>
            </el-col>
        </el-row>
    </div>
</template>

<script type="text/babel">
import draggable from "vuedraggable";
import TicketStatistics from "./TicketStatistics.vue";
import SuggestedTicket from "./SuggestedTicket.vue";
import MentionedTicket from "./MentionedTicket.vue";
export default {
    name: "DynamicDashboard",
    components: {
        draggable,
        TicketStatistics,
        SuggestedTicket,
        MentionedTicket,
    },
    data() {
        return {
            drawer: false,
            me: this.appVars.me,
            can_access_unassigned_tickets: false,
            loading: false,
            dashboard_notice: "",
            total_data: {
                MentionedTicket: {},
                TicketStatistics: {
                    stats: {},
                    individual_stat: {},
                },
                SuggestedTicket: {
                    suggested_tickets: [],
                    overall_stats: {},
                },
            },
            dashboard_param: {},
            dashboard_settings_data: {
                first_column: [
                    {
                        id: 1,
                        component: "MentionedTicket",
                        show: true,
                        heading: "dashboard_watcher_heading",
                        active_names: "mentionedTicket",
                    },
                    {
                        id: 2,
                        component: "TicketStatistics",
                        show: true,
                        heading: "Your Overview for Today",
                        active_names: "ticketStatistics",
                    },
                ],
                second_column: [
                    {
                        id: 3,
                        component: "SuggestedTicket",
                        show: true,
                        heading: "dashboard_sub_heading",
                        active_names: "suggestedTicket",
                    },
                ],
                greetingMessage: true,
            },
            dashboard_build: "v167.0",
            settings_data: {},
            app_ready: false,
            active_names: {},
            default_active_names: {
                mentionedTicket: ["mentionedTicket"],
                ticketStatistics: ["ticketStatistics"],
                suggestedTicket: ["suggestedTicket"],
            },
        };
    },
    watch: {
        dashboard_param: {
            handler(newValue, oldValue) {
                this.$saveData("dashboard_settings_data", newValue);
                this.$saveData("dashboard_build", this.dashboard_build);
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
        handleChange() {
            this.$saveData("component_collapse_data", this.active_names);
        },

        defalutSettings() {
            this.$saveData(
                "dashboard_settings_data",
                this.dashboard_settings_data
            );
            this.$saveData(
                "component_collapse_data",
                this.default_active_names
            );
            this.getComponentState();
            this.getDashboardSettings();
        },
        cancelClick() {
            this.drawer = false;
        },
        checkMove: function (e) {
            window.console.log("Future index: " + e.draggedContext.futureIndex);
        },
        getComponentState() {
            let collapseState = this.$getData("component_collapse_data");
            let build = this.$getData("dashboard_build");
            if (collapseState && build === this.dashboard_build) {
                this.active_names = collapseState;
            } else {
                this.active_names = this.default_active_names;
            }
        },
        getDashboardSettings() {
            this.settings_data = this.$getData("dashboard_settings_data");
            let build = this.$getData("dashboard_build");
            if (
                Object.entries(this.settings_data).length &&
                build === this.dashboard_build
            ) {
                this.dashboard_param = this.settings_data;
            } else {
                this.$saveData("dashboard_settings_data", []);
                this.dashboard_param = this.dashboard_settings_data;
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
                    this.dashboard_notice = response.dashboard_notice;
                    this.total_data.MentionedTicket = response.ticket_to_watch;
                    this.total_data.SuggestedTicket.suggested_tickets =
                        response.suggested_tickets;
                    this.total_data.SuggestedTicket.overall_stats =
                        response.overall_stats;
                    this.total_data.TicketStatistics.stats = response.stats;
                    this.total_data.TicketStatistics.individual_stat =
                        response.individual_stat;

                    this.app_ready = true;
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
.ghost {
    opacity: 0.2;
    background: rgb(201, 174, 122);
    color: white;
    margin: 10px;
    margin: auto;
    display: block;
    border-radius: 5px;
    overflow: hidden;
    text-align: center;
}

.fs_settings_drawer {
    width: 70%;
    border-radius: 10px;
    padding: 10px;
    margin-bottom: 10px;
    background: #fff;
    display: block;
    overflow: hidden;
    border: 1px solid #e3e8ee;
}

.fs_drawer_button {
    float: right;
}
.draggable_component {
    max-width: 680px;
    margin-left: auto;
    margin-right: auto;
    margin-bottom: 20px;
    border-radius: 15px;
    border: 1px solid rgb(226, 228, 231);
    box-shadow: 0 1px 4px rgb(18 25 97 / 8%);
}
.fs_head_section {
    display: flex;
    justify-content: space-between;
    margin-bottom: 25px;
}
.fs_button_group {
    padding: 10px;
}
.fs_drawer_content {
    margin-top: 30px;
}
.fs_component_skeleton {
    border: 1px solid #e3e8ee;
    border-radius: 10px;
    padding: 10px;
}
.fs_box_header {
    cursor: move;
}
</style>
