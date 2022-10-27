<template>
    <!-- <h1>MentionedTticket</h1>
   {{data}} -->
    <div
        class="fs_dash_mentioned_ticket"
        v-if="has_pro && data.ticket_to_watch"
    >
        <div class="fs_box fs_dashboard_box">
            <el-collapse v-model="activeNames" @change="handleChange">
                <el-collapse-item name="mentioned_ticket" class="box_board">
                    <template #title>
                        <div class="fs_box_header">
                            <span style="font-weight: normal">{{
                                $t("dashboard_watcher_heading")
                            }}</span>
                        </div>
                    </template>
                    <div class="fs_box_body">
                        <template v-if="!loading">
                            <ul
                                v-if="data.ticket_to_watch"
                                class="fs_card_list"
                            >
                                <li
                                    v-for="ticket in data.ticket_to_watch"
                                    :key="ticket.id"
                                >
                                    <router-link
                                        tag="li"
                                        :to="{
                                            name: 'view_ticket',
                                            params: { ticket_id: ticket.id },
                                        }"
                                    >
                                        <div class="fs_suggested_ticket">
                                            <div class="fs_ticket_info">
                                                <img
                                                    class="fs_inline_photo_40"
                                                    :src="ticket.customer.photo"
                                                />
                                                <span
                                                    style="
                                                        color: #3c434a;
                                                        font-size: 15px;
                                                        font-weight: 400;
                                                    "
                                                    >{{ ticket.title }}</span
                                                >
                                            </div>
                                            <div class="fs_ticket_status">
                                                <span
                                                    style="
                                                        padding: 3px 6px;
                                                        line-height: 100%;
                                                        margin-left: 5px;
                                                        margin-top: 7px;
                                                        font-weight: 500;
                                                        font-size: 14px;
                                                    "
                                                    class="fs_badge"
                                                    :class="
                                                        'fs_badge_' +
                                                        ticket.status
                                                    "
                                                >
                                                    {{ ticket.status }}
                                                </span>
                                            </div>
                                        </div>
                                    </router-link>
                                </li>
                            </ul>
                        </template>
                        <div class="fs_padded_20" v-else>
                            <el-skeleton :rows="3" animated />
                        </div>
                    </div>
                </el-collapse-item>
            </el-collapse>
        </div>
    </div>
</template>
<script>
export default {
    props: ["data", "component_collapse_state"],
    emits: ["component_state"],
    name: "MentionedTticket",
    data() {
        return {
            activeNames: [],
            collapse_data: [],
        };
    },
    methods: {
        handleChange(val) {
            if (val[1]) {
                this.component_collapse_state.mentionedTicket = true;
            } else {
                this.component_collapse_state.mentionedTicket = false;
            }
            this.$emit("component_state", this.component_collapse_state);
        },
        getCollapseData() {
            this.collapse_data = this.component_collapse_state;
            // this.collapse_data = this.$getData('component_state');
            console.log("Hello" + this.collapse_data);
            if (this.collapse_data.mentionedTicket) {
                this.activeNames = ["mentioned_ticket"];
            } else {
                this.activeNames = null;
            }
        },
    },
    created() {
        this.getCollapseData();
    },
};
</script>
<style></style>
