<template>
    <div class="fs_tickets_view_wrapper" :class="{ 'fs_is_viewing_ticket': isViewingTicket }">
        <!-- Combined Top Bar -->
        <div class="fs_combined_top_bar" v-if="isViewingTicket">
            <!-- Left Section: Filtered Tickets with Collapse -->
            <div class="fs_top_bar_left">
                <div class="fs_filtered_tickets_section">
                    <!-- The view switcher is teleported here from _TicketsMenu.vue -->
                    <div v-if="listLoaded" id="fs_ticket_view_switcher" class="fs_view_switcher_slot"></div>
                    <h3 class="fs_filtered_tickets_title" v-else>{{ $t('Ticket Filters') }}</h3>
                    <el-button
                        class="fs_tickets_collapse_btn"
                        :class="{ 'collapsed': isSidebarCollapsed }"
                        @click="toggleSidebar"
                        :title="$t(isSidebarCollapsed ? 'Expand Sidebar' : 'Collapse Sidebar')"
                        text
                    >
                        <DynamicIcon name="ArrowLeftLine"/>
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
                        <!-- translators: %s is the internal ticket ID number -->
                        <span v-tip="$t('Internal Ticket ID: #%s', currentActualTicketNumber)" class="fs_breadcrumb_current">#{{ currentTicketNumber }}</span>
                    </div>
                    <div id="fs_top_bar_ticket_chip_slot" class="fs_breadcrumb_item"></div>
                </div>

                <!-- Position in the list and previous/next, teleported here from ViewTicket.vue -->
                <div id="fs_top_bar_position_slot" class="fs_top_bar_position"></div>

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
import { mapState } from 'pinia';
import { useTicketListStore } from '@/admin/stores/ticketList.store';
import DynamicIcon from '@/admin/Components/Icons/DynamicIcon.vue';

export default {
    name: 'ticketsView',
    components: {
        DynamicIcon,
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
            isSidebarCollapsed: savedCollapseState === 'yes',
            viewedTicket: null
        };
    },
    computed: {
        ...mapState(useTicketListStore, {
            storeTickets: 'tickets',
            listLoaded: (state) => !state.first_time_loading,
        }),
        isViewingTicket() {
            return this.$route.name === 'view_ticket';
        },
        currentTicketId() {
            return this.$route.params.ticket_id || '';
        },
        currentTicket() {
            const routeTicketId = String(this.currentTicketId || '');
            const viewedTicket = this.viewedTicket;

            if (
                viewedTicket &&
                (String(viewedTicket.id) === routeTicketId || String(viewedTicket.serial_number || '') === routeTicketId)
            ) {
                return viewedTicket;
            }

            return (this.storeTickets || []).find((item) => {
                return String(item.id) === routeTicketId || String(item.serial_number || '') === routeTicketId;
            });
        },
        currentTicketNumber() {
            const ticket = this.currentTicket;

            return ticket ? (ticket.display_ticket_number || ticket.serial_number || ticket.id) : this.currentTicketId;
        },
        currentActualTicketNumber() {
            const ticket = this.currentTicket;

            return ticket ? ticket.id : this.currentTicketId;
        },
    },
    watch: {
        '$route.name'() {
            this.loadRouteDataFromLocalStorage();
        },
        currentTicketId() {
            this.viewedTicket = null;
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
        },

        setViewedTicket(event) {
            const ticket = event && event.detail && event.detail.response && event.detail.response.ticket;

            if (ticket) {
                this.viewedTicket = ticket;
            }
        }
    },
    mounted() {
        document.body.addEventListener('fs_ticket_viewed', this.setViewedTicket);
        this.loadRouteDataFromLocalStorage();
    },
    beforeUnmount() {
        document.body.removeEventListener('fs_ticket_viewed', this.setViewedTicket);
    }
}
</script>
