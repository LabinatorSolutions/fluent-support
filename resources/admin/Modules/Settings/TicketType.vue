<template>
    <div class="fs_ticket_types">
        <div class="fs_box_wrapper">
            <div class="fs_box_header">
                <div class="fs_box_head">
                    <h3>{{$t('Ticket Types')}}</h3>
                    <p style="color: #f56c6b; font-weight: 500;">{{$t('ticket_type_help_text')}}</p>
                </div>
                <div class="fs_box_actions">
                    <el-button @click="createTicketModal()" type="primary" icon="el-icon-plus" size="small">{{$t('Create New')}}
                    </el-button>
                </div>
            </div>
            <div v-if="!fetching" class="fs_box_body">
                <el-table stripe :data="ticketTypes">
                    <el-table-column width="80" prop="id" :label="$t('ID')"></el-table-column>
                    <el-table-column prop="title" :label="$t('Title')"></el-table-column>
                    <el-table-column prop="description" :label="$t('Description')"></el-table-column>
                    <el-table-column width="120" :label="$t('Action')">
                        <template #default="scope">
                            <el-button @click="editTicketTypeModal(scope.row)" size="mini" type="success"
                                       icon="el-icon-edit"></el-button>
                            <el-button @click="deleteTicketType(scope.row)" size="mini" type="danger" icon="el-icon-delete"></el-button>
                        </template>
                    </el-table-column>
                </el-table>
                <div class="fframe_pagination_wrapper">
                    <pagination @fetch="getTicketTypes()" :pagination="pagination"/>
                </div>
            </div>
            <div style="padding: 20px; background: white;" class="fs_box_body" v-else>
                <el-skeleton :rows="5" animated/>
            </div>
        </div>

        <el-dialog
            :append-to-body="true"
            :title="(editing_ticket_type && editing_ticket_type.id) ? $t('Update Type') : $t('Create New Ticket Type')"
            v-model="ticket_modal"
            v-if="editing_ticket_type"
            width="60%">

            <el-form label-position="top" :data="editing_ticket_type">
                <el-form-item :label="$t('Title')">
                    <el-input type="text" :placeholder="$t('Ticket Type Title')" v-model="editing_ticket_type.title"/>
                </el-form-item>
                <el-form-item :label="$t('Description')">
                    <el-input type="textarea" :placeholder="$t('Ticket Type Description')" v-model="editing_ticket_type.description"/>
                </el-form-item>
            </el-form>

            <template #footer>
                <span class="dialog-footer">
                  <el-button v-loading="saving" :disabled="saving" type="primary" @click="createOrUpdateTicketType()">{{$t('Save')}}</el-button>
                </span>
            </template>
        </el-dialog>

    </div>
</template>

<script type="text/babel">
import Pagination from '../../Pieces/Pagination'

export default {
    name: 'TicketType',
    components: {
        Pagination
    },
    data() {
        return {
            fetching: false,
            ticketTypes: [],
            pagination: {
                current_page: 1,
                per_page: 10,
                total: 0
            },
            saving: false,
            ticket_modal: false,
            editing_ticket_type: false
        }
    },
    methods: {
        getTicketTypes() {
            this.fetching = true;
            this.$get('ticket-types', {
                per_page: this.pagination.per_page,
                page: this.pagination.current_page
            })
                .then(response => {
                    this.ticketTypes = response.ticket_type.data;
                    this.pagination.total = response.ticket_type.total;
                })
                .catch(errors => {
                    this.$handleError(errors);
                })
                .always(() => {
                    this.fetching = false;
                });
        },
        createOrUpdateTicketType() {
            this.saving = true;
            let method = this.$post;
            let route = 'ticket-types';
            if (this.editing_ticket_type.id) {
                method = this.$put;
                route = `ticket-types/${this.editing_ticket_type.id}`;
            }

            method(route, {
                ...this.editing_ticket_type
            })
                .then(response => {
                    this.$notify({
                        message: response.message,
                        type: 'success',
                        position: 'bottom-right'
                    });
                    this.getTicketTypes();
                    this.ticket_modal = false;
                })
                .catch((errors) => {
                    this.$handleError(errors);
                })
                .always(() => {
                    this.saving = false;
                });

        },
        editTicketTypeModal(ticket_type) {
            this.editing_ticket_type = JSON.parse(JSON.stringify(ticket_type));
            this.ticket_modal = true;
        },
        createTicketModal() {
            this.editing_ticket_type = {
                title: '',
                description: ''
            }
            this.ticket_modal = true;
        },
        deleteTicketType(ticket_type) {
            const r = confirm(this.$t("Are you sure, You want to delete this?"));

            if (!r) {
                return ;
            }

            this.$del(`ticket-types/${ticket_type.id}`)
                .then(response => {
                    this.$notify({
                        message: response.message,
                        type: 'success',
                        position: 'bottom-right'
                    });

                    this.getTicketTypes();
                });
        }
    },
    mounted() {
        this.getTicketTypes();
        this.$setTitle('Ticket Type Settings');
    }
}
</script>
