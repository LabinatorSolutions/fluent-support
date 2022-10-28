<template>
    <div class="fs_dash_suggested_ticket">
        <div class="fs_box fs_dashboard_box">
            <el-collapse v-model="activeNames" @change="handleChange">
                <el-collapse-item name="suggested_ticket" class="fs_box_board">
                    <template #title>
                        <div
                            class="fs_box_header"
                            style="justify-content: unset"
                        >
                            {{ $t("dashboard_sub_heading") }}
                        </div>
                    </template>

                    <div class="fs_box_body">
                            <ul
                                v-if="component_data.suggested_tickets.length"
                                class="fs_card_list"
                            >
                                <li
                                    v-for="ticket in component_data.suggested_tickets"
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

                            <p class="fs_padded_20 fs_stat_helper" v-else>
                                {{ $t("dashboard_all_catch_up") }}
                                <b>{{ $t("Good Job") }}, {{ me.full_name }}!</b>
                            </p>

                            <p
                                class="fs_padded_20 fs_stat_helper"
                                v-if="component_data.overall_stats"
                            >
                                <span class="fs_highlight"
                                    >{{
                                        component_data.overall_stats
                                            .waiting_tickets
                                    }}
                                    {{ $t("tickets") }}</span
                                >
                                {{ $t("are waiting for reply with") }}
                                <span class="fs_highlight">
                                    {{ $t("average") }}
                                    {{
                                        component_data.overall_stats
                                            .average_waiting
                                    }}
                                    {{ $t("wait time") }}</span
                                >
                                & {{ $t("max wait time") }}
                                <span class="fs_highlight">{{
                                    component_data.overall_stats.max_waiting
                                }}</span>
                            </p>
                    </div>
                </el-collapse-item>
            </el-collapse>
        </div>
    </div>
</template>
<script>
export default {
    props: ["component_data"],
    name: "SuggestedTicket",
    data() {
        return {
            me: this.appVars.me,
            activeNames: [],
            collapse_data: [],
        };
    },
    computed: {
        greetingTime() {
            const m = this.moment();
            let g = null; //return g

            if (!m || !m.isValid()) {
                return;
            } //if we can't find a valid or filled moment, we return.

            const split_afternoon = 12; //24hr time to split the afternoon
            const split_evening = 17; //24hr time to split the evening
            const currentHour = parseFloat(m.format("HH"));

            if (
                currentHour >= split_afternoon &&
                currentHour <= split_evening
            ) {
                g = this.$t("afternoon");
            } else if (currentHour >= split_evening) {
                g = this.$t("evening");
            } else {
                g = this.$t("morning");
            }

            return g;
        },
    },
    methods: {
        handleChange(val) {
        },
        getCollapseData() {
        },
    },
    mounted() {
        this.getCollapseData();
    },
};
</script>
<style scoped></style>
