<template>
    <div class="fs_dash_suggested_ticket">
        <div class="fs_box fs_dashboard_box">
            <el-collapse v-model="activeNames" @change="handleChange">
                <el-collapse-item name="suggested_ticket" class="box_board">
                    <template #title>
                        <div
                            class="fs_box_header"
                            style="justify-content: unset"
                        >
                            <!-- {{ $t("Good") }} {{ greetingTime }}
                            {{ me.full_name }}, -->
                            <span style="font-weight: normal">
                                {{ $t("dashboard_sub_heading") }}
                            </span>
                        </div>
                    </template>

                    <div class="fs_box_body">
                        <template v-if="!loading">
                            <ul
                                v-if="data.suggested_tickets"
                                class="fs_card_list"
                            >
                                <li
                                    v-for="ticket in data.suggested_tickets"
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
                                v-if="data.overall_stats"
                            >
                                <span class="fs_highlight"
                                    >{{ data.overall_stats.waiting_tickets }}
                                    {{ $t("tickets") }}</span
                                >
                                {{ $t("are waiting for reply with") }}
                                <span class="fs_highlight">
                                    {{ $t("average") }}
                                    {{ data.overall_stats.average_waiting }}
                                    {{ $t("wait time") }}</span
                                >
                                & {{ $t("max wait time") }}
                                <span class="fs_highlight">{{
                                    data.overall_stats.max_waiting
                                }}</span>
                            </p>
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
    name: "SuggestedTicket",
    emits: ["component_state"],
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
            if (val[1]) {
                this.component_collapse_state.suggestedTicket = true;
            } else {
                this.component_collapse_state.suggestedTicket = false;
            }
            this.$emit("component_state", this.component_collapse_state);
        },
        getCollapseData() {
            // this.collapse_data = this.$getData('component_state');
            this.collapse_data = this.component_collapse_state;
            if (this.collapse_data.suggestedTicket) {
                this.activeNames = ["suggested_ticket"];
            } else {
                this.activeNames = null;
            }
        },
    },
    mounted() {
        this.getCollapseData();
    },
};
</script>
<style scoped>
.fs_box_header {
    font-size: 15px;
}
</style>
