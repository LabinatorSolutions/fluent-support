<template>
    <div class="dashboard fs_box_wrapper" v-if="!loading">
        <el-row :gutter="20">
            <el-col :span="12">
                <draggable
                    v-model="dashboard_param.first_column"
                    ghost-class="ghost"
                    group="dashboard_component"
                    item-key="id"
                    handle=".fs_component_header"
                >
                    <template v-if="!loading" #item="{ element }">
                        <div v-if="element.show" class="draggable">
                            <div class="draggable_component">
                                <el-collapse
                                    v-model="
                                        active_names[element.active_names]
                                    "
                                    @change="handleChange"
                                >
                                    <el-collapse-item
                                        :name="element.active_names"
                                        class="fs_box_board"
                                    >
                                        <template #title>
                                            <div
                                                class="fs_component_header fs_label_medium fs_dashboard_component_header"
                                            >
                                                {{ element.heading }}
                                            </div>
                                        </template>
                                        <component
                                            :is="element.component"
                                            :component_data="
                                                total_data[
                                                    element.component
                                                ]
                                            "
                                            @navigate-to-ticket="navigateToTicket"
                                        >
                                        </component>
                                    </el-collapse-item>
                                </el-collapse>
                            </div>
                        </div>
                    </template>
                </draggable>
            </el-col>
            <el-col :span="12">
                <draggable
                    v-model="dashboard_param.second_column"
                    ghost-class="ghost"
                    group="dashboard_component"
                    item-key="id"
                    handle=".fs_component_header"
                >
                    <template #item="{ element }">
                        <div v-if="element.show" class="draggable">
                            <div class="draggable_component">
                                <el-collapse
                                    v-model="
                                        active_names[element.active_names]
                                    "
                                    @change="handleChange"
                                >
                                    <el-collapse-item
                                        :name="element.active_names"
                                        class="fs_box_board"
                                    >
                                        <template #title>
                                            <div
                                                class="fs_component_header"
                                            >
                                                {{ element.heading }}
                                            </div>
                                        </template>
                                        <component
                                            :is="element.component"
                                            :component_data="
                                                total_data[
                                                    element.component
                                                ]
                                            "
                                            @navigate-to-ticket="navigateToTicket"
                                        >
                                        </component>
                                    </el-collapse-item>
                                </el-collapse>
                            </div>
                        </div>
                    </template>
                </draggable>
            </el-col>
        </el-row>
    </div>

    <div class="dashboard fs_box_wrapper" v-else aria-busy="true">
        <el-row :gutter="20">
            <el-col v-for="column in ['first_column', 'second_column']" :key="column" :span="12">
                <div
                    v-for="element in skeletonWidgets(column)"
                    :key="element.id"
                    class="fs_component_skeleton"
                    style="margin-bottom: 20px;"
                >
                    <el-skeleton animated>
                        <template #template>
                            <el-skeleton-item variant="text" style="width: 40%; height: 18px;" />
                            <div v-for="n in 3" :key="n" style="margin-top: 20px;">
                                <el-skeleton-item variant="text" style="width: 75%;" />
                                <div style="display: flex; gap: 8px; margin-top: 10px;">
                                    <el-skeleton-item variant="button" style="width: 64px; height: 22px;" />
                                    <el-skeleton-item variant="button" style="width: 64px; height: 22px;" />
                                    <el-skeleton-item variant="text" style="width: 80px;" />
                                </div>
                            </div>
                        </template>
                    </el-skeleton>
                </div>
            </el-col>
        </el-row>
    </div>
</template>

<script type="text/babel">
import draggable from "vuedraggable";
import SuggestedTicket from "./SuggestedTicket.vue";
import MentionedTicket from "./MentionedTicket.vue";
import TicketsByProduct from "./TicketsByProduct";
import AgentPerformance from './AgentPerformance.vue';
import IconPack from "../../Components/IconPack.vue";
export default {
    name: "DashboardDraggableContent",
    components: {
        draggable,
        SuggestedTicket,
        MentionedTicket,
        TicketsByProduct,
        AgentPerformance,
        IconPack
    },

    props: {
        loading: {
            type: Boolean,
            required: true
        },
        dashboard_notice: {
            type: String,
            default: ""
        },
        dashboard_param: {
            type: Object,
            required: true
        },
        active_names: {
            type: Object,
            required: true
        },
        total_data: {
            type: Object,
            required: true
        }
    },

    emits: ['change'],

    methods: {
        skeletonWidgets(column) {
            // One placeholder card per visible widget; fall back to one card so the column is never empty.
            const items = (this.dashboard_param[column] || []).filter(item => item.show);
            return items.length ? items : [{ id: column }];
        },
        handleChange() {
            this.$emit('change');
        },
        navigateToTicket(ticket) {
            this.$router.push({
                name: "view_ticket",
                params: { ticket_id: ticket.id },
            });
        }
    }
};
</script>

