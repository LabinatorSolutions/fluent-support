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
                                <el-upload
                                    class="fs-avatar-uploader"
                                    :action="upload_url"
                                    :on-success ="handleAvatarSuccess"
                                    :headers="requestHeaders"
                                    :show-file-list="false"
                                    drag
                                >
                                    <img v-if="customer.photo" :src="customer.photo" class="avatar"/>
                                    <i class="el-icon-upload avatar-uploader-icon"></i>
                                </el-upload>
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
                                                <i class="el-icon-message"></i> {{ other_ticket.title }} <span class="fs_badge" :class="'fs_badge_'+other_ticket.status">{{other_ticket.status}}</span>
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

export default {
    name: "CustomerPage",
    props: ['customer_id'],
    components: {
        CustomerForm,
        FluentCrmProfile
    },
    data() {
        return {
            loading: false,
            loading_sidebar: false,
            customer: false,
            widgets: false,
            tickets: [],
            fluentcrm_profile: false,
            upload_url: this.appVars.rest.url+'/customers/profile_image/'+this.customer_id,
            requestHeaders: {
                'X-WP-Nonce': this.appVars.rest.nonce
            }
        }
    },
    methods: {
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
        showIcon() {
            document.querySelector('.avatar-uploader-icon').style.display = 'initial';
        },
        hideIcon() {
            document.querySelector('.avatar-uploader-icon').style.display = 'none';
        }
    },
    mounted() {
        this.fetchCustomer();
    }
}
</script>


<style lang="scss">
.fs_cs_profile_picture{
    position: absolute;
    top: -0.8em;
    left: 1.9em;
    .fs-avatar-uploader{
        .avatar-uploader-icon {
            display: none;
            font-size: 2.9em;
            color: #fafafa;
            position: absolute;
            top: -0.4em;
            left: 0.8em;
        }
        .el-upload-dragger {
            width: unset;
            height: unset;
            background: transparent;
            border: none;
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
</style>
