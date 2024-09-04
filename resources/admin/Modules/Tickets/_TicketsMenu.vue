<template>
    <div v-if="currentTickets && !isMobile" class="fs_tickets_tiny_nav">
        <ul class="fs_ticket_nav">
            <li v-for="ticket in currentTickets">
                <router-link :to="{ name: 'view_ticket', params: { ticket_id: ticket.id } }">
                    <img v-if="ticket.customer" :title="ticket.customer.email +' - '+ ticket.customer.full_name"
                         :alt="ticket.customer.full_name"
                         class="tk_customer_avatar" :src="ticket.customer.photo"/>
                    <div class="fs_ticket_title">
                        <span class="fs_ticket_nav_customer_name">{{ ticket.title }}</span>
                        <span class="fs_ticket_nav_title">
                            {{ getExcerpt(ticket) }}
                        </span>
                    </div>
                </router-link>
            </li>
        </ul>
    </div>
    <div v-else class="inner_sidebar">
        <ul>
            <li v-for="(route, index) in dynamicRoutes" :key="`${route.name}-${index}`">
                <router-link
                    :class="{router_not_exactly_matched: isCurrentRouteMatched(route.name)}"
                    :to="route.to"
                    @click="route.onClick"
                >
                    <el-icon>
                        <component :is="route.icon"/>
                    </el-icon>
                    {{ $t(route.label) }}
                </router-link>
            </li>
        </ul>
    </div>
</template>

<script type="text/babel">
import {useFluentHelper} from "@/admin/Composable/FluentFrameworkHelper";

export default {
    name: 'TicketsMenu',
    data() {
        return {
            isMobile: false,
            dynamicRoutes: [
                {
                    name: 'allTickets',
                    label: 'All Tickets',
                    to: {name: 'tickets'},
                    icon: 'Tickets',
                    onClick: () => this.saveRoutes(),
                },
                {
                    name: 'myTickets',
                    label: 'My Tickets',
                    to: {name: 'tickets', query: {agent_id: this.appVars.me.id}},
                    icon: 'User',
                    onClick: () => this.saveRoutes({agent_id: this.appVars.me.id}),
                },
                {
                    name: 'unassignedTickets',
                    label: 'Unassigned',
                    to: {name: 'tickets', query: {agent_id: 'unassigned'}},
                    icon: 'View',
                    onClick: () => this.saveRoutes({agent_id: 'unassigned'}),
                },
                ...(this.has_pro ? [{
                    name: 'mentionedTickets',
                    label: 'Bookmarks',
                    to: {name: 'tickets', query: {watcher: 'watcher', agent_id: this.appVars.me.id}},
                    icon: 'CollectionTag',
                    onClick: () => this.saveRoutes({watcher: 'watcher', agent_id: this.appVars.me.id}),
                }] : []),
            ],
        }
    },
    computed: {
        currentTickets() {
            if (this.$route.name != 'view_ticket' || !(window.fsCurrentFilteredTickets && window.fsCurrentFilteredTickets.length)) {
                return null;
            }

            return window.fsCurrentFilteredTickets;
        },
        isAll() {
            return this.$route.query.agent_id;
        },
        isMine() {
            return !(this.$route.query.watcher !== 'watcher' && this.appVars.me.id === parseInt(this.$route.query.agent_id));
        },
        isUnassigned() {
            return !(this.$route.query.agent_id === 'unassigned' && !this.$route.query.watcher);
        },
        isMentioned() {
            return !(this.$route.query.watcher === 'watcher' && this.appVars.me.id === parseInt(this.$route.query.agent_id));
        }
    },
    methods: {
        getExcerpt(ticket) {
            let text = (ticket.preview_response) ? ticket.preview_response.content : ticket.content;

            if (!text) {
                return '';
            }

            text = text.replace(/<\/?("[^"]*"|'[^']*'|[^>])*(>|$)/g, "");

            return text.substring(0, 70);
        },

        isCurrentRouteMatched(routeName) {
            const routeContacts = {
                allTickets: this.isAll,
                myTickets: this.isMine,
                unassignedTickets: this.isUnassigned,
                mentionedTickets: this.isMentioned,
            };

            if (routeName in routeContacts) {
                return routeContacts[routeName];
            }

            return false;
        },

        saveRoutes(currentRouteQuery = {}) {
            useFluentHelper().saveData("routesData", JSON.stringify(currentRouteQuery));
        },
    },
    mounted() {
        this.isMobile = window.innerWidth < 768;
    }
}
</script>

<style lang="scss">
.fs_tickets_tiny_nav {
    width: 220px;
    ul.fs_ticket_nav {
        margin: 0 20px 0 0;
        list-style: none;
        padding: 0;
        max-height: 85vh;
        overflow-y: auto;
        li {
            padding: 7px 0;
            &:hover {
                background: white;
            }
            position: relative;
            a {
                display: flex;
                column-gap: 7px;
                align-items: center;
                justify-content: flex-start;
                text-decoration: none;
                color: #333;
                cursor: pointer;
                &:focus {
                    outline: none;
                    box-shadow: none;
                }

                /*
                * Create a graiant at the end of list item
                * It should show as less opacity
                 */
                &:after {
                    content: "";
                    position: absolute;
                    width: 40%;
                    background: linear-gradient(to right, rgba(255, 255, 255, 0), #f1f2f5d9);
                    bottom: 0;
                    right: 0;
                    top: 0;
                }

                img {
                    width: 30px;
                    height: 30px;
                    border-radius: 50%;
                }
                .fs_ticket_title {
                    display: block;
                    width: 100%;
                    overflow: hidden;
                    text-overflow: ellipsis;
                    white-space: nowrap;
                }
                span.fs_ticket_nav_customer_name {
                    display: block;
                    width: 100%;
                    font-weight: 500;
                }
                .fs_ticket_nav_title {
                    display: block;
                    width: 100%;
                    font-size: 12px;
                    color: #666;
                    white-space: nowrap;
                    overflow: hidden;
                    text-overflow: ellipsis;
                }
                &.router-link-exact-active {
                    .fs_ticket_nav_title, .fs_ticket_nav_customer_name {
                        color: #2271b1;
                    }
                }
            }
        }
    }
}
</style>
