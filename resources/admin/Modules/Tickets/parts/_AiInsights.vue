<template>
    <div class="fs_intelligence" v-loading="ResponseLoader" v-if="!ResponseLoader">
        <div class="fs_intelligence_card__result" v-if="ticketSummary">
            <div class="fs_generated_summary">
                <div class="fs_ai_response_actions">
                    <div class="fs_ai_regenerate">
                        <el-button class="fs_outline_btn" @click="run('summary')">
                            <DynamicIcon name="Refresh"/>
                            {{ $t('Regenerate') }}
                        </el-button>
                    </div>
                    <div class="fs_ai_response_close">
                        <el-button class="fs_ai_response_close_button" @click="close()">
                            <DynamicIcon name="CloseIcon"/>
                        </el-button>
                    </div>
                </div>
                <div>
                    <strong>{{ $t('Ticket Summary') }}:</strong>
                    <p>{{ ticketSummary}}</p>
                </div>
            </div>

        </div>
        <div class="fs_intelligence_card__result" v-if="customerSentiment">
            <div class="fs_customer_sentiment">
                <div class="fs_ai_response_actions">
                    <div class="fs_ai_regenerate">
                        <el-button class="fs_outline_btn" @click="run('sentiment')">
                            <DynamicIcon name="Refresh"/>
                            {{ $t('Regenerate') }}
                        </el-button>
                    </div>
                    <div class="fs_ai_response_close" >
                        <el-button class="fs_ai_response_close_button" @click="close()">
                            <DynamicIcon name="CloseIcon"/>
                        </el-button>
                    </div>
                </div>
                <div>
                    <div class="fs_customer_sentiment_status">
                        <div class="fs_sentiment_emoji">{{ customerSentiment.emoji }}</div>
                        <div class="fs_sentiment_label">{{ $t('Tone of customer') }}</div>
                        <div class="fs_sentiment_text">{{ customerSentiment.text }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="fs_ai_response_loading" v-if="ResponseLoader">
        <el-skeleton :rows="4" animated />
    </div>
</template>

<script type="text/babel">
import ticketService from '@/admin/services/ticketService';
import DynamicIcon from '@/admin/Components/Icons/DynamicIcon.vue';

/**
 * The AI ticket summary and customer tone panel. ViewTicket.vue calls run()
 * from the header's AI menu; the panel remounts with the next ticket.
 */
export default {
    name: 'AiInsights',
    components: { DynamicIcon },
    props: ['ticketId'],
    data() {
        return {
            ticketSummary: '',
            customerSentiment: '',
            ResponseLoader: false
        };
    },
    methods: {
        close() {
            this.ticketSummary = '';
            this.customerSentiment = '';
        },

        // type: 'summary' or 'sentiment' (Pro ai/ routes).
        run(type) {
            const requestedTicketId = this.ticketId;
            this.ResponseLoader = true;
            const request = type === 'summary' ? ticketService.summary(requestedTicketId) : ticketService.tone(requestedTicketId);

            request
                .then(response => {
                    // Ignore a late response for the ticket we already left.
                    if (String(requestedTicketId) !== String(this.$route.params.ticket_id)) {
                        return;
                    }
                    if (type === 'summary') {
                        this.ticketSummary = response;
                        this.customerSentiment = '';
                    } else {
                        this.customerSentiment = this.sentiment(response);
                        this.ticketSummary = '';
                    }
                    this.ResponseLoader = false;
                })
                .catch((errors) => {
                    if (String(requestedTicketId) !== String(this.$route.params.ticket_id)) {
                        return;
                    }
                    this.ResponseLoader = false;
                    this.$handleError(errors);
                });
        },

        sentiment(response) {
            const sentimentData = {
                emoji: '',
                text: response
            };

            if (response.includes('Positive')) {
                sentimentData.emoji = '😀';
                sentimentData.text = 'Positive';
            } else if (response.includes('Neutral')) {
                sentimentData.emoji = '😐';
                sentimentData.text = 'Neutral';
            } else if (response.includes('Negative')) {
                sentimentData.emoji = '😡';
                sentimentData.text = 'Negative';
            }

            return sentimentData;
        }
    }
};
</script>
