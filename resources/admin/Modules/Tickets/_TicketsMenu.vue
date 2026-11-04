<template>
    <!-- On a single ticket the views move into a switcher in the top bar, so the list can change without leaving the ticket. -->
    <Teleport v-if="showSwitcher" defer to="#fs_ticket_view_switcher">
        <el-popover :persistent="false"
            v-model:visible="switcherOpen"
            placement="bottom-start"
            :width="280"
            trigger="click"
            popper-class="fs_view_switcher_popover"
        >
            <template #reference>
                <el-button text class="fs_view_switcher_btn" :aria-label="$t('Change ticket view')">
                    <span class="fs_view_switcher_label">{{ switcherLabel }}</span>
                    <span v-if="listTotal" class="fs_view_switcher_total">{{ formatCount(listTotal) }}</span>
                    <el-icon class="fs_view_switcher_arrow"><ArrowDown /></el-icon>
                </el-button>
            </template>
            <TicketViewsList
                :groups="groups"
                :tag-items="tagItems"
                v-model:tag-search="tagSearch"
                :show-tag-search="allTags.length > tagLimit"
                :closed-groups="closedGroups"
                :open-in-place="true"
                @toggle-group="toggleGroup"
                @selected="switcherOpen = false"
            />
        </el-popover>
    </Teleport>

    <!-- On phones and tablets the list title opens the views (a bottom sheet on phones, a side panel on tablets), so the list starts at the top of the screen. -->
    <template v-if="isMobile">
        <el-drawer
            v-model="viewsSheetOpen"
            :direction="isPhone ? 'btt' : 'ltr'"
            :size="isPhone ? '80%' : '360px'"
            :append-to-body="true"
            :title="$t('Ticket Views')"
            class="fs_mobile_views_drawer"
        >
            <TicketViewsList
                :groups="groups"
                :tag-items="tagItems"
                v-model:tag-search="tagSearch"
                :show-tag-search="allTags.length > tagLimit"
                :closed-groups="closedGroups"
                @toggle-group="toggleGroup"
                @selected="viewsSheetOpen = false"
            />
        </el-drawer>
    </template>
    <div v-else-if="showSwitcher" class="fs_tickets_tiny_nav" :class="{ 'is-collapsed': isCollapsed }">
        <!-- Ticket List with Collapse Transition -->
        <el-collapse-transition>
            <div v-show="!isCollapsed" class="fs_ticket_nav" v-loading="listLoading">
                <router-link
                    v-for="ticket in storeTickets"
                    :key="ticket.id"
                    :to="{ name: 'view_ticket', params: { ticket_id: ticket.id } }"
                    class="fs_ticket_card"
                >
                    <img
                        v-if="ticket.customer"
                        :title="ticket.customer.email ? ticket.customer.email + ' - ' + ticket.customer.full_name : ticket.customer.full_name"
                        :alt="ticket.customer.full_name"
                        class="fs_ticket_avatar"
                        :src="ticket.customer.photo"
                    />
                    <div class="fs_ticket_content">
                        <div class="fs_ticket_title_row">
                            <p class="fs_ticket_title_text">{{ ticket.title }}</p>
                        </div>
                        <div class="fs_ticket_meta_row">
                            <p class="fs_ticket_excerpt">{{ getExcerpt(ticket) }}</p>
                                <p class="fs_ticket_time" :data-ticket-time="lastActivity(ticket)">{{ $timeDiff(lastActivity(ticket)) }}</p>
                        </div>
                    </div>
                </router-link>
                <p v-if="!listLoading && !storeTickets.length" class="fs_ticket_nav_empty">{{ $t('No tickets in this view.') }}</p>
            </div>
        </el-collapse-transition>
    </div>
    <div v-else class="fs_tk_inner_sidebar" :class="{ 'is-collapsed': isCollapsed, 'has-view-route': hasViewRoute }">
        <div class="fs_sidebar_header" v-if="!isViewingTicket">
            <h3 class="fs_sidebar_title">{{ $t('Ticket Filters') }}</h3>
            <el-button
                class="fs_tickets_collapse_btn"
                :class="{ 'collapsed': isCollapsed }"
                @click="$emit('toggle-collapse')"
                :title="$t(isCollapsed ? 'Expand Sidebar' : 'Collapse Sidebar')"
                text
            >
                <DynamicIcon name="ArrowLeftLine"/>
            </el-button>
        </div>
        <TicketViewsList
            :groups="visibleGroups"
            :tag-items="tagItems"
            v-model:tag-search="tagSearch"
            :show-tag-search="allTags.length > tagLimit"
            :closed-groups="closedGroups"
            :is-collapsed="isCollapsed"
            @toggle-group="toggleGroup"
        />
    </div>
</template>

<script type="text/babel">

import TicketViewsList from "./_TicketViewsList.vue";
import { mapState, mapWritableState } from 'pinia';
import { useTicketListStore, defaultFilters } from '@/admin/stores/ticketList.store';
import { shortcutModifiers, isTypingTarget } from '@/admin/Bits/shortcuts';
import DynamicIcon from '@/admin/Components/Icons/DynamicIcon.vue';

// The counts follow list reloads, but at most this often.
const COUNTS_REFRESH_MS = 20 * 1000;

const STATUS_ICONS = {
    open: 'open_tickets',
    new: 'new_tickets',
    active: 'active_tickets',
    closed: 'closed_tickets',
    all: 'Tickets',
};

// Compares filter values the way the API reads them: 5, '5' and ['5'] are the same.
function normalizeFilterValue(value) {
    if (Array.isArray(value)) {
        return value.map(String).sort().join(',');
    }

    return (value === null || value === undefined || value === false) ? '' : String(value);
}

export default {
    name: 'TicketsMenu',
    components: {
        DynamicIcon,
        TicketViewsList
    },
    props: {
        isCollapsed: {
            type: Boolean,
            default: false
        }
    },
    emits: ['toggle-collapse'],
    data() {
        return {
            isMobile: false,
            isPhone: false,
            counts: null,
            countsLoadedAt: 0,
            closedGroups: this.$getData('ticket_sidebar_closed_groups', {}) || {},
            tagSearch: '',
            tagLimit: 8,
            switcherOpen: false,
            mobileQuery: null,
            phoneQuery: null,
        }
    },
    computed: {
        ...mapState(useTicketListStore, {
            storeTickets: 'tickets',
            storeFilters: 'filters',
            storeFilterType: 'filter_type',
            storeSearch: 'search',
            storeAdvancedFilters: 'advanced_filters',
            labelSearchList: 'labelSearchList',
            storeLabelSearchName: 'label_search_name',
            listTotal: (state) => state.pagination.total,
            listLoading: 'loading',
            // Set once the list has loaded in this page load; until then a ticket opened
            // directly has no list to step through and shows the full filters instead.
            listLoaded: (state) => !state.first_time_loading,
            lastFetchTime: (state) => state.cache_metadata.last_fetch_time,
        }),
        ...mapWritableState(useTicketListStore, {
            viewsSheetOpen: 'views_sheet_open',
        }),
        showSwitcher() {
            return this.isViewingTicket && this.listLoaded;
        },
        switcherLabel() {
            if (this.activeViewLabel) {
                return this.activeViewLabel;
            }

            if (this.storeFilterType === 'advanced') {
                return this.storeLabelSearchName || this.$t('Advanced Filter');
            }

            return this.storeSearch ? this.$t('Search Results') : this.$t('Filtered Tickets');
        },
        isViewingTicket() {
            return this.$route.name === 'view_ticket';
        },
        hasViewRoute() {
            return this.$route.name && this.$route.name.includes('view');
        },
        // While a ticket is open, the views still describe the list it came from.
        isListRoute() {
            return this.$route.name === 'tickets' || this.$route.name === 'view_ticket';
        },
        meId() {
            return this.appVars.me.id;
        },
        groups() {
            const counts = this.counts || {};
            const open = counts.open || {};
            const priorities = counts.priorities || {};
            const statuses = counts.statuses || {};
            const countOf = (bucket, key) => (this.counts ? (bucket[key] || 0) : null);

            const myWork = [
                this.viewItem('mine', this.$t('Assigned to Me'), 'User', { agent_id: [this.meId] }, countOf(open, 'mine')),
            ];

            // Agents who only see their own tickets never see an unassigned one.
            if (this.appVars.ticket_visibility !== 'assigned_only') {
                myWork.push(this.viewItem('unassigned', this.$t('Unassigned'), 'View', { agent_id: ['unassigned'] }, countOf(open, 'unassigned')));
            }

            if (this.has_pro) {
                myWork.push(this.viewItem('bookmarks', this.$t('Bookmarks'), 'CollectionTag', { status_type: 'all', agent_id: [this.meId], watcher: 'watcher' }, null));
            }

            // Highest priority first.
            const priorityItems = Object.keys(this.appVars.admin_priorities || {}).reverse().map((priority) => {
                const item = this.viewItem('priority_' + priority, this.appVars.admin_priorities[priority], 'filtersAdminPriority', { priority: [priority] }, countOf(priorities, priority));
                item.attention = priority === 'critical';
                item.dot = priority;
                return item;
            });

            const statusItems = Object.keys(this.appVars.ticket_statuses_group || {}).map((statusKey) => {
                const label = this.appVars.ticket_statuses[statusKey] || this.$t(statusKey);
                return this.viewItem('status_' + statusKey, label, STATUS_ICONS[statusKey] || 'Tickets', { status_type: statusKey }, countOf(statuses, statusKey));
            });

            const waiting = this.viewItem('waiting', this.$t('Waiting For Reply'), 'waiting_tickets', { waiting_for_reply: 'yes' }, countOf(open, 'waiting'));
            waiting.attention = true;

            const groups = [
                { key: 'my_work', title: this.$t('My Work'), items: myWork },
                { key: 'attention', title: this.$t('Needs Attention'), items: [waiting] },
                { key: 'priority', title: this.$t('Agent Priority'), items: priorityItems },
                { key: 'status', title: this.$t('Status'), items: statusItems },
            ];

            if (this.has_pro && this.labelSearchList && this.labelSearchList.length) {
                groups.push({
                    key: 'saved_filters',
                    title: this.$t('Saved Filters'),
                    items: this.labelSearchList.map((folder) => this.folderItem(folder)),
                });
            }

            return groups;
        },
        visibleGroups() {
            // The collapsed rail shows icons only; saved filters have no distinct icons.
            return this.isCollapsed ? this.groups.filter((group) => group.key !== 'saved_filters') : this.groups;
        },
        allTags() {
            return this.appVars.ticket_tags || [];
        },
        // Tags past the visible few still name the view, so look through all of them.
        activeViewLabel() {
            for (const group of this.groups) {
                const item = group.items.find((groupItem) => groupItem.active);
                if (item) {
                    return item.label;
                }
            }

            const tag = this.allTags.find((tagItem) => this.isViewActive({ ...defaultFilters(), ticket_tags: [tagItem.id] }));

            return tag ? tag.title : '';
        },
        tagItems() {
            const search = this.tagSearch.trim().toLowerCase();
            let tags = this.allTags;

            if (search) {
                tags = tags.filter((tag) => String(tag.title).toLowerCase().includes(search));
            } else {
                tags = tags.slice(0, this.tagLimit);
            }

            return tags.map((tag) => this.viewItem('tag_' + tag.id, tag.title, 'filtersTagIcon', { ticket_tags: [tag.id] }, null));
        },
    },
    watch: {
        activeViewLabel: {
            immediate: true,
            handler(label) {
                useTicketListStore().active_view_label = label;
            },
        },
        lastFetchTime(time) {
            if (time && Date.now() - this.countsLoadedAt > COUNTS_REFRESH_MS) {
                this.fetchCounts();
            }
        },
    },
    methods: {
        // updated_at also moves on edits like priority or assignee, so show the latest reply instead.
        lastActivity(ticket) {
            const customer = ticket.last_customer_response;
            const agent = ticket.last_agent_response;
            if (customer && agent) {
                return this.$dayjs(agent).isAfter(customer) ? agent : customer;
            }
            return agent || customer || ticket.created_at;
        },
        getExcerpt(ticket) {
            const html = (ticket.preview_response) ? ticket.preview_response.content : ticket.content;

            if (!html) {
                return '';
            }

            // textContent strips tags and decodes entities (&amp; → &, &#039; → '). Run it twice:
            // some piped emails store their markup escaped (&lt;p&gt;), which one pass leaves as <p>.
            const parse = (value) => new DOMParser().parseFromString(value, 'text/html').body.textContent;
            const text = parse(parse(html));

            return text.substring(0, 100);
        },

        viewItem(key, label, icon, viewFilters, count) {
            const filters = { ...defaultFilters(), ...viewFilters };

            return {
                key,
                label,
                icon,
                count,
                dot: '',
                attention: false,
                active: this.isViewActive(filters),
                open: (inPlace) => this.openView(filters, inPlace),
            };
        },

        folderItem(folder) {
            let advancedFilters = null;
            try {
                advancedFilters = JSON.parse(folder.advanced_filters);
            } catch (e) {
                advancedFilters = null;
            }

            return {
                key: 'folder_' + folder.id,
                label: folder.label_search_name,
                icon: 'labelSearch',
                count: null,
                attention: false,
                active: this.isListRoute && this.storeFilterType === 'advanced'
                    && JSON.stringify(advancedFilters) === JSON.stringify(this.storeAdvancedFilters),
                open: (inPlace) => this.openFolder(folder, advancedFilters, inPlace),
            };
        },

        isViewActive(filters) {
            if (!this.isListRoute || this.storeFilterType !== 'simple' || this.storeSearch) {
                return false;
            }

            const current = this.storeFilters || {};

            return Object.keys(filters).every((key) => normalizeFilterValue(filters[key]) === normalizeFilterValue(current[key]));
        },

        // Leaves the list on its own route with no query, so the route watchers in
        // AllTickets don't overwrite the view after it is applied.
        goToList() {
            if (this.$route.name === 'tickets' && !Object.keys(this.$route.query).length) {
                return Promise.resolve();
            }

            return this.$router.push({ name: 'tickets' }).catch(() => {});
        },

        // The list restores its filters from local storage when it mounts.
        persistListState(filterType, values) {
            const pref = this.$getData('tickets_pref', false);
            if (pref) {
                this.$saveData('tickets_pref', { ...pref, search: '', current_page: 1 });
            }

            this.$saveData('tickets_filter_type', filterType);
            this.$saveData(filterType === 'advanced' ? 'tickets_advanced_filters' : 'tickets_filter', values);
            this.saveRoutes();
        },

        // inPlace reloads the list beside an open ticket instead of going back to the list page.
        openView(filters, inPlace = false) {
            const wasOnList = this.$route.name === 'tickets';
            this.persistListState('simple', filters);

            const apply = () => {
                const store = useTicketListStore();
                store.applyView(filters);
                this.fetchList(store);
            };

            if (inPlace) {
                apply();
                return;
            }

            this.goToList().then(() => {
                if (wasOnList) {
                    apply();
                }
            });
        },

        openFolder(folder, advancedFilters, inPlace = false) {
            if (!advancedFilters) {
                return;
            }

            const wasOnList = this.$route.name === 'tickets';
            this.persistListState('advanced', advancedFilters);

            const apply = () => {
                const store = useTicketListStore();
                store.handleAdvanceSearch(folder);
                store.pagination.current_page = 1;
                this.fetchList(store);
            };

            if (inPlace) {
                apply();
                return;
            }

            this.goToList().then(() => {
                if (wasOnList) {
                    apply();
                }
            });
        },

        fetchList(store) {
            store.fetchTickets(true).catch((error) => this.$handleError(error));
        },

        fetchCounts() {
            this.countsLoadedAt = Date.now();
            this.$get('tickets/sidebar-counts')
                .then((response) => {
                    this.counts = response.counts;
                })
                .catch(() => {
                    // Counts are a hint; the views work without them.
                    this.countsLoadedAt = 0;
                });
        },

        formatCount(count) {
            if (count < 1000) {
                return count;
            }

            try {
                return new Intl.NumberFormat(undefined, { notation: 'compact', maximumFractionDigits: 1 }).format(count);
            } catch (e) {
                return count;
            }
        },

        toggleGroup(key) {
            this.closedGroups = { ...this.closedGroups, [key]: !this.closedGroups[key] };
            this.$saveData('ticket_sidebar_closed_groups', this.closedGroups);
        },

        findItem(key) {
            for (const group of this.groups) {
                const item = group.items.find((groupItem) => groupItem.key === key);
                if (item) {
                    return item;
                }
            }

            return null;
        },

        saveRoutes(currentRouteQuery = {}) {
            this.$saveData("routesData", JSON.stringify(currentRouteQuery));
        },

        onMobileChange(event) {
            this.isMobile = event.matches;
            if (!this.isMobile) {
                this.viewsSheetOpen = false;
            }
        },

        onPhoneChange(event) {
            this.isPhone = event.matches;
        },

        handleKeydown(event) {
            const { code } = event;
            const { primary, shift } = shortcutModifiers(event);

            if (!primary || !shift || isTypingTarget(event.target)) return;

            const keyItems = {
                KeyA: 'status_open',
                KeyM: 'mine',
                KeyU: 'unassigned',
                KeyB: 'bookmarks',
            };

            const item = keyItems[code] && this.findItem(keyItems[code]);
            if (item) {
                event.preventDefault();
                item.open();
            }
        },
    },
    mounted() {
        this.mobileQuery = window.matchMedia('(max-width: 1024px)');
        this.isMobile = this.mobileQuery.matches;
        this.mobileQuery.addEventListener('change', this.onMobileChange);
        this.phoneQuery = window.matchMedia('(max-width: 767px)');
        this.isPhone = this.phoneQuery.matches;
        this.phoneQuery.addEventListener('change', this.onPhoneChange);
        this.fetchCounts();

        if (this.has_pro) {
            useTicketListStore().fetchLabelSearches().catch(() => {});
        }

        if(this.appVars.keyboard_shortcuts === 'yes') {
            window.addEventListener('keydown', this.handleKeydown);
        }
    },
    unmounted() {
        if (this.mobileQuery) {
            this.mobileQuery.removeEventListener('change', this.onMobileChange);
            this.phoneQuery.removeEventListener('change', this.onPhoneChange);
        }

        if(this.appVars.keyboard_shortcuts === 'yes') {
            window.removeEventListener('keydown', this.handleKeydown);
        }
    }
};
</script>
