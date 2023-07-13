<template>
    <div class="fs_box_body" v-if="has_pro">
        <ul v-if="component_data.length" class="fs_card_list">
            <li v-for="ticket in component_data" :key="ticket.id">
                <router-link
                class="fs_mentioned_ticket_list"
                    tag="li"
                    :to="{
                        name: 'view_ticket',
                        params: { ticket_id: ticket.id },
                    }"
                >
                    <div class="fs_suggested_ticket">
                        <div class="fs_ticket_info">
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
        <div v-else class="fs_no_book_tk">
            <span>
                {{ translate('no_bookmarked_ticket') }}
            </span>
        </div>
    </div>
</template>
<script>
import { useFluentHelper } from "@/admin/Composable/FluentFrameworkHelper";
export default {
    props: ["component_data"],
    name: "MentionedTicket",
    setup() {

        const {
            has_pro,
            translate,

        } = useFluentHelper();
        return{
            has_pro,
            translate
        };
    },
};
</script>
<style scoped>
    .fs_no_book_tk span{
        font-size: 15px;
        color: #3c434a;
        font-weight: 400;
        padding: 0 15px;
        display: block;
        margin-top: 20px;
    }
    .fs_mentioned_ticket_list{
        text-decoration: none;
    }
</style>
