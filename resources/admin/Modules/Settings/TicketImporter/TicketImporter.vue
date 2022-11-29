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

                                    <el-dialog v-model="import_done" title="Delete Imported Tickets">
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
                                    <help-scout-importer :show="openSettings" :settings="config" @import="importTickets(currently_importing)" @close="openSettings=false"/>
                                </div>
                            </el-card>
                        </div>
                    </el-col>
                </el-row>
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
export default {
    name: 'TicketImporter',
    components: {
        HelpScoutImporter
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
            sass_systems: ['helpscout'],
            import_from_sass: false
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
.fs_import_buttons {
    margin-top: 15px;
}
</style>
