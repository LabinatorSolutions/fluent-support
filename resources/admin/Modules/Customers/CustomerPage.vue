<template>
    <div class="fs_customer_page">
        <div v-if="customer" class="fs_box_wrapper">
            <div style="padding: 20px" class="fs_box_header">
                <div class="fs_box_head">
                    <el-breadcrumb separator="/">
                        <el-breadcrumb-item :to="{ name: 'Customers' }">{{$t('Customers')}}</el-breadcrumb-item>
                        <el-breadcrumb-item>{{ customer.first_name }} {{ customer.last_name }}</el-breadcrumb-item>
                    </el-breadcrumb>
                </div>
                <div class="fs_box_actions fs_customer_filters">
                    <div class="fs_cs_status_filter">
                    </div>
                </div>
            </div>

            <div style="margin-top: 20px">
                <el-row :gutter="30">
                    <el-col :sm="12" :md="18" :xs="24">
                        <div style="padding: 20px; margin-top: 2.3em;" class="fs_box_body">
                            <div class="fs_cs_profile_picture" @mouseover="showIcon" @mouseout="hideIcon">
                                <div class="fs_customer_avatar">

                                    <el-dropdown trigger="click" :hide-on-click="false" placement="bottom-start">
                                        <el-icon class="fs_customer_avatar_upload"> <Camera /> </el-icon>
                                        <template #dropdown>
                                            <el-dropdown-menu class="fs-cs-avatar-actions">
                                                <el-dropdown-item>
                                                    <el-upload
                                                        class="fs-avatar-uploader"
                                                        :action="upload_url"
                                                        :on-success ="handleAvatarSuccess"
                                                        :on-error="handleAvatarError"
                                                        :headers="requestHeaders"
                                                        :show-file-list="false"
                                                        drag
                                                    >
                                                        Upload a Custom Picture
                                                    </el-upload>

                                                    </el-dropdown-item>
                                                <el-dropdown-item v-if="customer.avatar">
<!--                                                    Reset To Default Gravatar-->
                                                    <el-popconfirm
                                                        confirm-button-text="Yes"
                                                        cancel-button-text="No"
                                                        title="Reset to gravatar?"
                                                        @confirm="confirmResetProfile"
                                                    >
                                                        <template #reference>
                                                            Reset To Default Gravatar
                                                        </template>
                                                    </el-popconfirm>
                                                </el-dropdown-item>
                                            </el-dropdown-menu>
                                        </template>
                                    </el-dropdown>
                                    <img v-if="customer.photo" :src="customer.photo" class="avatar"/>
                                </div>
                            </div>
                            <customer-form v-if="customer.id" :customer="customer" style="margin-top: 4em;"/>
                        </div>
                    </el-col>
                    <el-col :sm="12" :md="6" :xs="24">
                        <div class="fs_tk_sidebar_wrap">
                            <div v-if="customer" class="fs_tk_card fs_tk_profile_card">
                                <div class="fs_tk_card_header">
                                    <div class="fs_avatar">
                                        <a v-if="customer.profile_edit_url" :href="customer.profile_edit_url">
                                            <img :src="customer.photo" :alt="customer.full_name"/>
                                        </a>
                                        <img v-else :src="customer.photo" :alt="customer.full_name"/>
                                    </div>
                                </div>
                                <div class="fs_tk_card_body">
                                    <div class="fs_tk_line">
                                        <div class="fs_tk_profile_name">{{ customer.first_name }} {{
                                                customer.last_name
                                            }}
                                        </div>
                                    </div>
                                    <div class="fs_tk_line">
                                        <div class="fs_tk_contact_details">
                                            <a rel="noopener" target="_blank" v-if="customer.profile_edit_url"
                                               :href="customer.profile_edit_url">
                                                {{ customer.email }}
                                            </a>
                                            <span v-else> {{ customer.email }}</span>
                                        </div>
                                        <fluent-crm-profile v-if="fluentcrm_profile" :crm_profile="fluentcrm_profile" />
                                    </div>
                                </div>
                            </div>

                            <div v-if="widgets" v-for="(widget,widget_key) in widgets" :key="widget_key"
                                 :class="'fs_tk_widget_' + widget_key" class="fs_tk_card fs_tk_extra_card">
                                <div class="fs_tk_card_header">
                                    <h3 v-html="widget.header"></h3>
                                </div>
                                <div class="fs_tk_card_body">
                                    <div v-html="widget.body_html"></div>
                                </div>
                            </div>

                            <div class="fs_tk_card fs_tk_other_tickets_card">
                                <div class="fs_tk_card_header">
                                    <h3>{{$t('Support Tickets')}} ({{tickets.length}})</h3>
                                </div>
                                <div class="fs_tk_card_body">
                                    <ul>
                                        <li v-for="other_ticket in tickets" :key="'other_ticket_'+other_ticket.id">
                                            <router-link :to="{
                            name: 'view_ticket',
                            params: { ticket_id: other_ticket.id },
                            query: {prev_ticket: ticket_id}
                        }">
                                                <el-icon> <Message /> </el-icon> {{ other_ticket.title }} <span class="fs_badge" :class="'fs_badge_'+other_ticket.status">{{other_ticket.status}}</span>
                                            </router-link>
                                        </li>
                                    </ul>
                                </div>
                            </div>

                        </div>
                    </el-col>
                </el-row>
            </div>
        </div>
        <div class="fs_padded_20" v-else>
            <el-row :gutter="30">
                <el-col :sm="12" :md="18" :xs="24">
                    <el-skeleton style="background: white; box-sizing: border-box; padding: 20px;" :rows="9" animated/>
                </el-col>
                <el-col :sm="12" :md="6" :xs="24">
                    <el-skeleton style="margin-bottom: 20px;background: white; padding: 20px; box-sizing: border-box" :rows="3" animated/>
                    <el-skeleton style="background: white; padding: 20px; box-sizing: border-box" :rows="3" animated/>
                </el-col>
            </el-row>
        </div>
    </div>
</template>

<script type="text/babel">
import CustomerForm from './_CustomerForm';
import FluentCrmProfile from '@/admin/Modules/Tickets/parts/_CrmProfile';
import {onMounted, reactive, toRefs} from "vue";
import {useFluentHelper, useNotify} from "@/admin/Composable/FluentFrameworkHelper";

export default {
    name: "CustomerPage",
    props: ['customer_id'],
    components: {
        CustomerForm,
        FluentCrmProfile
    },
    setup(props, context) {
        const {
            appVars,
            translate,
            get,
            post,
            handleError
        } = useFluentHelper();

        const { notify } = useNotify();
        const customer_id = props.customer_id;
        const state = reactive({
            customer: {},
            tickets: [],
            widgets: {},
            loading_sidebar: false,
            fluentcrm_profile: false,
            loading: false,
            upload_url: appVars.rest.url+`/customers/profile_image/'${customer_id}`,
            requestHeaders: {
                //'X-WP-Nonce': fluentcrm.nonce
            }
        });

        const fetchCustomer = async () => {
            state.loading = !state.loading;
            try {
                await get(`customers/${customer_id}`, {
                    with: ['widgets', 'tickets', 'fluentcrm_profile']
                }).then(response => {
                    state.customer = response.customer;
                    state.tickets = response.tickets;
                    state.widgets = response.widgets;
                    state.fluentcrm_profile = response.fluentcrm_profile;
                })
                    .catch(errors => {
                        this.$handleError(errors);
                    })
                    .always(() => {
                        state.loading = !state.loading;
                    });
            } catch (e) {
                handleError(e);
            }
        };

        const handleAvatarSuccess = (response, file) => {
            if (response.success) {
                state.customer.photo = URL.createObjectURL(file.raw);
                notify({
                    title: translate('Success'),
                    message: translate('Profile picture has been updated successfully'),
                    type: 'success'
                });
            }
        };

        const handleAvatarError = (err, _) => {
            let errorMessage = JSON.parse(err.message);

            notify.error({
                message: translate(errorMessage.message),
                position: 'bottom-right',
                type: 'error'
            });
        };

        const showIcon = () => {
            document.querySelector('i.fs_customer_avatar_upload').style.display = 'inline-flex';
        };

        const hideIcon = () => {
            document.querySelector('i.fs_customer_avatar_upload').style.display = 'none';
        };
        const confirmResetProfile = async () => {
            state.loading = !state.loading;
            try {
                await post(`customers/reset_avatar/${customer_id}`)
                    .then(response => {
                        notify.success({
                            message: translate(response.message),
                            position: 'bottom-right',
                            type: 'success'
                        })
                    })
                    .catch(errors => {
                        handleError(errors);
                    })
                    .always(() => {
                        state.loading = !state.loading;
                    });
            } catch (e) {
                handleError(e);
            }
        }

        onMounted(() => {
            fetchCustomer();
        });

        return {
            fetchCustomer,
            handleAvatarSuccess,
            handleAvatarError,
            showIcon,
            hideIcon,
            confirmResetProfile,
            translate,
            post,
            handleError,
            notify,
            ...toRefs(state)
        }
    },
    /*methods: {
        fetchCustomer() {
            this.loading = !this.loading;
            this.$get('customers/' + this.customer_id, {
                with: ['widgets', 'tickets', 'fluentcrm_profile']
            })
                .then(response => {
                    this.customer = response.customer;
                    this.widgets = response.widgets;
                    this.tickets = response.tickets;
                    this.fluentcrm_profile = response.fluentcrm_profile;
                })
                .catch(errors => {
                    this.$handleError(errors);
                })
                .always(() => {
                    this.loading = !this.loading;
                })
        },
        handleAvatarSuccess(res, file) {
            this.customer.photo = URL.createObjectURL(file.raw);
            this.$notify.success({
                message: 'Profile picture has been updated successfully',
                position: 'bottom-right'
            });
        },
        handleAvatarError(err, _){
            let errorMessage = JSON.parse(err.message);

            this.$notify.error({
                message: errorMessage.message,
                position: 'bottom-right'
            });
        },
        showIcon() {
            document.querySelector('i.fs_customer_avatar_upload').style.display = 'inline-flex';
        },
        hideIcon() {
            document.querySelector('i.fs_customer_avatar_upload').style.display = 'none';
        },
        confirmResetProfile(){
            this.loading = !this.loading;
            this.$post('customers/reset_avatar/' + this.customer_id)
                .then(response => {
                    this.$notify.success({
                        message: response.message,
                        position: 'bottom-right'
                    });

                    this.fetchCustomer();
                })
                .catch(errors => {
                    this.$handleError(errors);
                })
                .always(() => {
                    this.loading = !this.loading;
                })
        }
    },
    mounted() {
        this.fetchCustomer();
    }*/
}
</script>


<style lang="scss">
.fs_tk_contact_details{
    word-break: break-all;
}
.fs_cs_profile_picture{
    position: absolute;
    top: -0.8em;
    left: 1.9em;

    .fs_customer_avatar{
        img {
            border: none;
            width: 7.3em;
            height: 7.3em;
            border-radius: 50%;
            box-shadow: 0 4px 6px rgb(147 161 175 / 50%);
        }
        .fs_customer_avatar_upload{
            display: none;
            left: 41px;
            top: 43px;
            cursor: pointer;
            z-index: 10000;
            font-size: 22px;
            position: absolute;
            background-color: #f5f7fa;
            border-radius: 32%;
            padding: 3px;
        }
    }
    .fs-avatar-uploader{
        .el-upload-dragger>i {
            display: none;
            font-size: 2.9em;
            color: #fafafa;
            position: absolute;
            top: 0.8em;
            left: 0.8em;
        }
        img {
            border: none;
            width: 7.3em;
            height: 7.3em;
            border-radius: 50%;
            box-shadow: 0 4px 6px rgb(147 161 175 / 50%);
        }
        .avatar-uploader .el-upload {
            border-radius: 6px;
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }
    }
}

.fs-avatar-uploader .el-upload-dragger {
    background-color: unset;
    border: unset;
    border-radius: unset;
    width: unset;
    height: unset;
    text-align: unset;
}
.fs-cs-avatar-actions {
    display: flex;
    flex-direction: column;
}

</style>
