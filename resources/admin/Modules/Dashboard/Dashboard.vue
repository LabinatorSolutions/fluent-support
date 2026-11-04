<template>
    <div class="fs_box_wrapper">
        <div class="fs_dashboard_wrapper">
            <div v-html="dashboard_notice"></div>
            <div class="fs_head_section">
                <div class="fs_user_greetings_section">
                    <div class="fs_greetings_content">
                        <div class="fs_user_avatar">
                            <img :src="me.photo" :alt="me.full_name" />
                        </div>
                        <div class="fs_greetings_text">
                            <h1 class="fs_greeting_main">
                                <span class="fs_page_title fs_page_title_secondary_color">{{ greetingTime }}</span>
                                <span class="fs_page_title">, {{ me.full_name }} 👋🏻</span>
                            </h1>
                            <p class="fs_page_subtitle">{{ $t("Welcome back to Fluent Support") }}</p>
                        </div>
                    </div>
                </div>
                <div></div>
                <div class="fs_button_group">
                    <div class="fs_button_group_inner">
                        <button
                            @click="drawer = true"
                            class="fs_settings_button"
                            :aria-label="$t('All Widgets')"
                        >
                            <DynamicIcon name="Sliders"/>
                        </button>

                        <button
                            class="fs_refresh_button"
                            @click="defalutSettings()"
                        >
                            <DynamicIcon name="Refresh"/>

                            <span>{{ $t('Reset') }}</span>
                        </button>
                    </div>
                </div>
            </div>

            <DashboardStatistics :stats="total_data.TicketStatistics.stats" :loading="loading" />

            <!-- Dashboard Draggable Content Component -->
            <DashboardDraggableContent
                :loading="loading"
                :dashboard_notice="dashboard_notice"
                :dashboard_param="dashboard_param"
                :active_names="active_names"
                :total_data="total_data"
                @change="handleChange"
            />

            <!-- Settings Drawer -->
            <DashboardSettingsDrawer
                v-model="drawer"
                :dashboard-param="dashboard_param"
                @close="cancelClick"
                @widget-toggled="handleWidgetToggle"
            />
        </div>
    </div>
</template>

<script type="text/babel">
import DashboardDraggableContent from "./DashboardDraggableContent.vue";
import DashboardStatistics from './DashboardStatistics.vue';
import DashboardSettingsDrawer from './DashboardSettingsDrawer.vue';
import DynamicIcon from '@/admin/Components/Icons/DynamicIcon.vue';

export default {
    name: "DynamicDashboard",
    components: {
        DashboardDraggableContent,
        DashboardStatistics,
        DashboardSettingsDrawer,
        DynamicIcon,
    },

    data() {
        return {
            // Component state
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
                TicketsByProduct: {},
                AgentPerformance: {},
            },
            dashboard_param: {},
            dashboard_settings_data: {
                first_column: [
                    {
                        id: 1,
                        component: "MentionedTicket",
                        icon: 'bookmark',
                        show: true,
                        heading: this.$t("Your Bookmarked Tickets"),
                        active_names: "mentionedTicket",
                        image: "/images/dashboard_drawer_bookmark.png",

                    },
                                        {
                        id: 4,
                        component: "TicketsByProduct",
                        show: true,
                        icon: 'box',
                        heading: this.$t("active_tickets_by_products"),
                        active_names: "ticketsByProduct",
                        image: "/images/dashboard_drawer_product_wise_tickets.png",
                    },
                ],
                second_column: [
                    {
                        id: 3,
                        component: "SuggestedTicket",
                        show: true,
                        icon: 'lightbulb',
                        heading: this.$t("Suggested Tickets"),
                        active_names: "suggestedTicket",
                        image: "/images/dashboard_drawer_suggested_tickets.png",
                    },
                    {
                        id: 5,
                        component: 'AgentPerformance',
                        icon: 'user_avatar',
                        show: this.appVars.me.permissions.includes('fst_agent_today_performance'),
                        heading: this.$t('Agent Performance Today'),
                        active_names: 'agentPerformance',
                        image: "/images/dashboard_drawer_agent_performance.png",
                    }
                ],
                greetingMessage: true,
            },
            settings_data: {},
            app_ready: false,
            active_names: {},
            default_active_names: {
                mentionedTicket: ["mentionedTicket"],
                suggestedTicket: ["suggestedTicket"],
                ticketsByProduct: ["ticketsByProduct"],
                agentPerformance: ["agentPerformance"],
            },
        };
    },

    computed: {
        canManageSettings() {
            return this.appVars.me.permissions.includes('fst_manage_settings');
        },
        greetingTime() {
            const m = this.$dayjs();
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
                g = this.$t("Good afternoon");
            } else if (currentHour >= split_evening) {
                g = this.$t("Good evening");
            } else {
                g = this.$t("Good morning");
            }

            return g;
        }
    },

    watch: {
        dashboard_param: {
            handler(newValue, oldValue) {
                this.$saveData("dashboard_settings_data", newValue);
                this.$saveData(
                    "prev_dashboard_default_settings",
                    this.dashboard_settings_data
                );
            },
            deep: true
        }
    },

    mounted() {
        if (!this.appVars.has_mailboxes && this.canManageSettings) {
            this.$router.push({ name: "setup", query: { t: Date.now() } });
        }
        this.can_access_unassigned_tickets =
            this.appVars.me.permissions.indexOf(
                "fst_manage_unassigned_tickets"
            ) > -1;
        this.migrateDashboardSettings();
        this.fetchStat();
        this.getDashboardSettings();
        this.getComponentState();
        jQuery(
            ".update-nag,.notice, #wpbody-content > .updated, #wpbody-content > .error"
        ).remove();
    },

    methods: {
        migrateDashboardSettings() {
            // Check if migration is needed
            const migrationDone = this.$getData("dashboard_migration_v2_done");
            if (migrationDone) {
                return; // Already migrated
            }

            // Get old settings
            const oldSettings = this.$getData("dashboard_settings_data");
            if (!oldSettings) {
                // No old settings, mark migration as done
                this.$saveData("dashboard_migration_v2_done", true);
                return;
            }

            // Check if old settings contain TicketStatistics component (old structure)
            const hasOldStructureInFirstColumn = oldSettings.first_column?.some(
                item => item.component === "TicketStatistics"
            );
            const hasOldStructureInSecondColumn = oldSettings.second_column?.some(
                item => item.component === "TicketStatistics"
            );
            const hasOldStructure = hasOldStructureInFirstColumn || hasOldStructureInSecondColumn;

            if (hasOldStructure) {
                // Migrate: Remove TicketStatistics from both columns
                const newSettings = {
                    first_column: (oldSettings.first_column || []).filter(
                        item => item.component !== "TicketStatistics"
                    ),
                    second_column: (oldSettings.second_column || []).filter(
                        item => item.component !== "TicketStatistics"
                    ),
                    greetingMessage: oldSettings.greetingMessage !== false // default to true
                };

                // Ensure all required properties exist on all items
                newSettings.first_column = newSettings.first_column.map(item => ({
                    ...item,
                    icon: item.icon || this.getIconForComponent(item.component),
                    image: item.image || this.getImageForComponent(item.component)
                }));

                newSettings.second_column = newSettings.second_column.map(item => ({
                    ...item,
                    icon: item.icon || this.getIconForComponent(item.component),
                    image: item.image || this.getImageForComponent(item.component)
                }));

                // Update IDs to be sequential
                newSettings.first_column.forEach((item, index) => {
                    item.id = index + 1;
                });
                newSettings.second_column.forEach((item, index) => {
                    item.id = newSettings.first_column.length + index + 1;
                });

                // Save migrated settings
                this.$saveData("dashboard_settings_data", newSettings);
                this.$saveData("prev_dashboard_default_settings", this.dashboard_settings_data);

                // Migrate collapse state - remove ticketStatistics key
                const collapseState = this.$getData("component_collapse_data");
                if (collapseState && collapseState.ticketStatistics) {
                    delete collapseState.ticketStatistics;
                    this.$saveData("component_collapse_data", collapseState);
                }
            }

            // Mark migration as complete
            this.$saveData("dashboard_migration_v2_done", true);
        },

        getIconForComponent(componentName) {
            const iconMap = {
                'MentionedTicket': 'bookmark',
                'SuggestedTicket': 'lightbulb',
                'TicketsByProduct': 'box',
                'AgentPerformance': 'user_avatar'
            };
            return iconMap[componentName] || 'box';
        },

        getImageForComponent(componentName) {
            const imageMap = {
                'MentionedTicket': '/images/dashboard_drawer_bookmark.png',
                'SuggestedTicket': '/images/dashboard_drawer_suggested_tickets.png',
                'TicketsByProduct': '/images/dashboard_drawer_product_wise_tickets.png',
                'AgentPerformance': '/images/dashboard_drawer_agent_performance.png'
            };
            return imageMap[componentName] || '/images/dashboard_drawer_bookmark.png';
        },

        handleChange() {
            this.$saveData("component_collapse_data", this.active_names);
        },

        defalutSettings() {
            this.$saveData("dashboard_settings_data", this.dashboard_settings_data);
            this.$saveData("component_collapse_data", this.default_active_names);
            this.getComponentState();
            this.getDashboardSettings();
        },

        cancelClick() {
            this.drawer = false;
        },

        handleWidgetToggle(data) {
            // Save the updated dashboard settings when a widget is toggled
            this.$saveData("dashboard_settings_data", this.dashboard_param);
        },

        checkMove(e) {
            window.console.log("Future index: " + e.draggedContext.futureIndex);
        },

        getComponentState() {
            let collapseState = this.$getData("component_collapse_data");
            if (collapseState) {
                this.active_names = collapseState;
            } else {
                this.active_names = this.default_active_names;
            }
        },

        getDashboardSettings() {
            let prev_dashboard_default_settings = this.$getData(
                "prev_dashboard_default_settings"
            );
            this.settings_data = this.$getData("dashboard_settings_data");
            if (
                JSON.stringify(prev_dashboard_default_settings) ===
                JSON.stringify(this.dashboard_settings_data)
            ) {
                this.dashboard_param = this.settings_data;
            } else {
                this.$saveData(
                    "prev_dashboard_default_settings",
                    this.dashboard_settings_data
                );
                this.dashboard_param = this.$getData(
                    "prev_dashboard_default_settings"
                );
            }
        },

        fetchStat() {
            this.loading = true;
            let withData = [
                "suggested_tickets",
                "overall_stats",
                "individual_stat",
                "ticket_to_watch",
                "tickets_by_products",
            ];
            this.$get("tickets/my_stats", {
                with: withData,
            })
                .then((response) => {
                    // Built by PHP code through the fluent_support/dashboard_notice filter, like
                    // a WordPress admin notice, so it is trusted the same way.
                    this.dashboard_notice = response.dashboard_notice;
                    this.total_data.MentionedTicket = response.ticket_to_watch;
                    this.total_data.SuggestedTicket.suggested_tickets =
                        response.suggested_tickets;
                    this.total_data.SuggestedTicket.overall_stats =
                        response.overall_stats;
                    this.total_data.TicketStatistics.stats = response.stats;
                    this.total_data.TicketStatistics.individual_stat =
                        response.individual_stat;
                    this.total_data.TicketsByProduct =
                        response.tickets_by_product;

                    this.app_ready = true;
                })
                .catch((errors) => {
                    this.$handleError(errors);
                })
                .always(() => {
                    this.loading = false;
                });
        }
    }
};
</script>
