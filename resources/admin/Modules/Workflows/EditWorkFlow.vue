<template>
    <div class="fs_all_workflows">
        <div class="fs_box_header">
            <div class="fs_box_head">
                <el-breadcrumb separator="/">
                    <el-breadcrumb-item :to="{ name: 'workflows' }">{{
                            $t("Workflows")
                        }}</el-breadcrumb-item>
                    <template v-if="workflow">
                        <el-breadcrumb-item>
                            <template v-if="!editingTitle">
                                {{ workflow.title }}
                                <el-button
                                    link
                                    type="primary"
                                    class="fs_inline_edit_btn"
                                    :disabled="saving"
                                    @click="startEditTitle"
                                >
                                    <svg class="fs_icon_secondary" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 11 12" fill="none">
                                        <path d="M2.0484 8.38247L8.1336 2.29727L7.2852 1.44887L1.2 7.53407V8.38247H2.0484ZM2.5458 9.58247H0V7.03667L6.861 0.175672C6.97352 0.0631893 7.1261 0 7.2852 0C7.4443 0 7.59688 0.0631893 7.7094 0.175672L9.4068 1.87307C9.51928 1.98559 9.58247 2.13817 9.58247 2.29727C9.58247 2.45637 9.51928 2.60896 9.4068 2.72147L2.5458 9.58247ZM0 10.7825H10.8V11.9825H0V10.7825Z" fill="currentColor"/>
                                    </svg>
                                </el-button>
                            </template>
                            <template v-else>
                                <el-input
                                    ref="titleInputRef"
                                    v-model="workflow.title"
                                    size="small"
                                    class="fs_inline_title_input fs_text_input"
                                    @blur="finishEditTitle"
                                    @keyup.enter="finishEditTitle"
                                />
                            </template>
                        </el-breadcrumb-item>
                    </template>
                </el-breadcrumb>
            </div>
            <div v-if="workflow" class="fs_box_actions fs-gap-16">
                <div class="fs_workflow_status">
                    <el-switch
                        :disabled="saving"
                        @change="updateWorkFlow()"
                        active-value="published"
                        inactive-value="draft"
                        v-model="workflow.status"
                    />
                    <div class="fs_workflow_status_text">
                        <!-- translators: %s is a status label (a ticket status, or Published/Draft for a workflow) -->
                        {{ $t('Status: %s', workflowStatusLabel) }}
                    </div>
                </div>
                <span v-if="isDirty" class="fs_wf_unsaved">{{ $t("Unsaved changes") }}</span>
                <el-button
                    :disabled="saving || !isDirty"
                    v-loading="saving"
                    class="fs_filled_btn"
                    @click="updateWorkFlow()"
                >{{ $t("Save Workflow") }}
                </el-button>
            </div>
        </div>
        <div class="fs_box_wrapper">
            <div
                v-if="workflow"
                v-loading="saving"
                class="fs_workflow_edit_wrap fs_wf_editor"
            >
                <workflow-flow
                    :workflow="workflow"
                    :conditions="workflow_conditions"
                    :trigger_fields="trigger_fields"
                    :actions="actions"
                    :actionFields="filtered_action_fields"
                    :selected="drawer.open ? drawer.target : ''"
                    @edit="openEditor"
                    @insert="openPicker"
                />

                <div class="fs_box fs_workflow_runs_wrap">
                    <div class="fs_box_edit_header">
                        <div class="fs_box_head">
                            <h3>{{ $t("Recent Runs") }}</h3>
                        </div>
                        <el-button
                            class="fs_outline_btn"
                            icon="Refresh"
                            :aria-label="$t('Refresh')"
                            :disabled="loadingRuns"
                            @click="fetchRuns()"
                        />
                    </div>
                    <div class="fs_box_body fs_padded_20" v-loading="loadingRuns">
                        <el-skeleton v-if="runs === null" :rows="3" animated />
                        <p v-else-if="!runs.length" class="fs_workflow_runs_empty">
                            {{ $t("No runs yet. Runs are kept as long as the activity log keeps its entries.") }}
                        </p>
                        <div v-else class="fs_activity_timeline">
                            <div
                                v-for="run in runs"
                                :key="run.id"
                                class="fs_timeline_item"
                                @click.prevent="openRunLink($event)"
                            >
                                <div class="fs_timeline_icon">
                                    <div class="fs_activity_icon fs_icon_workflow" :class="{ fs_icon_failed: run.failed }">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                                        </svg>
                                    </div>
                                </div>
                                <div class="fs_timeline_content">
                                    <div class="fs_activity_description" v-html="sanitize(run.description)"></div>
                                    <div class="fs_activity_time">{{ dateTimeFormat(run.created_at, 'MMM D, YYYY, h:mm A') }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- First load: cards shaped like the trigger, conditions and action steps. -->
            <div
                v-else
                class="fs_workflow_edit_wrap fs_wf_editor"
                aria-busy="true"
            >
                <div class="fs_wf_flow">
                    <template v-for="n in 3" :key="n">
                        <div v-if="n > 1" class="fs_wf_connector"></div>
                        <el-skeleton animated :loading="true" class="fs_wf_step fs_wf_step_static">
                            <template #template>
                                <el-skeleton-item variant="text" style="display: block; width: 60px;" />
                                <el-skeleton-item variant="text" style="display: block; width: 220px; height: 18px; margin-top: 8px;" />
                                <el-skeleton-item variant="text" style="display: block; width: 70%; margin-top: 8px;" />
                            </template>
                        </el-skeleton>
                    </template>
                </div>
            </div>
        </div>

        <el-drawer
            v-if="workflow"
            v-model="drawer.open"
            :size="drawerSize"
            :title="drawerTitle"
            :append-to-body="true"
            :destroy-on-close="true"
            class="fs_wf_drawer"
        >
            <template v-if="drawer.mode === 'trigger'">
                <div class="fs_wf_choice_list">
                    <div
                        v-for="(trigger, triggerKey) in trigger_fields.triggers"
                        :key="triggerKey"
                        class="fs_wf_choice"
                        :class="{ fs_wf_choice_active: workflow.trigger_key === triggerKey }"
                        role="button"
                        tabindex="0"
                        @click="changeTrigger(triggerKey)"
                        @keydown.enter.prevent="changeTrigger(triggerKey)"
                    >
                        <div class="fs_wf_choice_title">{{ trigger.title }}</div>
                        <div v-if="trigger.description" class="fs_wf_choice_desc">{{ trigger.description }}</div>
                    </div>
                </div>
            </template>
            <template v-else-if="drawer.mode === 'conditions'">
                <el-form label-position="top" :data="workflow">
                    <trigger-mappers
                        :workflow_conditions="workflow_conditions"
                        :workflow="workflow"
                        :trigger_fields="trigger_fields"
                    />
                </el-form>
            </template>
            <template v-else-if="drawer.mode === 'pick'">
                <el-input
                    v-model="actionSearch"
                    :placeholder="$t('Search actions')"
                    prefix-icon="Search"
                    clearable
                    class="fs_text_input fs_wf_action_search"
                />
                <div class="fs_wf_choice_list">
                    <div
                        v-for="item in pickableActions"
                        :key="item.name"
                        class="fs_wf_choice"
                        role="button"
                        tabindex="0"
                        @click="insertAction(item.name)"
                        @keydown.enter.prevent="insertAction(item.name)"
                    >
                        <div class="fs_wf_choice_title">{{ item.title }}</div>
                    </div>
                    <p v-if="!pickableActions.length" class="fs_wf_help_text">{{ $t("No action matches your search.") }}</p>
                </div>
            </template>
            <template v-else-if="drawer.mode === 'action' && editingAction">
                <action-map
                    :key="editingAction._key"
                    :action="editingAction"
                    :actions="filtered_action_fields"
                />
            </template>
            <template v-if="drawer.mode !== 'pick'" #footer>
                <div class="fs_wf_drawer_footer">
                    <el-button
                        v-if="drawer.mode === 'action'"
                        class="fs_stroke_btn"
                        icon="Delete"
                        @click="removeAction(drawer.target)"
                    >{{ $t("Delete Action") }}</el-button>
                    <el-button class="fs_filled_btn" @click="drawer.open = false">{{ $t("Done") }}</el-button>
                </div>
            </template>
        </el-drawer>
    </div>
</template>

<script type="text/babel">
import WorkflowFlow from "./_WorkflowFlow";
import ActionMap from "./_ActionMap";
import TriggerMappers from "./_TiggerMappers";
import DOMPurify from 'dompurify';
import { applyPurifyGuards } from '@/common/domPurifyGuards';

applyPurifyGuards(DOMPurify);

// Actions that make no sense after these triggers.
const EXCLUDED_ACTIONS = {
    "fluent_support/ticket_closed": [
        "fs_action_create_response",
        "fs_action_close_ticket",
        "fs_action_assign_agent",
        "fs_block_customer",
        "fs_delete_ticket",
    ],
    "fluent_support/ticket_created": [
        "fs_action_remove_bookmarks",
        "fs_action_remove_tags",
    ],
};

let keySeed = 0;

export default {
    name: "EditWorkFlow",
    components: {
        WorkflowFlow,
        ActionMap,
        TriggerMappers
    },
    props: ["workflow_id"],

    data() {
        return {
            workflow: false,
            workflow_conditions: [],
            actions: [],
            action_fields: {},
            trigger_fields: {},
            saving: false,
            savedState: '',
            editingTitle: false,
            runs: null,
            loadingRuns: false,
            // target: 'trigger', 'conditions' or an action's _key
            drawer: { open: false, mode: '', target: '', insertAt: 0 },
            actionSearch: '',
            isMobile: window.innerWidth < 768,
        };
    },

    computed: {
        workflowStatusLabel() {
            const status = this.workflow ? this.workflow.status : "";
            const labels = {
                published: this.$t('Published'),
                draft: this.$t('Draft')
            };
            return labels[status] || (status ? status.charAt(0).toUpperCase() + status.slice(1) : '');
        },
        filtered_action_fields() {
            const exclude = EXCLUDED_ACTIONS[this.workflow && this.workflow.trigger_key] || [];
            const filtered = {};
            Object.keys(this.action_fields).forEach(name => {
                if (!exclude.includes(name)) {
                    filtered[name] = this.action_fields[name];
                }
            });
            return filtered;
        },
        pickableActions() {
            const search = this.actionSearch.trim().toLowerCase();
            return Object.keys(this.filtered_action_fields)
                .map(name => ({ name, title: this.filtered_action_fields[name].title }))
                .filter(item => !search || String(item.title).toLowerCase().includes(search));
        },
        editingAction() {
            return this.actions.find(action => action._key === this.drawer.target) || null;
        },
        drawerTitle() {
            const titles = {
                trigger: this.$t('When should this workflow run?'),
                conditions: this.$t('Only run if'),
                pick: this.$t('Add an action'),
                action: this.$t('Edit action'),
            };
            return titles[this.drawer.mode] || '';
        },
        drawerSize() {
            return this.isMobile ? '100%' : '560px';
        },
        isDirty() {
            return !!this.workflow && this.serializeState() !== this.savedState;
        }
    },
    created() {
        if (this.has_pro) {
            this.fetch();
            this.fetchRuns();
        }
        window.addEventListener('beforeunload', this.warnUnsaved);
        window.addEventListener('resize', this.onResize);
    },
    beforeUnmount() {
        window.removeEventListener('beforeunload', this.warnUnsaved);
        window.removeEventListener('resize', this.onResize);
    },
    beforeRouteLeave() {
        if (!this.isDirty) {
            return true;
        }
        return this.$confirm({
            message: this.$t('You have unsaved changes. Leave this page anyway?'),
            options: {
                confirmButtonText: this.$t('Leave'),
                cancelButtonText: this.$t('Stay'),
                confirmButtonClass: 'fs_filled_btn',
                cancelButtonClass: 'fs_outline_btn',
                type: 'warning'
            }
        }).then(() => true).catch(() => false);
    },

    methods: {
        onResize() {
            this.isMobile = window.innerWidth < 768;
        },
        warnUnsaved(event) {
            if (this.isDirty) {
                event.preventDefault();
                event.returnValue = '';
            }
        },
        // What a save sends, as a string to compare against the last save.
        serializeState() {
            return JSON.stringify({
                title: this.workflow.title,
                trigger_key: this.workflow.trigger_key,
                conditions: this.workflow_conditions,
                actions: this.actions.map(({ id, title, action_name, settings }) => ({ id, title, action_name, settings })),
            });
        },
        // Loads the server's workflow and actions; a local _key tracks actions without an id.
        applyResponse(response) {
            if (response.workflow.trigger_type === "automatic") {
                if (!response.workflow.settings) {
                    response.workflow.settings = {};
                }
                this.workflow_conditions = response.workflow.settings.conditions || [];
            }
            this.workflow = response.workflow;
            this.actions = (response.actions || []).map(action => ({
                ...action,
                // PHP sends empty settings as [].
                settings: action.settings && !Array.isArray(action.settings) ? action.settings : {},
                _key: 'action_' + (++keySeed),
            }));
            this.savedState = this.serializeState();
        },
        // The flow is masked while saving; this also stops keyboard Enter on a card.
        openEditor(target) {
            if (this.saving) {
                return;
            }
            const mode = (target === 'trigger' || target === 'conditions') ? target : 'action';
            this.drawer = { open: true, mode, target, insertAt: 0 };
        },
        openPicker(index) {
            if (this.saving) {
                return;
            }
            this.actionSearch = '';
            this.drawer = { open: true, mode: 'pick', target: '', insertAt: index };
        },
        insertAction(actionName) {
            const definition = this.filtered_action_fields[actionName];
            const defaults = definition.settings_defaults;
            const action = {
                title: definition.title,
                action_name: actionName,
                settings: defaults && !Array.isArray(defaults) ? JSON.parse(JSON.stringify(defaults)) : {},
                _key: 'action_' + (++keySeed),
            };
            this.actions.splice(this.drawer.insertAt, 0, action);
            this.openEditor(action._key);
        },
        removeAction(key) {
            this.$confirm({
                message: this.$t('delete_action_warning'),
                options: {
                    confirmButtonText: this.$t('Yes, Delete this Action'),
                    cancelButtonText: this.$t('Cancel'),
                    confirmButtonClass: 'fs_filled_btn',
                    cancelButtonClass: 'fs_outline_btn',
                    type: 'warning'
                }
            }).then(() => {
                this.actions = this.actions.filter(action => action._key !== key);
                this.drawer.open = false;
            }).catch(() => {});
        },
        // Condition rows the new trigger does not support are dropped.
        changeTrigger(triggerKey) {
            this.workflow.trigger_key = triggerKey;
            const supported = this.trigger_fields.triggers[triggerKey].supported_conditions || [];
            this.workflow_conditions.forEach((group, index) => {
                this.workflow_conditions[index] = group.filter(row => !row.data_key || supported.includes(row.data_key));
            });
            this.drawer.open = false;
        },
        fetchRuns() {
            this.loadingRuns = true;
            this.$get("workflows/" + this.workflow_id + "/runs")
                .then((response) => {
                    this.runs = response.runs || [];
                })
                .catch((errors) => {
                    this.$handleError(errors);
                })
                .always(() => {
                    this.loadingRuns = false;
                });
        },
        sanitize(html) {
            return html ? DOMPurify.sanitize(html) : '';
        },
        // A run links its ticket (data-ticket-id) and this workflow (already open).
        openRunLink($event) {
            const link = $event.target.closest && $event.target.closest('[data-ticket-id]');
            const ticketId = link ? link.dataset.ticketId : null;
            if (ticketId) {
                this.$router.push({ name: "view_ticket", params: { ticket_id: ticketId } });
            }
        },
        startEditTitle() {
            this.editingTitle = true;
            this.$nextTick(() => {
                if (this.$refs.titleInputRef && this.$refs.titleInputRef.focus) {
                    this.$refs.titleInputRef.focus();
                }
            });
        },
        finishEditTitle() {
            this.editingTitle = false;
        },
        fetch() {
            this.$get("workflows/" + this.workflow_id, {
                with: ["action_fields", "trigger_fields"],
            })
                .then((response) => {
                    this.trigger_fields = response.trigger_fields;
                    this.action_fields = response.action_fields;
                    this.applyResponse(response);
                })
                .catch((errors) => {
                    this.$handleError(errors);
                });
        },

        updateWorkFlow() {
            this.saving = true;
            const workFlow = JSON.parse(JSON.stringify(this.workflow));

            if (workFlow.trigger_type === "automatic") {
                if (!workFlow.settings) {
                    workFlow.settings = {};
                }
                workFlow.settings.conditions = this.workflow_conditions || [];
            }

            const sent = this.actions.map(({ _key, ...action }) => action);

            this.$post("workflows/" + this.workflow_id, {
                actions: sent,
                workflow: workFlow,
            })
                .then((response) => {
                    // Edits stay blocked until the order is settled too.
                    this.keepActionOrder(sent, response.actions || [], () => {
                        this.$notify({
                            type: "success",
                            message: response.message,
                            position: "bottom-right",
                        });
                        this.applyResponse(response);
                        if ("published" === this.workflow.status) {
                            this.renewOptions("workflows/options");
                        }
                        this.saving = false;
                    });
                })
                .catch((errors) => {
                    this.$handleError(errors);
                    this.saving = false;
                });
        },

        /**
         * Pro 2.4.7 and older save actions without their order. New actions get
         * ids in the order sent, so rebuild the intended order and save it with
         * the older action-sequence endpoint. Current Pro returns it in order already.
         * On success `saved` is put in the intended order; on failure it keeps the
         * order the server has, so the editor shows what will really run.
         */
        keepActionOrder(sent, saved, done) {
            const savedIds = saved.map(action => Number(action.id));
            const known = sent.filter(action => action.id).map(action => Number(action.id));
            const created = savedIds.filter(id => !known.includes(id)).sort((a, b) => a - b);
            const wanted = sent.map(action => action.id ? Number(action.id) : created.shift()).filter(id => savedIds.includes(id));

            if (wanted.join() === savedIds.join()) {
                done();
                return;
            }

            this.$post("workflows/action-sequence", {
                workflow_id: this.workflow_id,
                action_sequence: wanted,
            })
                .then(() => {
                    saved.sort((a, b) => wanted.indexOf(Number(a.id)) - wanted.indexOf(Number(b.id)));
                })
                .catch((errors) => {
                    this.$handleError(errors);
                })
                .always(done);
        },
    },
};
</script>
