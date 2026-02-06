<template>
    <div class="fs_tickets_view_wrapper">
        <!-- Combined Top Bar -->
        <div class="fs_combined_top_bar" v-if="isViewingTicket">
            <!-- Left Section: Filtered Tickets with Collapse -->
            <div class="fs_top_bar_left">
                <div class="fs_filtered_tickets_section">
                    <h3 class="fs_filtered_tickets_title" v-if="currentTickets && currentTickets.length">{{ $t('Filtered Tickets') }}</h3>
                    <h3 class="fs_filtered_tickets_title" v-else>{{ $t('Ticket Filters') }}</h3>
                    <el-button
                        class="fs_tickets_collapse_btn"
                        :class="{ 'collapsed': isSidebarCollapsed }"
                        @click="toggleSidebar"
                        :title="$t(isSidebarCollapsed ? 'Expand Sidebar' : 'Collapse Sidebar')"
                        text
                    >
                        <img :src="appVars.asset_url + 'images/arrowLeftLine.svg'" alt="">
                    </el-button>
                </div>
            </div>

            <!-- Right Section: Breadcrumbs & Actions (shown when viewing a ticket) -->
            <div class="fs_top_bar_right" v-if="isViewingTicket">
                <!-- Breadcrumbs -->
                <div class="fs_breadcrumbs_group">
                    <div class="fs_breadcrumb_item">
                        <router-link to="/tickets" class="fs_breadcrumb_link">
                            {{ $t('All Tickets') }}
                        </router-link>
                    </div>
                    <div class="fs_breadcrumb_separator">
                        <el-icon><ArrowRight /></el-icon>
                    </div>
                    <div class="fs_breadcrumb_item">
                        <span class="fs_breadcrumb_current">#{{ currentTicketId }}</span>
                    </div>
                </div>

                <!-- Action Buttons Teleport Target -->
                <div id="fs_top_bar_actions_slot" class="fs_top_bar_actions">
                    <!-- Actions will be teleported here from ViewTicket.vue -->
                </div>
            </div>
        </div>

        <!-- Main Content Area -->
        <div class="fs_tickets_view" :class="{ 'sidebar-collapsed': isSidebarCollapsed }">
            <tickets-menu :is-collapsed="isSidebarCollapsed" @toggle-collapse="toggleSidebar" />
            <div class="fs_tk_inner_body">
                <router-view key="tickets_view"></router-view>
            </div>
        </div>
    </div>
</template>

<script type="text/babel">
import TicketsMenu from "./_TicketsMenu.vue";

export default {
    name: 'ticketsView',
    components: {
        TicketsMenu
    },
    data() {
        // Initialize from localStorage to prevent flash on page load
        let savedCollapseState = false;
        try {
            if (typeof this.$getData === 'function') {
                savedCollapseState = this.$getData("ticketsMenuCollapsed");
            } else {
                // Fallback: access localStorage directly if $getData is not available yet
                const existingData = window.localStorage.getItem('__fluentsupport_data');
                if (existingData) {
                    const parsed = JSON.parse(existingData);
                    if (parsed && parsed.ticketsMenuCollapsed) {
                        savedCollapseState = parsed.ticketsMenuCollapsed;
                    }
                }
            }
        } catch (e) {
            // If there's any error, default to false
            savedCollapseState = false;
        }
        return {
            isSidebarCollapsed: savedCollapseState === 'yes'
        };
    },
    computed: {
        isViewingTicket() {
            return this.$route.name === 'view_ticket';
        },
        currentTicketId() {
            return this.$route.params.ticket_id || '';
        },
        currentTickets() {
            if (this.$route.name !== 'view_ticket') {
                return null;
            }
            
            const tickets = window.fsCurrentFilteredTickets;
            return tickets && Array.isArray(tickets) && tickets.length > 0 ? tickets : null;
        },
    },
    watch: {
        '$route.name'() {
            this.loadRouteDataFromLocalStorage();
        }
    },
    methods: {
        toggleSidebar() {
            this.isSidebarCollapsed = !this.isSidebarCollapsed;
            this.$saveData("ticketsMenuCollapsed", this.isSidebarCollapsed ? 'yes' : 'no');
        },

        loadRouteDataFromLocalStorage() {
            const routeName = this.$route.name;
            const savedRoute = this.$getData('routesData');

            if (routeName !== 'tickets' || !savedRoute || Object.keys(savedRoute).length === 0) {
                return;
            }

            const parsedRoute = typeof savedRoute === 'string' ? JSON.parse(savedRoute) : savedRoute;

            if ("agent_id" in parsedRoute && parsedRoute.agent_id !== 'unassigned') {
                parsedRoute.agent_id = this.appVars.me.id;
            }

            this.$router.replace({ name: 'tickets', query: parsedRoute });
        }
    },
    mounted() {
        this.loadRouteDataFromLocalStorage();
    }
}
</script>
