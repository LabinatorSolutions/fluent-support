<template>
    <div class="fs_mailboxes">
        <div v-if="mailbox" class="fs_box_wrapper">
            <div style="margin-bottom: 20px;" class="fs_box_header">
                <div class="fs_box_head">
                    <h3>{{ translate('Settings') }}: {{ mailbox.name }}</h3>
                </div>
                <div class="fs_box_actions">

                </div>
            </div>
            <div class="fs_tickets_view fs_settings_view">
                <div class="inner_sidebar">
                    <ul>
                        <li>
                            <router-link :to="{ name: 'box_settings', params: { box_id: mailbox.id } }">
                                <el-icon> <Box /> </el-icon>
                                {{ translate('Inbox Settings') }}
                            </router-link>
                        </li>
                        <li>
                            <router-link :to="{ name: 'email_settings', params: { box_id: mailbox.id } }">
                                <el-icon> <Message /> </el-icon>
                                {{ translate('Email Settings') }}
                            </router-link>
                        </li>
                        <li v-if="mailbox && mailbox.box_type == 'email'">
                            <router-link :to="{ name: 'email_piping', params: { box_id: mailbox.id } }">
                                <el-icon> <Connection /> </el-icon>
                                {{ translate('Email Piping') }}
                            </router-link>
                        </li>
                    </ul>
                </div>
                <div class="inner_body fs_box_body fs_padded_20">
                    <router-view :mailbox="mailbox"/>
                </div>
            </div>
        </div>
        <div style="padding: 20px; background: white;" class="fs_box_body" v-else>
            <el-skeleton class="fs_box_wrapper" :rows="5" animated/>
        </div>
    </div>
</template>

<script type="text/babel">
import { onMounted, reactive, toRefs } from 'vue';
import {useFluentHelper, useNotify} from "@/admin/Bits/FluentFramework";
export default {
    name: 'MailBoxRoot',
    props: ['box_id'],

    setup(props){
        const {
            get,
            handleError,
            setTitle,
            translate,

        } = useFluentHelper();

        const state = reactive({
            fetching: true,
            mailbox: false
        })

        const fetch = async ()=>{
            state.fetching = true;
            get(`mailboxes/${props.box_id}`)
                .then((response) => {
                    state.mailbox = response.mailbox;
                    setTitle(response.mailbox.name + ' Settings');
                })
                .catch((errors) => {
                    handleError(errors)
                })
                .always(() => {
                    state.fetching = false;
                })
        }

        onMounted(()=>{
            fetch();
            setTitle('Business Settings');
        })

        return{
            fetch,
            translate,
            ...toRefs(state)
        }

    }
}
</script>
