<template>
    <div class="fs_box_body">
        <ul v-if="component_data.suggested_tickets.length" class="fs_card_list">
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
                                :class="'fs_badge_' + ticket.status"
                            >
                                {{ ticket.status }}
                            </span>
                        </div>
                    </div>
                </router-link>
            </li>
        </ul>

        <p class="fs_stat_helper" v-else>
            {{ $t("dashboard_all_catch_up") }}
            <b>{{ $t("Good Job") }}, {{ me.full_name }}!</b>
        </p>

        <p
            class="fs_stat_helper"
            v-if="component_data.overall_stats"
        >
            <span  style="color:#FF7C7C"
                >{{ component_data.overall_stats.waiting_tickets }}
                {{ $t("tickets") }}</span
            >
            {{ $t("are waiting for reply with") }}
            <span style="color:#88C379">
                {{ $t("average") }}
                {{ component_data.overall_stats.average_waiting }}
                {{ $t("wait time") }}</span
            >
            & {{ $t("max wait time") }}
            <span style="color:#7280FF">{{
                component_data.overall_stats.max_waiting
            }}</span>
        </p>
    </div>
</template>
<script>
export default {
    props: ["component_data"],
    name: "SuggestedTicket",
    setup() {
        return {};
    },
};
</script>
<style scoped>
    .fs_stat_helper{
        padding: 5px 30px;
    }
</style>
