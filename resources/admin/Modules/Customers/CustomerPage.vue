<template>
    <div class="fs_customer_page">
        <div v-if="customer.id">
            <div class="fs_customer_page_head">
                <el-breadcrumb separator=">">
                    <el-breadcrumb-item :to="{ name: 'Customers' }">{{ $t('Customers') }}</el-breadcrumb-item>
                    <el-breadcrumb-item>{{ customer.full_name || customer.email }}</el-breadcrumb-item>
                </el-breadcrumb>
                <div class="fs_customer_page_actions">
                    <el-button class="fs_outline_btn" icon="EditPen" @click="openEditModal()">
                        {{ $t('Edit') }}
                    </el-button>
                    <el-dropdown trigger="click" placement="bottom-end" @command="handleCommand">
                        <el-button class="fs_outline_btn fs_customer_more_btn" icon="More" :aria-label="$t('More actions')"/>
                        <template #dropdown>
                            <el-dropdown-menu class="fs_global_dropdown">
                                <el-dropdown-item command="tickets">
                                    <div class="fs_dropdown_item_content">
                                        <el-icon class="fs_dropdown_icon"><Tickets /></el-icon>
                                        {{ $t('View in ticket list') }}
                                    </div>
                                </el-dropdown-item>
                                <el-dropdown-item v-if="customer.avatar" command="reset_avatar">
                                    <div class="fs_dropdown_item_content">
                                        <el-icon class="fs_dropdown_icon"><Refresh /></el-icon>
                                        {{ $t('Reset photo to Gravatar') }}
                                    </div>
                                </el-dropdown-item>
                                <el-dropdown-item v-if="canDelete" command="delete" class="fs_delete_action" divided>
                                    <div class="fs_dropdown_item_content">
                                        <el-icon class="fs_dropdown_icon"><Delete /></el-icon>
                                        {{ $t('Delete customer') }}
                                    </div>
                                </el-dropdown-item>
                            </el-dropdown-menu>
                        </template>
                    </el-dropdown>
                </div>
            </div>

            <el-row :gutter="24">
                <el-col :xs="24" :md="16" :lg="17">
                    <div class="fs_box_wrapper fs_customer_tickets_box">
                        <div class="fs_box_header">
                            <div class="fs_box_head">
                                <h3>{{ $t('Tickets') }}</h3>
                            </div>
                            <div class="fs_box_actions">
                                <div class="fs_status_tabs">
                                    <div class="fs_segmented_control">
                                        <button
                                            v-for="(label, key) in statusTabs"
                                            :key="key"
                                            type="button"
                                            @click="setStatusTab(key)"
                                            :class="['fs_segment_button', { 'fs_segment_active': ticketFilters.status_type === key }]"
                                        >
                                            {{ label }}
                                        </button>
                                    </div>
                                </div>
                                <div class="fs_table_search_field">
                                    <el-input
                                        v-model="ticketFilters.search"
                                        clearable
                                        class="fs_text_input fs_table_search_input"
                                        :placeholder="$t('Search tickets...')"
                                        @keyup.enter="fetchTickets(true)"
                                        @clear="fetchTickets(true)"
                                    >
                                        <template #prefix>
                                            <el-icon class="el-input__icon"><Search /></el-icon>
                                        </template>
                                    </el-input>
                                </div>
                            </div>
                        </div>

                        <div class="fs_table_wrapper">
                            <el-table
                                :data="tickets"
                                v-loading="loadingTickets"
                                row-class-name="fs_table_row fs_customer_ticket_row"
                                header-row-class-name="fs_table_header_row"
                                cell-class-name="fs_table_cell"
                                header-cell-class-name="fs_table_header_cell"
                                @row-click="openTicket"
                            >
                                <el-table-column :label="$t('Ticket')" min-width="280">
                                    <template #default="{ row }">
                                        <div class="fs_customer_ticket_title">
                                            <router-link :to="{ name: 'view_ticket', params: { ticket_id: row.id } }" @click.stop>
                                                {{ row.title }}
                                            </router-link>
                                            <span class="fs_customer_ticket_meta">
                                                #{{ row.id }}
                                                <template v-if="row.product"> · {{ row.product.title }}</template>
                                                <template v-if="row.mailbox && hasMultipleMailboxes"> · {{ row.mailbox.name }}</template>
                                            </span>
                                        </div>
                                    </template>
                                </el-table-column>
                                <el-table-column :label="$t('Status')" width="120">
                                    <template #default="{ row }">
                                        <span class="fs_status_badge" :class="`fs_status_${row.status}`">
                                            {{ appVars.ticket_statuses[row.status] || row.status }}
                                        </span>
                                    </template>
                                </el-table-column>
                                <el-table-column :label="$t('Assignee')" width="170">
                                    <template #default="{ row }">
                                        <span v-if="row.agent" class="fs_customer_ticket_agent">{{ row.agent.full_name }}</span>
                                        <span v-else class="fs_customer_muted">{{ $t('Unassigned') }}</span>
                                    </template>
                                </el-table-column>
                                <el-table-column :label="$t('Last Update')" width="150">
                                    <template #default="{ row }">
                                        <span :title="dateTimeFormat(row.updated_at, 'DD MMM YYYY, hh:mm A')">
                                            {{ $timeDiff(row.updated_at) }}
                                        </span>
                                    </template>
                                </el-table-column>
                                <template #empty>
                                    <div class="fs_table_empty_state">
                                        <el-empty :description="emptyTicketsText" :image-size="110">
                                            <template #image>
                                                <TableEmptyStateImage />
                                            </template>
                                        </el-empty>
                                    </div>
                                </template>
                            </el-table>
                        </div>

                        <div class="fs_pagination_wrapper" v-if="ticketPagination.total > ticketPagination.per_page">
                            <span class="fs_pagination_left"></span>
                            <span class="fs_pagination_right">
                                <pagination
                                    @fetch="fetchTickets()"
                                    :pagination="ticketPagination"
                                    :background="true"
                                    layout="prev, pager, next"
                                />
                            </span>
                        </div>
                    </div>
                </el-col>

                <el-col :xs="24" :md="8" :lg="7" class="fs_customer_sidebar_col">
                    <div class="fs_customer_sidebar">
                        <div class="fs_box_wrapper fs_customer_profile_card">
                            <div class="fs_customer_profile_head">
                                <div class="fs_customer_profile_avatar">
                                    <img v-if="customer.photo" :src="customer.photo" :alt="customer.full_name"/>
                                    <el-upload
                                        class="fs_customer_avatar_upload"
                                        :action="upload_url"
                                        :headers="requestHeaders"
                                        :show-file-list="false"
                                        accept="image/*"
                                        :on-success="handleAvatarSuccess"
                                        :on-error="handleAvatarError"
                                    >
                                        <el-tooltip :content="$t('Upload a custom photo')" placement="bottom">
                                            <span class="fs_customer_avatar_upload_btn" role="button" tabindex="0" :aria-label="$t('Upload a custom photo')">
                                                <el-icon><Camera /></el-icon>
                                            </span>
                                        </el-tooltip>
                                    </el-upload>
                                </div>
                                <div class="fs_customer_profile_name">
                                    <h2>{{ customer.full_name || customer.email }}</h2>
                                    <span class="fs_customer_email_row">
                                        <a :href="'mailto:' + customer.email" :title="customer.email">{{ customer.email }}</a>
                                        <el-button
                                            text
                                            size="small"
                                            icon="DocumentCopy"
                                            class="fs_customer_copy_btn"
                                            :aria-label="$t('Copy email')"
                                            @click="$copyToClipboard(customer.email)"
                                        />
                                    </span>
                                    <p v-if="customer.title" class="fs_customer_profile_title">{{ customer.title }}</p>
                                    <span v-if="customer.status === 'inactive'" class="fs_status_badge fs_status_blocked">
                                        {{ $t('Blocked') }}
                                    </span>
                                </div>
                            </div>

                            <dl class="fs_customer_profile_stats">
                                <div>
                                    <dt>{{ $t('Open') }}</dt>
                                    <dd>{{ ticketCounts.open }}</dd>
                                </div>
                                <div>
                                    <dt>{{ $t('Closed') }}</dt>
                                    <dd>{{ ticketCounts.closed }}</dd>
                                </div>
                                <div>
                                    <dt>{{ $t('Total') }}</dt>
                                    <dd>{{ ticketCounts.total }}</dd>
                                </div>
                            </dl>

                            <ul class="fs_customer_profile_details">
                                <li v-if="customerAddress">
                                    <span class="fs_customer_detail_label">{{ $t('Address') }}</span>
                                    <span class="fs_customer_detail_value">{{ customerAddress }}</span>
                                </li>
                                <li>
                                    <span class="fs_customer_detail_label">{{ $t('Created at') }}</span>
                                    <span class="fs_customer_detail_value">{{ dateTimeFormat(customer.created_at, 'DD MMM YYYY') }}</span>
                                </li>
                                <li>
                                    <span class="fs_customer_detail_label">{{ $t('Last activity') }}</span>
                                    <span class="fs_customer_detail_value">{{ $timeDiff(customer.last_response_at) || '--' }}</span>
                                </li>
                                <li v-if="customer.user_id">
                                    <span class="fs_customer_detail_label">{{ $t('WordPress user') }}</span>
                                    <span class="fs_customer_detail_value">
                                        <a v-if="customer.profile_edit_url" :href="customer.profile_edit_url" target="_blank" rel="noopener">#{{ customer.user_id }}</a>
                                        <template v-else>#{{ customer.user_id }}</template>
                                    </span>
                                </li>
                            </ul>

                            <div v-if="customer.note" class="fs_customer_profile_note">
                                <span class="fs_customer_detail_label">{{ $t('Note') }}</span>
                                <p>{{ customer.note }}</p>
                            </div>
                        </div>

                        <div class="fs_tk_sidebar_wrap_new">
                            <collapsible-widget
                                v-if="fluentcrm_profile"
                                :title="$t('FluentCRM Profile')"
                                icon="User"
                                :default-expanded="true"
                                class="fs_sidebar_widget"
                            >
                                <fluent-crm-profile :crm_profile="fluentcrm_profile" />
                            </collapsible-widget>

                            <collapsible-widget
                                v-for="(widget, widget_key) in renderableWidgets"
                                :key="widget_key"
                                :title="widget.title || widget.header"
                                :icon="widgetIcon(widget)"
                                :default-expanded="false"
                                :class="`fs_tk_widget_${widget_key}`"
                                class="fs_sidebar_widget fs_tk_extra_card"
                            >
                                <!-- Widgets sent as stats / sections -->
                                <structured-widget v-if="isStructuredWidget(widget)" :widget="widget" />
                                <!-- Other Widgets -->
                                <div v-else-if="widget.body_html" class="fs_widget_custom_body">
                                    <div v-html="sanitizeWidgetHtml(widget.body_html)"></div>
                                </div>
                            </collapsible-widget>
                        </div>
                    </div>
                </el-col>
            </el-row>
        </div>
        <div v-else aria-busy="true">
            <div class="fs_customer_page_head">
                <el-skeleton animated style="width: 220px;">
                    <template #template>
                        <el-skeleton-item variant="text" />
                    </template>
                </el-skeleton>
            </div>
            <el-row :gutter="24">
                <el-col :xs="24" :md="16" :lg="17">
                    <div class="fs_box_wrapper fs_customer_tickets_box">
                        <div class="fs_box_header">
                            <div class="fs_box_head">
                                <h3>{{ $t('Tickets') }}</h3>
                            </div>
                        </div>
                        <TableSkeleton :rows="5" :columns="[2.8, '120px', '170px', '150px']" />
                    </div>
                </el-col>
                <el-col :xs="24" :md="8" :lg="7" class="fs_customer_sidebar_col">
                    <div class="fs_box_wrapper fs_customer_profile_card">
                        <el-skeleton animated>
                            <template #template>
                                <div class="fs_customer_profile_head">
                                    <el-skeleton-item variant="circle" style="width: 56px; height: 56px; flex-shrink: 0;" />
                                    <div style="flex: 1;">
                                        <el-skeleton-item variant="h3" style="width: 70%;" />
                                        <el-skeleton-item variant="text" style="width: 85%; margin-top: 8px;" />
                                    </div>
                                </div>
                                <el-skeleton-item variant="rect" style="width: 100%; height: 60px; margin-top: 20px; border-radius: 8px;" />
                                <div v-for="n in 3" :key="n" style="display: flex; justify-content: space-between; gap: 12px; margin-top: 16px;">
                                    <el-skeleton-item variant="text" style="width: 35%;" />
                                    <el-skeleton-item variant="text" style="width: 40%;" />
                                </div>
                            </template>
                        </el-skeleton>
                    </div>
                </el-col>
            </el-row>
        </div>

        <el-dialog
            v-if="editing_customer"
            v-model="showEditModal"
            :title="$t('Update customer')"
            :append-to-body="true"
            width="60%"
            class="fs_dialog"
        >
            <customer-form :customer="editing_customer" :hide-buttons="true" />
            <template #footer>
                <span class="fs_dialog_footer">
                    <el-button class="fs_outline_btn" @click="showEditModal = false">{{ $t('Cancel') }}</el-button>
                    <el-button class="fs_filled_btn" v-loading="saving" :disabled="saving" @click="updateCustomer()">
                        {{ $t('Update Customer') }}
                    </el-button>
                </span>
            </template>
        </el-dialog>
    </div>
</template>

<script type="text/babel">
import CustomerForm from './_CustomerForm';
import Pagination from '../../Pieces/Pagination';
import TableEmptyStateImage from '@/admin/Components/TableEmptyStateImage.vue';
import TableSkeleton from '@/admin/Components/TableSkeleton.vue';
import FluentCrmProfile from '@/admin/Modules/Tickets/parts/_CrmProfile';
import CollapsibleWidget from "@/admin/Modules/Tickets/parts/_CollapsibleWidget.vue";
import StructuredWidget, { WIDGET_ICONS, isStructuredWidget, filterRenderableWidgets } from "@/admin/Modules/Tickets/parts/_StructuredWidget.vue";
import { sanitizeWidgetHtml } from '@/common/domPurifyGuards';
import { canDeleteCustomers } from './customerPermissions';

export default {
    name: "CustomerPage",
    props: ['customer_id'],
    components: {
        CollapsibleWidget,
        StructuredWidget,
        CustomerForm,
        FluentCrmProfile,
        Pagination,
        TableEmptyStateImage,
        TableSkeleton
    },
    data() {
        return {
            customer: {},
            ticket_stats: {},
            widgets: {},
            fluentcrm_profile: false,
            loading: false,
            saving: false,
            showEditModal: false,
            editing_customer: null,
            tickets: [],
            loadingTickets: false,
            // Only the latest ticket request may update the table (tabs and search can overlap).
            ticketsFetchRequestId: 0,
            ticketFilters: {
                status_type: 'all',
                search: ''
            },
            ticketPagination: {
                current_page: 1,
                per_page: 20,
                total: 0
            },
            requestHeaders: {
                'X-WP-Nonce': this.appVars.rest.nonce
            }
        }
    },
    computed: {
        upload_url() {
            return this.appVars.rest.url + `/customers/profile_image/${this.customer_id}`;
        },
        statusTabs() {
            return {
                all: this.$t('All'),
                open: this.$t('Open'),
                closed: this.$t('Closed')
            };
        },
        ticketCounts() {
            const stats = this.ticket_stats || {};
            const openStatuses = (this.appVars.ticket_statuses_group || {}).open || ['new', 'active'];
            const closedStatuses = (this.appVars.ticket_statuses_group || {}).closed || ['closed'];
            const sum = (statuses) => statuses.reduce((total, status) => total + (stats[status] || 0), 0);

            return {
                open: sum(openStatuses),
                closed: sum(closedStatuses),
                total: Object.values(stats).reduce((total, count) => total + count, 0)
            };
        },
        customerAddress() {
            const c = this.customer;
            return [c.address_line_1, c.address_line_2, c.city, c.state, c.zip, c.country]
                .filter(Boolean)
                .join(', ');
        },
        hasMultipleMailboxes() {
            return (this.appVars.mailboxes || []).length > 1;
        },
        emptyTicketsText() {
            if (this.ticketFilters.search || this.ticketFilters.status_type !== 'all') {
                return this.$t('No tickets match your filters');
            }
            return this.$t('This customer has no tickets yet');
        },
        canDelete() {
            return canDeleteCustomers(this.appVars);
        },
        // Widgets in an older custom format have nothing to draw: skip them.
        renderableWidgets() {
            return filterRenderableWidgets(this.widgets);
        }
    },
    watch: {
        customer_id() {
            this.customer = {};
            this.ticketFilters = { status_type: 'all', search: '' };
            this.fetchCustomer();
            this.fetchTickets(true);
        }
    },
    mounted() {
        this.fetchCustomer();
        this.fetchTickets(true);
    },
    methods: {
        isStructuredWidget,
        sanitizeWidgetHtml,
        widgetIcon(widget) {
            return WIDGET_ICONS.includes(widget.icon) ? widget.icon : 'ShoppingCart';
        },
        fetchCustomer() {
            this.loading = true;
            this.$get(`customers/${this.customer_id}`, {
                with: ['widgets', 'ticket_stats', 'fluentcrm_profile']
            })
                .then(response => {
                    this.customer = response.customer || {};
                    this.ticket_stats = response.ticket_stats || {};
                    this.widgets = response.widgets;
                    this.fluentcrm_profile = response.fluentcrm_profile;
                    this.$setTitle(this.customer.full_name || this.$t('Customer'));
                })
                .catch(errors => {
                    this.$handleError(errors);
                })
                .always(() => {
                    this.loading = false;
                });
        },
        fetchTickets(resetPage = false) {
            if (resetPage) {
                this.ticketPagination.current_page = 1;
            }
            this.loadingTickets = true;
            const requestId = ++this.ticketsFetchRequestId;
            this.$get('tickets', {
                customer_id: this.customer_id,
                filters: { status_type: this.ticketFilters.status_type },
                search: this.ticketFilters.search,
                per_page: this.ticketPagination.per_page,
                page: this.ticketPagination.current_page,
                order_by: 'id',
                order_type: 'DESC'
            })
                .then(response => {
                    if (requestId !== this.ticketsFetchRequestId) {
                        return;
                    }
                    this.tickets = response.tickets.data;
                    this.ticketPagination.total = response.tickets.total;
                })
                .catch(errors => {
                    if (requestId !== this.ticketsFetchRequestId) {
                        return;
                    }
                    this.$handleError(errors);
                })
                .always(() => {
                    if (requestId !== this.ticketsFetchRequestId) {
                        return;
                    }
                    this.loadingTickets = false;
                });
        },
        setStatusTab(key) {
            this.ticketFilters.status_type = key;
            this.fetchTickets(true);
        },
        openTicket(row) {
            this.$router.push({ name: 'view_ticket', params: { ticket_id: row.id } });
        },
        openEditModal() {
            this.editing_customer = { ...this.customer };
            this.showEditModal = true;
        },
        updateCustomer() {
            this.saving = true;
            this.$put(`customers/${this.customer.id}`, this.editing_customer)
                .then(response => {
                    this.$notify({
                        message: response.message,
                        type: 'success',
                        position: 'bottom-right'
                    });
                    this.showEditModal = false;
                    this.fetchCustomer();
                })
                .catch(errors => {
                    this.$handleError(errors);
                })
                .always(() => {
                    this.saving = false;
                });
        },
        handleCommand(command) {
            if (command === 'tickets') {
                this.$router.push({ name: 'tickets', query: { search: 'customer_id:' + this.customer.id } });
            } else if (command === 'reset_avatar') {
                this.resetAvatar();
            } else if (command === 'delete') {
                this.deleteCustomer();
            }
        },
        handleAvatarSuccess(response) {
            this.customer.photo = response.image;
            this.customer.avatar = response.image;
            this.$notify({
                message: response.message,
                type: 'success',
                position: 'bottom-right'
            });
        },
        handleAvatarError(err) {
            let message = this.$t('Something went wrong');
            try {
                message = JSON.parse(err.message).message || message;
            } catch (e) {
                // Not a JSON error body: keep the generic message.
            }
            this.$notify({
                message,
                type: 'error',
                position: 'bottom-right'
            });
        },
        resetAvatar() {
            this.$confirm({ message: this.$t('Reset to gravatar?') })
                .then(() => {
                    this.$post(`customers/reset_avatar/${this.customer_id}`)
                        .then(response => {
                            this.$notify({
                                message: response.message,
                                type: 'success',
                                position: 'bottom-right'
                            });
                            this.fetchCustomer();
                        })
                        .catch(errors => {
                            this.$handleError(errors);
                        });
                })
                .catch(() => {});
        },
        deleteCustomer() {
            this.$confirm({
                message: this.$t('Are you sure to delete this customer? It will delete all associated data with this customer'),
                options: {
                    confirmButtonText: this.$t('Delete'),
                    cancelButtonText: this.$t('Cancel'),
                    type: 'warning',
                    cancelButtonClass: 'el-button--default fs_outline_btn',
                    confirmButtonClass: 'el-button--danger fs_filled_btn'
                }
            })
                .then(() => {
                    this.$del(`customers/${this.customer_id}`)
                        .then(response => {
                            this.$notify({
                                message: response.message,
                                type: 'success',
                                position: 'bottom-right'
                            });
                            this.$router.push({ name: 'Customers' });
                        })
                        .catch(errors => {
                            this.$handleError(errors);
                        });
                })
                .catch(() => {});
        }
    }
}
</script>
