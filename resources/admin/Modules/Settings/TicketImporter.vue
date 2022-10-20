<template>
    <div class="fs_ticket_importer">
        <div class="fs_box_wrapper">
            <div class="fs_box_header">
                <div class="fs_box_head">
                    <h3>{{ $t('Tickets Migration From Other Plugins') }}</h3>
                </div>
                <div class="fs_box_actions">

                </div>
            </div>
            <div>
                <div style="background: white;" class="fs_box_body" v-if="loading">
                    <el-skeleton class="fs_box_wrapper" :rows="5" animated/>
                </div>
                <el-row style="padding: 25px 25px" v-if="!loading && settings.length" :gutter="20">
                    <el-col v-for="setting in settings" :span="24">
                        <div :class="'grid-content fs_'+setting.handler">
                            <el-card :body-style="{ padding: '4px' }" :header=setting.name style="margin: 10px 0">
                                <div style="padding: 14px">
                                    <h4 v-if="setting?.type=='sass'">Migrate tickets from <b>{{setting.name}}</b> in one click.</h4>
                                    <h4 v-else>This migrator will migrate total <b>{{setting.tickets}}</b> tickets with <b>{{setting.replies}}</b> replies and <b>{{setting.customers}}</b> customers. If you already migrate tickets then it won't migrate existing tickets.</h4>
                                    <span v-if="setting.last_migrated">Last Migration: <b>{{setting.last_migrated}}</b></span>
                                    <el-progress
                                        v-if="imporing && currently_importing == setting.handler"
                                        :text-inside="true"
                                        :stroke-width="24"
                                        :percentage="completed"
                                        status="success"
                                        style="margin: 5px 0"
                                    />
                                    <el-progress v-if="deleting && currently_importing == setting.handler"
                                                 :percentage="50" status="exception" :indeterminate="true"
                                                 style="margin: 5px 0"/>
                                    <hr/>
                                    <el-button type="success" :disabled="imporing"
                                               @click="importTickets(setting.handler)" style="margin-top: 15px;">
                                        {{ $t('Import Tickets') }}
                                    </el-button>

                                    <el-dialog v-model="import_done" title="Delete Imported Tickets">
                                        <span> Do you want to delete all imported tickets and its data? </span>
                                        <template #footer>
						      <span class="dialog-footer">
						        <el-button @click="import_done = false" type="primary">No</el-button>
						        <el-button type="danger" @click="deleteOldTicketsWithData(currently_importing)">
						        	Yes
					        	</el-button>
						      </span>
                                        </template>
                                    </el-dialog>
                                </div>
                            </el-card>
                        </div>
                    </el-col>
                </el-row>
                <div class="fs_box_body" v-if="!loading && !settings.length">
                    <h2>Import from other Support Tickets Plugins</h2>
                    <p>If you want to migrate tickets from other ticketing system like <b>Awesome Support</b> or <b>Support
                        Candy</b> WordPress plugin then you can migrate from this section.</p>
                    <p>Currently no migration is available for this site</p>
                </div>
            </div>
        </div>
    </div>
</template>

<script type="text/babel">
export default {
    name: 'TicketImporter',
    data() {
        return {
            settings: {},
            imporing: false,
            import_page: 1,
            total_tickets: 0,
            completedPercentage: 0,
            completed: 0,
            loading: false,
            currently_importing: '',
            import_done: false,
            deleting: false,
            delete_page: 1
        }
    },

    methods: {
        fetchSettings() {
            this.loading = true;
            this.$get('ticket_importer').then((response) => {
                this.settings = response.stats;
            })
                .catch((e) => {
                    this.$handleError(e);
                })
                .always(() => {
                    this.loading = false;
                });
        },
        importTickets(handler) {
            this.imporing = true;
            this.currently_importing = handler;

            this.$post('ticket_importer/import', {
                handler: handler,
                page: this.import_page,
            })
                .then(response => {
                    if (response.has_more) {
                        this.import_page = response.next_page;
                        this.total_tickets = response.total_tickets;
                        this.completed = response.completed;
                        this.$nextTick(() => {
                            this.importTickets(handler);
                        });
                    } else {
                        this.$notify.success({
                            message: response.message,
                            position: 'bottom-right'
                        })
                        this.imporing = false;
                        this.import_done = true;
                    }
                })
                .catch((error) => {
                    this.$handleError(error);
                })
                // .always(() => {
                //     this.imporing = false;
                // });
        },
        deleteOldTicketsWithData(handler) {
            this.deleting = true;
            this.import_done = false;
            this.currently_importing = handler;

            this.$del('ticket_importer/delete', {
                page: this.delete_page,
                handler: handler
            })
                .then(response => {
                    if (response.has_more) {
                        this.delete_page = response.next_page;
                        this.$nextTick(() => {
                            this.deleteOldTicketsWithData(handler);
                        });
                    } else {
                        this.$notify.success({
                            message: response.message,
                            position: 'bottom-right'
                        })
                        this.deleting = false;
                        this.fetchSettings();
                    }
                })
                .catch(e => {
                    this.$handleError(e);
                })
                // .always(() => {
                //     this.deleting = false;
                // });
        }
    },

    mounted() {
        this.fetchSettings();
        this.$setTitle('Ticket Migrator');
    }
}
</script>

<style lang="scss" scoped>
.fs_no_active_support_system {
    padding: 20px;
}
</style>
