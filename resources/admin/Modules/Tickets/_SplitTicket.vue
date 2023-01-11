<template>
    <el-form :data="ticket_data" label-position="top">
        <el-form-item :label="$t('Ticket Title')">
            <el-input v-model="ticket_data.title" :placeholder="$t('Ticket Title')" />
        </el-form-item>
        <el-form-item :label="$t('Ticket Content')">
            <wp-editor v-model="ticket_data.content" />
        </el-form-item>

        <el-row :gutter="30">
            <el-col :md="12" :xs="24">
                <el-form-item :label="$t('Ticket Priority')">
                    <el-select v-model="ticket_data.client_priority" :placeholder="$t('Ticket Priority')">
                        <el-option v-for="(priorityValue, priorityKey) in priorities" :key="priorityKey" :value="priorityKey" :label="priorityValue"></el-option>
                    </el-select>
                </el-form-item>
            </el-col>
            <el-col :md="12" :xs="24">
                <el-form-item :label="$t('Ticket Product')" v-if="products.length">
                    <el-select v-model="ticket_data.product_id" :placeholder="$t('Ticket Product')">
                        <el-option v-for="product in products" :key="product.id" :value="product.id" :label="product.title"></el-option>
                    </el-select>
                </el-form-item>
            </el-col>
            <el-col :md="12" :xs="24">
                <el-form-item :label="$t('Ticket Mailbox')" v-if="mailboxes.length">
                    <el-select v-model="ticket_data.mailbox_id" :placeholder="$t('Ticket Mailbox')">
                        <el-option v-for="mailbox in mailboxes" :key="mailbox.id" :value="mailbox.id" :label="mailbox.name"></el-option>
                    </el-select>
                </el-form-item>
            </el-col>
        </el-row>

        <el-form-item>
            <el-button @click="splitTicket" :disabled=" spliting || !ticket_data.title " v-loading="spliting" type="primary">
                {{ $t('Split Ticket') }}
            </el-button>
        </el-form-item>
    </el-form>
</template>

<script>
import WpEditor from '../../Pieces/_wp_editor';
import { reactive, toRefs } from "vue";
import { useFluentHelper } from "@/admin/Composable/FluentFrameworkHelper";
export default {
    name: "SplitTicket",
    components: {
        WpEditor,
    },
    props: {
        ticket_data: {
            type: Object,
            required: true,
        },
        spliting : {
            type: Boolean,
            required: true,
        },
    },
    setup(props, context) {
        const {
            appVars
        } = useFluentHelper();
        const state = reactive({
            priorities: appVars.client_priorities,
            products: appVars.support_products,
            mailboxes: appVars.mailboxes,
        });
        const { emit } = context;

        const splitTicket = () => {
            emit('split-ticket');
        };

        return {
            ...toRefs(state),
            splitTicket,
        };
    }
}
</script>

<style scoped>

</style>
