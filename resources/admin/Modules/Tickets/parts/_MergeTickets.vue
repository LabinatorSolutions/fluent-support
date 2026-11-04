<template>
    <el-dialog
        v-model="visible"
        @closed="$emit('close')"
        :title="$t('Merge Tickets')"
        :append-to-body="true"
        class="fs_dialog fs_dialog_merge_ticket"
    >
        <div class="fs_box_body" v-if="customer_tickets.length && app_ready" v-loading="merge_loading">
            <el-table
                :data="customer_tickets"
                style="width: 100%"
                @selection-change="handleMergeSelectionChange"
            >
                <el-table-column type="selection" width="55" />
                <el-table-column prop="id" :label="$t('ID')" width="130"></el-table-column>
                <el-table-column prop="title" :label="$t('Title')" width="250"></el-table-column>
                <el-table-column prop="status" :label="$t('Status')" width="130"></el-table-column>
            </el-table>
            <div class="fs_tk_merge_actions">
                <div style="padding-bottom: 20px;" class="fs_pagination_wrapper" v-if="customer_tickets.length">
                    <pagination @fetch="customerTickets" :pagination="pagination"/>
                </div>
                <el-button class="fs_filled_btn" size="default" type="primary"
                           v-loading="merging" :disabled="merging"
                           @click="mergeTickets()">
                    {{ $t('Merge') }}
                </el-button>
            </div>
        </div>
        <div class="fs_box_body" v-else-if="!customer_tickets.length && app_ready">
            <h3>{{ $t('customer_has_one_tk') }}</h3>
        </div>
        <div class="fs_box_body" v-else>
            <TableSkeleton :rows="5" :columns="['55px', '130px', '250px', '130px']" />
        </div>
    </el-dialog>
</template>

<script type="text/babel">
import ticketService from '@/admin/services/ticketService';
import Pagination from '@/admin/Pieces/Pagination';
import TableSkeleton from '@/admin/Components/TableSkeleton.vue';

/**
 * Pro: merge the customer's other tickets into this one. Lists them when it
 * opens; emits merged after a merge and close when the dialog has closed.
 */
export default {
    name: 'MergeTickets',
    components: { Pagination, TableSkeleton },
    props: ['ticket'],
    emits: ['merged', 'close'],
    data() {
        return {
            visible: true,
            app_ready: false,
            merge_loading: false,
            merging: false,
            customer_tickets: [],
            tickets_to_merge: [],
            pagination: {
                current_page: 1,
                total: 0,
                per_page: 10
            }
        };
    },
    methods: {
        customerTickets() {
            this.merge_loading = true;
            ticketService.customerTickets(this.ticket.customer_id, {
                exclude_ticket_id: this.ticket.id,
                page: this.pagination.current_page,
                per_page: this.pagination.per_page,
            })
                .then(response => {
                    this.app_ready = true;
                    this.customer_tickets = response.tickets.data;
                    this.pagination.total = response.tickets.total;
                })
                .catch((errors) => {
                    this.$handleError(errors);
                })
                .always(() => {
                    this.merge_loading = false;
                });
        },

        handleMergeSelectionChange(selected) {
            this.tickets_to_merge = selected;
        },

        mergeTickets() {
            this.$confirm({
                message: this.$t('Are you sure you want to merge these tickets?'),
                title: this.$t('Merge Tickets'),
                type: 'Warning',
                options: {
                    confirmButtonText: this.$t('Merge'),
                    cancelButtonText: this.$t('Cancel'),
                    type: 'warning',
                    cancelButtonClass: 'el-button--default fs_outline_btn',
                    confirmButtonClass: 'el-button--danger fs_filled_btn',
                }
            }).then(() => {
                this.merging = true;
                ticketService.merge(this.ticket.id, this.tickets_to_merge.map(ticket => parseInt(ticket.id)))
                    .then(response => {
                        this.$notify({
                            message: response.message,
                            type: "success",
                            position: "bottom-right",
                        });
                        this.customerTickets();
                        this.$emit('merged');
                    })
                    .catch((error) => {
                        this.$handleError(error);
                    })
                    .always(() => {
                        this.merging = false;
                    });
            });
        }
    },
    mounted() {
        this.customerTickets();
    }
};
</script>
