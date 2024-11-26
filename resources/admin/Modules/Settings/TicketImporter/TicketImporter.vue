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

                    <el-col :span="24">
                        <el-card :body-style="{ padding: '0px' }" style="margin: 10px 0" v-if="!has_pro">
                            <div class="fs_narrow_promo" style="background: white;">
                                <h3>{{$t('import_from_sass')}}</h3>
                                <p>{{$t('pro_promo')}}</p>
                                <a target="_blank" rel="noopener" href="https://fluentsupport.com" class="el-button el-button--success">{{$t('Upgrade To Pro')}}</a>
                            </div>
                        </el-card>
                    </el-col>

                    <el-col v-for="setting in settings" :span="24">
                        <div :class="'grid-content fs_'+setting.handler">
                            <el-card :body-style="{ padding: '4px' }" :header=setting.name style="margin: 10px 0">
                                <div style="padding: 14px">
                                    <h4 v-if="setting?.type=='sass'">{{$t('Migrate tickets from')}} <b>{{setting.name}}</b> {{$t('in one click.')}}</h4>
                                    <h4 v-else>{{$t('This migrator will migrate total')}} <b>{{setting.tickets}}</b> {{$t('tickets with')}} <b>{{setting.replies}}</b> {{$t('replies and')}} <b>{{setting.customers}}</b> {{$t("customers. If you already migrate tickets then it won't migrate existing tickets.")}}</h4>
                                    <span v-if="setting.last_migrated">{{$t('Last Migration:')}} <b>{{setting.last_migrated}}</b></span>
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
                                    <div class="fs_import_buttons">
                                        <el-button v-if="setting.type=='sass'" type="success"
                                                   @click="(openSettings=true)&&(currently_importing=setting.handler)" :disabled="imporing">
                                            {{ $t('Import Tickets') }}
                                        </el-button>
                                        <el-button v-else type="success" :disabled="imporing"
                                                   @click="importTickets(setting.handler)">
                                            {{ $t('Import Tickets') }}
                                        </el-button>
                                    </div>

                                    <el-dialog v-if=!had_tickets v-model="import_done" title="Delete Imported Tickets" class="fs_dialog">
                                        <span> {{$t('Do you want to delete all imported tickets and its data?')}} </span>
                                        <template #footer>
                                          <span class="dialog-footer">
                                            <el-button @click="import_done = false" type="primary">{{$t('No')}}</el-button>
                                            <el-button type="danger" @click="deleteOldTicketsWithData(currently_importing)">
                                                {{$t('Yes')}}
                                            </el-button>
                                          </span>
                                        </template>
                                    </el-dialog>

                                </div>
                            </el-card>
                        </div>
                    </el-col>
                </el-row>

                <div class="fs_importer_modal">
                    <help-scout-importer v-if="currently_importing=='helpscout'" :show="openSettings" :settings="config" :previously_imported="previous_migration_data.helpscout.previously_imported" @restart_previous_migration="restartTicketMigration('helpscout')" @import="importTickets(currently_importing)" @close="openSettings=false"/>
                    <fresh-desk-importer v-if="currently_importing=='freshdesk'" :show="openSettings" :settings="config" @import="importTickets(currently_importing)" @close="openSettings=false"/>
                    <zendesk-importer v-if="currently_importing=='zendesk'" :show="openSettings" :settings="config" @import="importTickets(currently_importing)" @close="openSettings=false"/>
                </div>

                <div class="fs_box_body" v-if="!loading && !settings.length">
                    <h2>{{$t('Import from other Support Tickets Plugins')}}</h2>
                    <p>{{$t('If you want to migrate tickets from other ticketing system like')}} <b>{{$t('Awesome Support')}}</b> {{$t('or')}} <b>{{$t('Support Candy')}}</b> {{$t('WordPress plugin then you can migrate from this section.')}}</p>
                    <p>{{$t('Currently no migration is available for this site')}}</p>
                </div>
            </div>
        </div>
    </div>
</template>

<script type="text/babel">
import HelpScoutImporter from './HelpScout/HelpScoutImporter.vue';
import FreshDeskImporter from './FreshDesk/FreshDeskImporter.vue';
import ZendeskImporter from './Zendesk/ZendeskImporter';
export default {
    name: 'TicketImporter',
    components: {
        HelpScoutImporter,
        FreshDeskImporter,
        ZendeskImporter
    },
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
            delete_page: 1,
            openSettings: false,
            config:{},
            sass_systems: ['helpscout', 'freshdesk','zendesk'],
            import_from_sass: false,
            had_tickets: true,
            previous_migration_data: []
        }
    },

    methods: {
        fetchSettings() {
            this.loading = true;
            this.$get('ticket_importer').then((response) => {
                this.settings = response.stats;
                this.settings.forEach((setting) => {
                    const handler = setting.handler;
                    this.previous_migration_data[handler] = setting;
                });

            })
                .catch((e) => {
                    this.$handleError(e);
                })
                .always(() => {
                    this.loading = false;
                });
        },
        restartTicketMigration(handler) {
            this.import_page = this.previous_migration_data[handler].previously_imported.next_page;
            this.importTickets(handler);
        },
        importTickets(handler) {
            this.imporing = true;

            if (this.openSettings) {
                this.openSettings = false;
            }

            this.currently_importing = handler;
            let query = {
                handler: handler,
                page: this.import_page
            };

            if (this.config){
                query.query = {
                    access_token: this.config.access_token,
                    mailbox: this.config.mailbox_id,
                };
            }

            if(this.config.domain){
                query.query.domain = this.config.domain;
            }

            if(this.config.email){
                query.query.email = this.config.email;
            }

            this.$post('ticket_importer/import', query)
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

                        if(response.had_tickets){
                            this.had_tickets = response.had_tickets == 'yes' ? false : true;
                        }

                        if(!this.sass_systems.includes(handler)){
                            this.import_done = true;
                        } else{
                            this.config = {};
                            this.import_from_sass = true;
                        }

                        this.fetchSettings();
                    }
                })
                .catch((error) => {
                    this.$handleError(error);
                })
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
.fs_import_buttons {
    margin-top: 15px;
}
</style>
