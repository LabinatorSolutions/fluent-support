<template>
    <div class="fs_products">
        <div class="fs_box_wrapper">
            <div v-loading="loading" class="fs_box_body">
                <el-tabs v-model="active_tab" tab-position="left">
                    <el-tab-pane name="global-settings" label="Global Settings">
                        <global-settings @saveSettings="saveSettings" v-if="active_tab == 'global-settings'" />
                    </el-tab-pane>
                    <el-tab-pane name="ticket-submitted" label="Ticket Submitted">
                        <ticket-submitted v-if="active_tab == 'ticket-submitted'" />
                    </el-tab-pane>
                    <el-tab-pane name="ticket-assigned" label="Ticket Assigned">
                        <ticket-assigned v-if="active_tab == 'ticket-assigned'" />
                    </el-tab-pane>
                    <el-tab-pane name="replied-by-customer" label="Replied By Customer">
                        <replied-by-customer v-if="active_tab == 'replied-by-customer'" />
                    </el-tab-pane>
                    <el-tab-pane name="replied-by-agent" label="Replied By Agent">
                        <replied-by-agent v-if="active_tab == 'replied-by-agent'" />
                    </el-tab-pane>
                    <el-tab-pane name="closed-by-customer" label="Closed by Customer">
                        <closed-by-customer v-if="active_tab == 'closed-by-customer'" />
                    </el-tab-pane>
                    <el-tab-pane name="closed-by-agent" label="Closed by Agent">
                        <closed-by-agent v-if="active_tab == 'closed-by-agent'" />
                    </el-tab-pane>
                </el-tabs>
            </div>
            <pre>{{active_tab}}</pre>
        </div>
    </div>
</template>

<script type="text/babel">

import GlobalSettings from './EmailParts/GlobalSettings';
import TicketSubmitted from './EmailParts/TicketSubmitted';
import TicketAssigned from './EmailParts/TicketAssigned';
import RepliedByCustomer from './EmailParts/RepliedByCustomer';
import RepliedByAgent from './EmailParts/RepliedByAgent';
import ClosedByCustomer from './EmailParts/TicketClosedByCustomer';
import ClosedByAgent from './EmailParts/TicketClosedByAgent';

export default {
    name: 'emailNotifications',
    components: {
        GlobalSettings,
        TicketSubmitted,
        TicketAssigned,
        RepliedByCustomer,
        RepliedByAgent,
        ClosedByCustomer,
        ClosedByAgent
    },
    data() {
        return {
            active_tab: 'global-settings',
            loading: false
        }
    },
    methods: {
        saveSettings(settingsKey, settings) {
            this.loading = true;
            this.$post('settings', {
                settings_key: settingsKey,
                settings: settings
            })
            .then(response => {
               this.$notify({
                   type: 'success',
                   message: response.message,
                   position: 'bottom-right'
               })
            })
            .catch((errors) => {
                this.$handleError(errors);
            })
            .always(() => {
                this.loading = false;
            })
        }
    },
    mounted() {
        this.$setTitle('Email Notifications');
    }
}
</script>
