<template>
    <div class="fs_mailboxes">
        <div v-if="mailbox" class="fs_box_wrapper">

            <div class="fs_breadcrumb">
                <el-breadcrumb separator=">">
                    <el-breadcrumb-item :to="{ name: 'mailboxes' }">{{$t('Business Inboxes')}}</el-breadcrumb-item>
                    <el-breadcrumb-item>{{ mailbox.name }}</el-breadcrumb-item>
                </el-breadcrumb>
            </div>

            <!-- Menu Container -->
            <div class="fs_mailbox_settings_wrapper">
                <div class="fs_menu_container">
                    <div class="fs_menu_item" :class="{ active: $route.name === 'box_settings' }">
                        <router-link :to="{ name: 'box_settings', params: { box_id: mailbox.id } }">
                            <DynamicIcon name="InboxSettings"/>
                            <span>{{ $t('Inbox Settings') }}</span>
                            <div class="fs_menu_arrow_wrapper">
                                <DynamicIcon name="Arrow" v-if="$route.name === 'box_settings'" class="fs_menu_arrow"/>
                            </div>
                        </router-link>
                    </div>

                    <div class="fs_menu_item" :class="{ active: $route.name === 'email_settings' }">
                        <router-link :to="{ name: 'email_settings', params: { box_id: mailbox.id } }">
                            <DynamicIcon name="EmailSettings"/>
                            <span>{{ $t('Email Settings') }}</span>
                            <div class="fs_menu_arrow_wrapper">
                                <DynamicIcon name="Arrow" v-if="$route.name === 'email_settings'" class="fs_menu_arrow"/>
                            </div>
                        </router-link>
                    </div>

                    <div v-if="mailbox && mailbox.box_type == 'email'" class="fs_menu_item" :class="{ active: $route.name === 'email_piping' }">
                        <router-link :to="{ name: 'email_piping', params: { box_id: mailbox.id } }">
                            <DynamicIcon name="EmailPiping"/>
                            <span>{{ $t('Email Piping') }}</span>
                            <div class="fs_menu_arrow_wrapper">
                                <DynamicIcon name="Arrow" v-if="$route.name === 'email_piping'" class="fs_menu_arrow"/>
                            </div>
                        </router-link>
                    </div>
                </div>

                <div class="inner_body fs_box_body fs_padded_20">
                    <router-view :mailbox="mailbox"/>
                </div>
            </div>
        </div>
        <div class="fs_box_wrapper" v-else>
            <div class="fs_breadcrumb">
                <el-skeleton animated>
                    <template #template>
                        <el-skeleton-item variant="text" style="width: 220px;" />
                    </template>
                </el-skeleton>
            </div>
            <div class="fs_mailbox_settings_wrapper">
                <div class="fs_menu_container">
                    <div v-for="n in 3" :key="n" class="fs_menu_item">
                        <el-skeleton animated>
                            <template #template>
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <el-skeleton-item variant="rect" style="width: 20px; height: 20px; flex-shrink: 0;" />
                                    <el-skeleton-item variant="text" style="width: 60%;" />
                                </div>
                            </template>
                        </el-skeleton>
                    </div>
                </div>
                <div class="inner_body fs_box_body fs_padded_20">
                    <FormSkeleton :fields="5" :button="false" />
                </div>
            </div>
        </div>
    </div>
</template>

<script type="text/babel">
import DynamicIcon from '@/admin/Components/Icons/DynamicIcon.vue';
import FormSkeleton from '@/admin/Components/FormSkeleton.vue';

export default {
    name: 'MailBoxRoot',
    components: {
        DynamicIcon,
        FormSkeleton
    },
    props: ['box_id'],
    data() {
        return {
            fetching: true,
            mailbox: false
        }
    },
    mounted() {
        this.fetch();
        this.$setTitle('Business Settings');
    },
    methods: {
        fetch() {
            this.fetching = true;
            this.$get(`mailboxes/${this.box_id}`)
                .then((response) => {
                    this.mailbox = response.mailbox;
                    this.$setTitle(response.mailbox.name + ' Settings');
                })
                .catch((errors) => {
                    this.$handleError(errors)
                })
                .always(() => {
                    this.fetching = false;
                })
        }
    }
}
</script>
