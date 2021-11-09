<template>
    <div class="fs_box_wrapper">
        <div class="fs_box_header">
            <div class="fs_box_head">
                <h3>{{$t('Support Staffs')}}</h3>
            </div>
            <div class="fs_box_actions">
                <el-button @click="createStaffModal()" type="primary" icon="el-icon-plus" size="small">
                    {{$t('Add New')}}
                </el-button>
            </div>
            <div class="fs_box_actions fs_ticket_orders">
                <el-input @keyup.enter="fetchAgents" clearable @clear="fetchAgents" size="mini"
                          :placeholder="$t('Search Agents')" v-model="search">
                    <template #append>
                        <el-button @click="fetchAgents" icon="el-icon-search"></el-button>
                    </template>
                </el-input>
            </div>
        </div>
        <div v-if="!loading" class="fs_box_body">
            <el-table stripe :data="agents">
                <el-table-column :label="$t('ID')" prop="id" width="90"/>
                <el-table-column :label="$t('Name')">
                    <template #default="scope">
                        <a :href="scope.row.user_profile">{{ scope.row.full_name }}</a>
                    </template>
                </el-table-column>
                <el-table-column :label="$t('Title')">
                    <template #default="scope">
                        <span  style="font-size: 14px; color: #56c288;">{{scope.row.title}}</span>
                    </template>
                </el-table-column>
                <el-table-column :label="$t('Permissions')">
                    <template #default="scope">
                        <el-tooltip placement="top">
                            <template #content>
                                <p>Assigned Permissions</p>
                                <span style="display: block" v-for="permission in scope.row.permissions">{{ readable(permission) }}</span>
                            </template>
                            <el-button type="default" size="small">{{ scope.row.permissions.length }} permissions</el-button>
                        </el-tooltip>
                    </template>
                </el-table-column>
                <el-table-column :label="$t('Replies')" prop="replies_count" width="140"/>
                <el-table-column :label="$t('Interactions')" prop="interactions_count" width="140"/>
                <el-table-column :label="$t('Actions')" width="100">
                    <template #default="scope">
                        <el-button @click="initEdit(scope.row)" size="medium" type="text" icon="el-icon-edit" />
                        <el-button @click="deleteAgent(scope.row.id)" size="medium" type="text" icon="el-icon-delete" style="color:red;"/>
                    </template>
                </el-table-column>
            </el-table>

            <div class="fframe_pagination_wrapper">
                <pagination @fetch="fetchAgents()" :pagination="pagination"/>
            </div>
        </div>
        <div style="padding: 20px; background: white;" class="fs_box_body" v-else>
            <el-skeleton :rows="5" animated/>
        </div>

        <el-dialog
            :append-to-body=true
            :title="(editing_agent && editing_agent.id) ? $t('Edit Staff') : $t('Add New Support Staff')"
            v-model="agent_modal"
            v-if="editing_agent"
            width="60%">

            <el-form label-position="top" :data="editing_agent">
                <el-form-item :label="$t('Email')">
                    <el-input :disabled="editing_agent.id" type="email" :placeholder="$t('Agent Email')" v-model="editing_agent.email"/>
                </el-form-item>
                <el-form-item :label="$t('First Name')">
                    <el-input type="text" :placeholder="$t('First Name')" v-model="editing_agent.first_name"/>
                </el-form-item>
                <el-form-item :label="$t('Last Name')">
                    <el-input type="text" :placeholder="$t('Last Name')" v-model="editing_agent.last_name"/>
                </el-form-item>
                <el-form-item :label="$t('Title')">
                    <el-input type="text" :placeholder="$t('agent_title')" v-model="editing_agent.title"/>
                </el-form-item>

                <el-form-item :label="$t('Permissions')">
                    <el-checkbox-group class="fs_permission_groups" v-model="editing_agent.permissions">
                        <div  v-for="permissionSet in permissions" class="fs_each_permission_set">
                            <h4 style="font-size: 15px;">{{permissionSet.title}}</h4>
                            <el-checkbox v-for="(permissionLabel, permissionkey) in permissionSet.permissions" :label="permissionkey" :key="permissionkey">{{permissionLabel}}</el-checkbox>
                        </div>
                    </el-checkbox-group>
                </el-form-item>

                <div class="telegram_integration" v-if="integrationSettings.includes('telegram_settings')">
                    <h3>{{$t('Telegram Integration')}}</h3>
                    <el-form-item :label="$t('Telegram Chat ID')">
                        <el-input type="text" :placeholder="$t('Telegram Chat ID')" v-model="editing_agent.telegram_chat_id"/>
                    </el-form-item>
                </div>

                <div class="slack_integration" v-if="integrationSettings.includes('slack_settings')">
                    <h3>{{$t('Slack Integration')}}</h3>
                    <el-form-item :label="$t('Slack User ID')">
                        <el-input type="text" :placeholder="$t('Slack User ID')" v-model="editing_agent.slack_user_id"/>
                    </el-form-item>
                </div>

            </el-form>

            <template #footer>
                <span class="dialog-footer">
                  <el-button v-loading="saving" :disabled="saving" type="primary" @click="createOrUpdateAgent()">
                      <span v-if="editing_agent.id">{{$t('Update')}}</span>
                      <span v-else>{{$t('Create')}}</span>
                  </el-button>
                </span>
            </template>

        </el-dialog>

    </div>
</template>

<script type="text/babel">
import Pagination from "../../Pieces/Pagination";
export default {
    name: 'support-staffs',
    components: {
        Pagination
    },
    data() {
        return {
            agents: [],
            pagination: {
                per_page: 10,
                current_page: 1,
                total: 0
            },
            permissions: [],
            loading: false,
            editing_agent: false,
            agent_modal: false,
            saving: false,
            search:'',
            integrationSettings: []
        }
    },
    methods: {
        fetchAgents() {
            this.loading = true;
            this.$get('agents', {
                per_page: this.pagination.per_page,
                page: this.pagination.current_page,
                search: this.search
            })
                .then(response => {
                    this.agents = response.agents.data;
                    this.pagination.total = response.agents.total;
                    this.permissions = response.permissions;
                })
                .catch((errors) => {
                    this.$handleError(errors);
                })
                .always(() => {
                    this.loading = false;
                });
        },
        fetchSettings() {
            this.$get('settings/integration-settings')
                .then(response => {
                    this.integrationSettings = response;
                })
                .catch((errors) => {
                    this.$handleError(errors);
                })
                .always(() => {
                });
        },
        createStaffModal() {
            this.editing_agent = {
                email: '',
                first_name: '',
                last_name: '',
                title: '',
                permissions: []
            }
            this.agent_modal = true;
        },
        initEdit(agent) {
            this.editing_agent = agent;
            this.agent_modal = true;
        },
        createOrUpdateAgent() {
            this.saving = true;
            let method = this.$post;
            let route = 'agents';
            if (this.editing_agent.id) {
                method = this.$put;
                route = `agents/${this.editing_agent.id}`;
            }

            method(route, {
                ...this.editing_agent
            })
                .then(response => {
                    this.$notify({
                        message: response.message,
                        type: 'success',
                        position: 'bottom-right'
                    });
                    this.fetchAgents();
                    this.agent_modal = false;
                })
                .catch((errors) => {
                    this.$handleError(errors);
                })
                .always(() => {
                    this.saving = false;
                });
        },
        readable(name) {
            return name.replace('fst_', '')
                .replaceAll('_', ' ');
        },
        deleteAgent(agentId) {
            this.$prompt( this.$t('Please Provide Fallback Agent ID'), this.$t('Fallback Agent ID'), {
                confirmButtonText: this.$t('Confirm Delete'),
                cancelButtonText: this.$t('Cancel'),
                inputErrorMessage: this.$t('Invalid ID')
            }).then(({ value }) => {
                this.confirmDeleteAgent(agentId, value);
            }).catch(() => {

            });
        },
        confirmDeleteAgent(agentId, fallbackId) {
            this.$del(`agents/${agentId}`, {
                fallback_agent_id: fallbackId
            })
            .then(response => {
                this.$notify({
                    message: response.message,
                    type: 'success',
                    position: 'bottom-right'
                });
                this.fetchAgents();
            })
            .catch((errors) => {
                this.$handleError(errors);
            });
        }
    },
    mounted() {
        this.fetchAgents();
        this.fetchSettings();
        this.$setTitle('Support Staffs');
    }
}
</script>
