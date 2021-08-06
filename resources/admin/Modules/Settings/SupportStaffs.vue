<template>
    <div class="fs_box_wrapper">
        <div class="fs_box_header">
            <div class="fs_box_head">
                <h3>Support Staffs</h3>
            </div>
            <div class="fs_box_actions">
                <el-button @click="createStaffModal()" type="primary" icon="el-icon-plus" size="small">
                    Add New
                </el-button>
            </div>
        </div>
        <div v-if="!loading" class="fs_box_body">
            <el-table stripe :data="agents">
                <el-table-column label="ID" prop="id" width="90"/>
                <el-table-column label="Name" width="160">
                    <template #default="scope">
                        <a :href="scope.row.user_profile">{{ scope.row.full_name }}</a>
                    </template>
                </el-table-column>
                <el-table-column label="Permissions">
                    <template #default="scope">
                        <span class="fs_badge"
                              v-for="permission in scope.row.permissions">{{ readable(permission) }}</span>
                    </template>
                </el-table-column>
                <el-table-column label="Replies" prop="replies_count" width="140"/>
                <el-table-column label="Interactions" prop="interactions_count" width="140"/>
                <el-table-column label="Title">
                    <template #default="scope">
                        <span  style="font-size: 14px; color: #56c288;">{{scope.row.title}}</span>
                    </template>
                </el-table-column>
                <el-table-column label="Actions" width="100">
                    <template #default="scope">
                        <el-button @click="initEdit(scope.row)" size="mini" type="primary" icon="el-icon-edit" />
                        <el-button @click="deleteAgent(scope.row.id)" size="mini" type="danger" icon="el-icon-delete" />
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
            :append-to-body="true"
            :title="(editing_agent && editing_agent.id) ? 'Edit Staff' : 'Add new Support Staff'"
            v-model="agent_modal"
            v-if="editing_agent"
            width="60%">

            <el-form label-position="top" :data="editing_agent">
                <el-form-item label="Email">
                    <el-input :disabled="editing_agent.id" type="email" placeholder="Agent Email" v-model="editing_agent.email"/>
                </el-form-item>
                <el-form-item label="First Name">
                    <el-input type="text" placeholder="First Name" v-model="editing_agent.first_name"/>
                </el-form-item>
                <el-form-item label="Last Name">
                    <el-input type="text" placeholder="Last Name" v-model="editing_agent.last_name"/>
                </el-form-item>
                <el-form-item label="Title">
                    <el-input type="text" placeholder="Agent Job Title( ex: Developer, Support Staff )" v-model="editing_agent.title"/>
                </el-form-item>

                <el-form-item label="Permissions">
                    <el-checkbox-group v-model="editing_agent.permissions">
                        <el-checkbox v-for="permission in permissions" :label="permission" :key="permission">{{readable(permission)}}</el-checkbox>
                    </el-checkbox-group>
                </el-form-item>

                <h3>Telegram Integration</h3>
                <el-form-item label="Telegram Chat ID">
                    <el-input type="text" placeholder="Telegram Chat ID" v-model="editing_agent.telegram_chat_id"/>
                </el-form-item>

            </el-form>

            <template #footer>
                <span class="dialog-footer">
                  <el-button v-loading="saving" :disabled="saving" type="primary" @click="createOrUpdateAgent()">
                      <span v-if="editing_agent.id">Update</span>
                      <span v-else>Create</span>
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
            saving: false
        }
    },
    methods: {
        fetchAgents() {
            this.loading = true;
            this.$get('agents', {
                per_page: this.pagination.per_page,
                page: this.pagination.current_page
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
            this.$prompt('Please provide Fallback Agent ID', 'Fallback Agent ID', {
                confirmButtonText: 'Confirm Delete',
                cancelButtonText: 'Cancel',
                inputErrorMessage: 'Invalid ID'
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
        this.$setTitle('Support Staffs');
    }
}
</script>
