<template>
    <div class="fs_dash_mentioned_ticket" v-if="has_pro && component_data">
        <div class="fs_box fs_dashboard_box">
            <el-collapse v-model="activeNames" @change="handleChange">
                <el-collapse-item name="mentioned_ticket" class="fs_box_board">
                    <template #title>
                        <div class="fs_box_header">
                            {{ $t("dashboard_watcher_heading") }}
                        </div>
                    </template>
                    <div class="fs_box_body">
                        <ul v-if="component_data" class="fs_card_list">
                            <li
                                v-for="ticket in component_data"
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
                                                    'fs_badge_' + ticket.status
                                                "
                                            >
                                                {{ ticket.status }}
                                            </span>
                                        </div>
                                    </div>
                                </router-link>
                            </li>
                        </ul>
                    </div>
                </el-collapse-item>
            </el-collapse>
        </div>
    </div>
</template>
<script>
export default {
    props: ["component_data"],
    name: "MentionedTicket",
    data() {
        return {
            activeNames: [],
            collapse_data: [],
        };
    },
    methods: {
        handleChange(value) {
        },
        getCollapseData() {
        },
    },
    created() {
        this.getCollapseData();
    },
};
</script>
<style></style>
