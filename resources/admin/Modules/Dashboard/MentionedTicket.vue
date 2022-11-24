<template>
    <div class="fs_box_body">
        <ul v-if="component_data.length" class="fs_card_list">
            <li v-for="ticket in component_data" :key="ticket.id">
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
        <div v-else class="fs_no_book_tk">
            <span>
                {{ $t('no_bookmarked_ticket') }}
            </span>
        </div>
    </div>
</template>
<script>
export default {
    props: ["component_data"],
    name: "MentionedTicket",
    data() {
        return {};
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
    }
</style>
