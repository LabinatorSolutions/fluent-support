<template>
    <div class="fs_view_ticket">
        <template v-if="ticket && ticket.id">
            <div class="fs_ticket_body">
                <div class="fs_ticket_actions">
                    <ul class="fs_tk_actions">
                        <template v-if="ticket.status != 'closed'">
                            <li :title="$t('Add Reply')"
                                class="fs_add_reply"
                                :class="(show_response_box == 'response') ? 'fs_action_active' : ''"
                                @click="show_response_box = 'response'">
                                <el-icon style="vertical-align: middle;"><chat-line-square /></el-icon>
                            </li>
                            <li :title="$t('Add Internal Note')"
                                class="fs_add_note"
                                :class="(show_response_box == 'note') ? 'fs_action_active' : ''"
                                @click="show_response_box = 'note'">
                                <el-icon style="vertical-align: middle;"><notebook /></el-icon>
                            </li>
                        </template>

                        <li title="Run Workflow" class="fs_add_workflow"
                            v-if="appVars.manual_workflows && appVars.manual_workflows.length">
                            <work-flow-selector @reloadTickets="fetchTicket()" :ticket_ids="[ticket_id]"/>
                        </li>

                        <li :title="$t('Assigned Agent ') + ticket.agent?.full_name">
                            <el-popover
                                placement="bottom"
                                :width="400"
                                trigger="click"
                            >
                                <template #reference>
                                <span class="fs_agent_photo_icon" v-if="ticket.agent">
                                    <img :alt="ticket.agent?.full_name" :src="ticket.agent?.photo"/>
                                </span>
                                    <el-icon style="vertical-align: middle;" v-else><user /></el-icon>
                                </template>

                                <el-select filterable @change="updateTicketAttr('agent_id')" v-model="ticket.agent_id">
                                    <el-option
                                        v-for="agent in agents"
                                        :key="agent.id"
                                        :value="agent.id"
                                        :label="agent.full_name"></el-option>
                                </el-select>
                            </el-popover>
                        </li>

                    </ul>
                    <div class="fs_product">
                        <el-button v-loading="loading" @click="fetchTicket()"
                                   icon="Refresh"
                                   class="fs_refresh_tk_page"
                                   size="small"></el-button>
                        <el-button v-loading="updating" :disabled="updating" @click="closeTicket()"
                                   v-if="ticket.status != 'closed'" class="fs_close_btn" type="info" size="small">
                            {{ $t('Close') }}
                        </el-button>
                        <el-popover
                            placement="bottom"
                            :width="400"
                            trigger="click"
                        >
                            <template #reference>
                                <span style="margin-right: 10px;"><el-icon style="vertical-align: middle;"><goods /></el-icon> {{
                                        ticket.product?.title
                                    }}</span>
                            </template>

                            <el-select @change="updateTicketAttr('product_id')" v-model="ticket.product_id">
                                <el-option
                                    v-for="product in products"
                                    :key="product.id"
                                    :value="product.id"
                                    :label="product.title"></el-option>
                            </el-select>

                        </el-popover>

                        <el-popover
                            placement="bottom"
                            :width="400"
                            trigger="click"
                            v-if="appVars.me.permissions.includes('fst_manage_settings')"
                        >
                            <template #reference>
                                <span class="fs_business_name" style="margin-right: 10px;">
                                    <el-icon style="vertical-align: middle;"><office-building /></el-icon> {{ticket.mailbox?.name}}
                                </span>
                            </template>
                            <el-select @change="changeMailbox" v-model="ticket.mailbox.name">
                                <el-option
                                    v-for="mailbox in mailboxes"
                                    :key="mailbox.id"
                                    :value="mailbox.id"
                                    :label="mailbox.name"></el-option>
                            </el-select>
                        </el-popover>

                        <span v-else class="fs_business_name" style="margin-right: 10px;">
                            <el-icon style="vertical-align: middle;"><office-building /></el-icon> {{ticket.mailbox?.name}}
                        </span>

                        <el-dropdown style="vertical-align: middle;" trigger="click">
                            <span class="el-dropdown-link">
                              <el-icon style="transform: rotate(90deg)"><More /></el-icon>
                            </span>
                            <template #dropdown>
                                <el-dropdown-menu>
                                    <el-dropdown-item v-if="has_pro" @click='(show_merge_modal=true) && (customerTickets())'>
                                        <svg width="14" height="14" viewBox="0 0 14 14" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <path d="M4.14447 5.12748C4.24566 5.4978 4.46577 5.8246 4.77092 6.05754C5.07606 6.29048 5.44932 6.41666 5.83322 6.41665H8.16655C8.85374 6.4167 9.51884 6.65933 10.0446 7.10178C10.5704 7.54423 10.9232 8.15808 11.0406 8.83515C11.4365 8.96431 11.7734 9.23039 11.9908 9.5856C12.2082 9.9408 12.2918 10.3619 12.2267 10.7732C12.1616 11.1845 11.9519 11.5591 11.6355 11.8298C11.319 12.1005 10.9163 12.2495 10.4999 12.25C10.0924 12.2503 9.69767 12.1084 9.38362 11.8489C9.06957 11.5893 8.85594 11.2283 8.77956 10.828C8.70318 10.4278 8.76884 10.0135 8.96522 9.65651C9.1616 9.29952 9.47639 9.02224 9.85531 8.87248C9.75412 8.50216 9.534 8.17537 9.22886 7.94242C8.92371 7.70948 8.55045 7.5833 8.16655 7.58331H5.83322C5.20199 7.58424 4.58765 7.37946 4.08322 6.99998V8.84915C4.47259 8.98676 4.80078 9.2576 5.00976 9.61378C5.21875 9.96997 5.29509 10.3886 5.22528 10.7956C5.15547 11.2026 4.94401 11.5719 4.62827 11.8381C4.31254 12.1043 3.91286 12.2503 3.49989 12.2503C3.08691 12.2503 2.68724 12.1043 2.3715 11.8381C2.05577 11.5719 1.84431 11.2026 1.7745 10.7956C1.70469 10.3886 1.78102 9.96997 1.99001 9.61378C2.199 9.2576 2.52718 8.98676 2.91655 8.84915V5.15081C2.52979 5.01426 2.20325 4.74624 1.99393 4.39351C1.7846 4.04079 1.70577 3.62576 1.7712 3.22085C1.83663 2.81594 2.04216 2.44686 2.35192 2.17801C2.66169 1.90916 3.05602 1.75762 3.46611 1.74983C3.8762 1.74204 4.276 1.87849 4.59576 2.13537C4.91551 2.39226 5.13491 2.75326 5.21568 3.15539C5.29644 3.55753 5.23344 3.97525 5.03766 4.33567C4.84189 4.6961 4.52577 4.97633 4.14447 5.12748V5.12748ZM3.49989 4.08331C3.6546 4.08331 3.80297 4.02186 3.91237 3.91246C4.02176 3.80306 4.08322 3.65469 4.08322 3.49998C4.08322 3.34527 4.02176 3.1969 3.91237 3.0875C3.80297 2.97811 3.6546 2.91665 3.49989 2.91665C3.34518 2.91665 3.1968 2.97811 3.08741 3.0875C2.97801 3.1969 2.91655 3.34527 2.91655 3.49998C2.91655 3.65469 2.97801 3.80306 3.08741 3.91246C3.1968 4.02186 3.34518 4.08331 3.49989 4.08331ZM3.49989 11.0833C3.6546 11.0833 3.80297 11.0219 3.91237 10.9125C4.02176 10.8031 4.08322 10.6547 4.08322 10.5C4.08322 10.3453 4.02176 10.1969 3.91237 10.0875C3.80297 9.9781 3.6546 9.91665 3.49989 9.91665C3.34518 9.91665 3.1968 9.9781 3.08741 10.0875C2.97801 10.1969 2.91655 10.3453 2.91655 10.5C2.91655 10.6547 2.97801 10.8031 3.08741 10.9125C3.1968 11.0219 3.34518 11.0833 3.49989 11.0833ZM10.4999 11.0833C10.6546 11.0833 10.803 11.0219 10.9124 10.9125C11.0218 10.8031 11.0832 10.6547 11.0832 10.5C11.0832 10.3453 11.0218 10.1969 10.9124 10.0875C10.803 9.9781 10.6546 9.91665 10.4999 9.91665C10.3452 9.91665 10.1968 9.9781 10.0874 10.0875C9.97801 10.1969 9.91655 10.3453 9.91655 10.5C9.91655 10.6547 9.97801 10.8031 10.0874 10.9125C10.1968 11.0219 10.3452 11.0833 10.4999 11.0833Z" fill="currentColor"/>
                                        </svg> {{ $t('Merge Tickets') }}
                                    </el-dropdown-item>

                                    <el-dropdown-item @click='(show_watcher_modal=true)' v-if="!watchers.length">
                                        <svg width="14" height="14" viewBox="0 0 14 14" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <path d="M10.5 1.16634C9.21115 1.16634 8.16634 2.21115 8.16634 3.49999C8.16634 4.78882 9.21115 5.83362 10.5 5.83362C10.982 5.83362 11.4262 5.69291 11.796 5.44517C11.9298 5.35553 12.111 5.39134 12.2006 5.52517C12.2903 5.659 12.2545 5.84015 12.1206 5.92981C11.6548 6.24184 11.0978 6.41695 10.5 6.41695C8.88897 6.41695 7.58301 5.11099 7.58301 3.49999C7.58301 1.88899 8.88897 0.583008 10.5 0.583008C12.111 0.583008 13.417 1.88899 13.417 3.49999L13.4169 3.50571V3.93748C13.4169 4.50127 12.9599 4.95832 12.3961 4.95832C12.0449 4.95832 11.7351 4.78094 11.5514 4.51087C11.2861 4.78669 10.9132 4.95833 10.5003 4.95833C9.69485 4.95833 9.04192 4.30541 9.04192 3.49999C9.04192 2.69458 9.69485 2.04166 10.5003 2.04166C10.8286 2.04166 11.1315 2.15014 11.3753 2.33322C11.3753 2.17219 11.5059 2.04166 11.6669 2.04166C11.828 2.04166 11.9586 2.17225 11.9586 2.33333V3.93748C11.9586 4.17911 12.1545 4.37498 12.3961 4.37498C12.6377 4.37498 12.8336 4.17911 12.8336 3.93748V3.49687L12.8336 3.49191C12.8293 2.20679 11.7862 1.16634 10.5 1.16634ZM9.62526 3.49999C9.62526 3.98325 10.017 4.37499 10.5003 4.37499C10.9835 4.37499 11.3753 3.98325 11.3753 3.49999C11.3753 3.01674 10.9835 2.62499 10.5003 2.62499C10.017 2.62499 9.62526 3.01674 9.62526 3.49999Z" fill="currentColor"/>
                                            <path d="M12.8334 8.60417V6.10878C12.5737 6.34124 12.2791 6.53543 11.9584 6.68267V8.60417C11.9584 9.16796 11.5014 9.625 10.9376 9.625H7.29962L4.375 11.8128L4.37438 9.625H3.06258C2.49879 9.625 2.04175 9.16796 2.04175 8.60417V3.64583C2.04175 3.08204 2.49879 2.625 3.06258 2.625H7.11033C7.19025 2.31457 7.31164 2.02082 7.46832 1.75H3.06258C2.01554 1.75 1.16675 2.59879 1.16675 3.64583V8.60417C1.16675 9.65119 2.01554 10.5 3.06258 10.5H3.49962L3.50008 12.1041C3.50008 12.2614 3.55104 12.4146 3.64534 12.5407C3.88654 12.8632 4.34349 12.9291 4.66598 12.6879L7.59071 10.5H10.9376C11.9846 10.5 12.8334 9.65119 12.8334 8.60417Z" fill="currentColor"/>
                                        </svg> {{ $t('Add Watcher')}}
                                    </el-dropdown-item>
                                </el-dropdown-menu>
                            </template>
                        </el-dropdown>

                        <el-dialog
                            v-model="show_merge_modal"
                            v-if="has_pro && show_merge_modal"
                            :title="$t('Merge Tickets')"
                        >
                            <div class="fs_box_body" v-if="customer_tickets.length">
                                <el-table :data="customer_tickets" style="width: 100%">
                                    <el-table-column prop="id" label="ID" width="130"></el-table-column>
                                    <el-table-column prop="title" label="Title" width="250"></el-table-column>
                                    <el-table-column prop="status" label="Status" width="130"></el-table-column>
                                    <el-table-column label="Action">
                                        <template #default="scope">
                                            <el-button size="small" type="primary" @click="mergeTickets(scope.row.id)">
                                                {{$t('Merge')}}
                                            </el-button>
                                        </template>
                                    </el-table-column>
                                </el-table>
                                <div style="padding-bottom: 20px;" class="fframe_pagination_wrapper">
                                    <pagination @fetch="customerTickets" :pagination="pagination" />
                                </div>
                            </div>
                            <div class="fs_box_body" v-else-if="customer_tickets.length == 0">
                                <h3>{{$t('customer_has_one_tk')}}</h3>
                            </div>
                            <div style="padding: 20px; background: white;" class="fs_box_body" v-else>
                                <el-skeleton :rows="5" animated/>
                            </div>
                        </el-dialog>

                        <el-dialog
                            v-model="show_watcher_modal"
                            v-if="show_watcher_modal"
                            :title="$t('Add watcher')"
                        >
                            <div class="fs_box_body">
                                <el-select :multiple="true" v-model="watchers">
                                    <el-option
                                        v-for="(agent,agent_key) in agents"
                                        :key="agent_key" :value="agent.id"
                                        :label="agent.full_name"></el-option>
                                </el-select>

                                <el-button type="primary" size="small"
                                           style="margin-top: 20px" @click="addWatchers">{{$t('Add')}}
                                </el-button>
                            </div>
                        </el-dialog>
                    </div>
                </div>
                <div class="fs_th_header">
                    <div class="fs_header_group">
                        <div class="fs_tk_subject">
                            <h2 :title="$t('Click to Edit Subject')">
                                <el-popover
                                    placement="bottom"
                                    :width="400"
                                    trigger="click"
                                >
                                    <template #reference>
                                        <span> {{ ticket?.title }}</span>
                                    </template>

                                    <el-input @keyup.enter="updateTicketAttr('title')"
                                              v-model="ticket.title"></el-input>
                                    <p>{{ $t('Press enter to save') }}</p>
                                </el-popover>
                            </h2>
                            <ticket-tags :creatable="true" :ticket_id="ticket.id" :tags.sync="ticket.tags"/>
                        </div>
                        <div class="fs_tk_badges">
                            <span class="fs_ticket_id">#{{ ticket.id }} </span>
                            <el-popover
                                placement="bottom"
                                :width="400"
                                trigger="click"
                            >
                                <template #reference>
                                    <span :title="$t('Client Priority: ') + ticket.client_priority "
                                          :class="'fs_badge_priority_'+ticket.client_priority" class="fs_badge">
                                        <el-icon style="vertical-align: middle;"><user /></el-icon> {{ ticket.client_priority }}</span>
                                </template>

                                <el-select @change="updateTicketAttr('client_priority')"
                                           v-model="ticket.client_priority"
                                           size="small">
                                    <el-option

                                        v-for="(priorityLabel, priority) in client_priorities"
                                        :key="priority" :value="priority"
                                        :label="priorityLabel"></el-option>
                                </el-select>
                            </el-popover>
                            <el-popover
                                placement="bottom"
                                :width="400"
                                trigger="click"
                            >
                                <template #reference>
                                    <span :title="$t('Admin Priority:') + ticket.priority "
                                          :class="'fs_badge_priority_'+ticket.priority" class="fs_badge">
                                        <el-icon style="vertical-align: middle;"><service /></el-icon> {{ ticket.priority }}</span>
                                </template>

                                <el-select @change="updateTicketAttr('priority')" v-model="ticket.priority"
                                           size="small">
                                    <el-option

                                        v-for="(priorityLabel, priority) in admin_priorities"
                                        :key="priority" :value="priority"
                                        :label="priorityLabel"></el-option>
                                </el-select>
                            </el-popover>
                            <span class="fs_badge" :class="'fs_badge_' + ticket.status">{{ ticket.status }}</span>
                        </div>
                    </div>
                </div>
                <create-response
                    v-if="show_response_box && ticket.status != 'closed'"
                    @created="recordNewResponse"
                    :ticket="ticket"
                    @close="show_response_box = ''"
                    :type="show_response_box"
                />
                <div class="fs_create_response text-align-center" v-if="ticket.status == 'closed'">
                    <p>{{ $t('ticket_closed') }} {{ ticket.resolved_at }}
                        <span v-if="ticket.closed_by_person">
                            by <b>{{ getHumanName(ticket.closed_by_person) }}</b>
                        </span></p>
                    <el-button v-loading="updating" :disabled="updating" @click="reOpen()" type="info" size="small">
                        {{ $t('Reopen This ticket') }}
                    </el-button>
                </div>
                <div v-if="ticket && ticket.id" class="fs_threads_container">
                    <article v-for="conversation in conversations"
                             :key="conversation.id"
                             class="fs_thread"
                             :class="(conversation.person.title && conversation.person.person_type != 'customer' ) ? 'fs_agent fs_conv_type_'+conversation.conversation_type : getTicketClasses(conversation) ">

                        <span class="agent_title"
                              v-if="conversation.person.title"> {{ conversation.person.title }} </span>

                        <div class="fs_thread_content">
                            <section class="fs_avatar">
                                <img v-if="conversation.person" :src="conversation.person?.photo"
                                     :alt="conversation.person.full_name"/>
                            </section>
                            <section class="fs_thread_wrap">
                                <section class="fs_thread_message">
                                    <div class="fs_thread_head">
                                        <div class="fs_thread_title">
                                            <strong v-if="conversation.person">{{
                                                    getHumanName(conversation.person)
                                                }}</strong>&nbsp;
                                            <span v-if="conversation.conversation_type == 'response'"> {{
                                                    $t('replied')
                                                }}</span>
                                            <span v-else-if="conversation.conversation_type == 'note'"> {{
                                                    $t('added a note')
                                                }}</span>
                                        </div>
                                        <div class="fs_thread_actions">
                                            <span style="margin-right: 5px" v-if="conversation.source == 'email'"
                                                  :title="$t('Added By Email')"><el-icon style="vertical-align: middle;"><message /></el-icon></span>
                                            <span :title="conversation.created_at">{{
                                                    $timeDiff(conversation.created_at)
                                                }}</span>
                                            <el-dropdown @command="handleResponseActionCommand" trigger="click">
                                                <span class="el-dropdown-link">
                                                    <el-icon><arrow-down /></el-icon>
                                                </span>
                                                <template #dropdown>
                                                    <el-dropdown-menu>
                                                        <el-dropdown-item
                                                            :command="{ type: 'edit', conversation: conversation }"
                                                            icon="EditPen"> {{$t('Edit')}}
                                                        </el-dropdown-item>
                                                        <el-dropdown-item
                                                            :command="{ type: 'delete', conversation: conversation }"
                                                            icon="Delete"> {{$t('Delete')}}
                                                        </el-dropdown-item>
                                                    </el-dropdown-menu>
                                                </template>
                                            </el-dropdown>
                                        </div>
                                    </div>
                                    <div v-html="santizeContent(conversation.content)" class="fs_thread_body"></div>

                                    <div class="fst_file_lists" v-if="conversation.attachments?.length">
                                        <hr/>
                                        <ul>
                                            <li
                                                v-for="attachment in conversation.attachments"
                                                :key="attachment.file_hash"
                                            >
                                                <el-icon style="vertical-align: middle;"><paperclip /></el-icon> <a target="_blank" rel="noopener"
                                                                                     :href="attachment.secureUrl">{{
                                                    attachment.title
                                                }}</a>
                                            </li>
                                        </ul>
                                    </div>
                                </section>
                            </section>
                        </div>
                    </article>
                    <article class="fs_thread conversion_starter">
                        <div class="fs_thread_content">
                            <section class="fs_avatar">
                                <img :src="ticket.customer?.photo" :alt="ticket.customer?.full_name"/>
                            </section>
                            <section class="fs_thread_wrap">
                                <section class="fs_thread_message">
                                    <div class="fs_thread_head">
                                        <div class="fs_thread_title">
                                            <strong>{{ ticket.customer?.full_name }}</strong> {{$t('started the conversation')}}
                                        </div>
                                        <div class="fs_thread_actions">
                                            <span style="margin-right: 5px" v-if="ticket.source == 'email'"
                                                  :title="$t('Added By Email')"><el-icon style="vertical-align: middle;"><message /></el-icon></span>
                                            <span :title="ticket.created_at">{{ $timeDiff(ticket.created_at) }}</span>
                                        </div>
                                    </div>
                                    <div v-html="santizeContent(ticket.content)" class="fs_thread_body"></div>

                                    <div class="fst_file_lists" v-if="ticket.attachments && ticket.attachments.length">
                                        <ul>
                                            <li v-if="ticket.attachments.length"
                                                v-for="attachment in ticket.attachments"
                                                :key="attachment.file_hash"
                                            >
                                                <el-icon style="vertical-align: middle;"><paperclip /></el-icon> <a target="_blank" rel="noopener"
                                                                                     :href="attachment.secureUrl">{{
                                                    attachment.title
                                                }}</a>
                                            </li>
                                        </ul>
                                    </div>
                                </section>
                            </section>
                            <div v-if="has_pro && !isEmpty(appVars.custom_fields)" class="fc_custom_data_wrap">
                                <h3>{{ $t('Additional Data') }}
                                    <el-button @click="showCustomDataEditForm = !showCustomDataEditForm" type="text"
                                               icon="EditPen" size="small"></el-button>
                                </h3>
                                <ul v-if="!isEmpty(ticket.custom_fields)">
                                    <li v-for="(fieldValue, fieldName) in ticket.custom_fields" :key="fieldName">
                                        <b>{{ appVars.custom_fields[fieldName].label }}</b> :
                                        <span v-if="isArray(fieldValue)">
                                            <span class="fs_custom_check_value" v-for="value in fieldValue"
                                                  :key="value">{{ value }}</span>
                                        </span>
                                        <span v-else v-html="fieldValue"></span>
                                    </li>
                                </ul>
                                <p v-else>{{$t('No additional data found')}}</p>
                            </div>
                        </div>
                        <el-dialog
                            :title="$t('Updating Custom Field Data')"
                            v-model="showCustomDataEditForm"
                            v-if="showCustomDataEditForm"
                            width="60%">
                            <custom-field-form @syncData="syncCustomData" :ticket_id="ticket_id"
                                               :custom_data="ticket.custom_fields"/>
                        </el-dialog>

                    </article>
                </div>
            </div>
            <div class="fs_ticket_sidebar">
                <ticket-sidebar :fluentcrm_profile="fluentcrm_profile" :ticket_id="ticket_id" :ticket="ticket" :watchers="watchers" @refresh="fetchTicket"/>
            </div>
        </template>
        <template v-else>
            <div class="fs_ticket_body">
                <div style="padding: 15px;">
                    <el-skeleton :rows="10" animated/>
                </div>
            </div>
            <div style="margin-left: 20px;" class="fs_ticket_sidebar">
                <el-skeleton style="background: white;padding: 20px; margin-bottom: 20px; box-sizing: border-box;"
                             :rows="3" animated/>
                <el-skeleton style="background: white;padding: 20px; margin-bottom: 20px; box-sizing: border-box;"
                             :rows="3" animated/>
                <el-skeleton style="background: white;padding: 20px; margin-bottom: 20px; box-sizing: border-box;"
                             :rows="3" animated/>
            </div>
        </template>

        <modal :show="edit_response_modal" @close="edit_response_modal=false" :title="$t('Edit Response')">
            <template #body>
                <edit-response @updated="edit_response_modal = false; editing_response = false" v-if="editing_response"
                               :response="editing_response"/>
            </template>
        </modal>

        <active-agents :ticket="ticket" v-if="ticket && ticket.id"/>
    </div>
</template>

<script type="text/babel">
import CreateResponse from './_CreateResponse';
import EditResponse from './_EditResponse';
import TicketSidebar from './_TicketSidebar';
import each from 'lodash/each';
import isEmpty from 'lodash/isEmpty';
import isArray from 'lodash/isArray';
import ActiveAgents from './_active_agents';
import TicketTags from './parts/_Tags';
import CustomFieldForm from './parts/_CustomFieldForm';
import WorkFlowSelector from './parts/_WorkFlowSelector';
import Pagination from "../../Pieces/Pagination";
import Modal from "../../Pieces/Modal";

export default {
    name: 'ViewTicket',
    props: ['ticket_id'],
    components: {
        Modal,
        Pagination,
        CreateResponse,
        TicketSidebar,
        EditResponse,
        ActiveAgents,
        TicketTags,
        CustomFieldForm,
        WorkFlowSelector,
    },
    data() {
        return {
            loading: false,
            ticket: false,
            conversations: [],
            show_response_box: '',
            products: this.appVars.support_products,
            agents: this.appVars.support_agents,
            admin_priorities: this.appVars.admin_priorities,
            client_priorities: this.appVars.client_priorities,
            updating: false,
            active_agents: [],
            edit_response_modal: false,
            editing_response: false,
            showCustomDataEditForm: false,
            fluentcrm_profile: false,
            mailboxes: this.appVars.mailboxes,
            customer_tickets: [],
            show_merge_modal: false,
            show_watcher_modal: false,
            watchers: [],
            pagination: {
                current_page: 1,
                total: 0,
                per_page: 10
            },
            conversation_type: '',
            filteredWatchers: []
        }
    },
    watch: {
        '$route.params.ticket_id'(ticketId) {
            if (ticketId) {
                this.doAction('ticket_view_exit', this.ticket.id);
                this.ticket = false;
                this.$nextTick(() => {
                    this.doAction('ticket_view_entered', ticketId);
                    this.fetchTicket();
                });
            }
        }
    },
    methods: {
        fetchTicket() {
            this.loading = true;
            this.$get(`tickets/${this.ticket_id}`, {
                with_data: ['fluentcrm_profile']
            })
            .then(response => {
                let that = this;
                this.ticket = response.ticket;

                this.$setTitle(response.ticket.title);
                this.conversations = response.responses;
                if (this.appVars.fluentcrm_config) {
                    this.fluentcrm_profile = response.fluentcrm_profile;
                }
                this.watchers = response.watchers;
                this.filteredWatchers = response.watchers.map((watcher, key) => {
                    return watcher.id.toString();
                });
            })
            .catch((errors) => {
                this.$handleError(errors);
            })
            .always(() => {
                this.loading = false;
            });
        },
        getTicketClasses(conversation) {
            const classes = [
                'fs_thread'
            ];

            if (conversation.person) {
                classes.push('fs_person_' + conversation.person.person_type);
            }

            classes.push('fs_conv_type_' + conversation.conversation_type);
            return classes;
        },
        recordNewResponse(response, data) {
            this.conversations.unshift(response);
            this.ticket.status = data.ticket.status;
            this.show_response_box = false;

            each(data.update_data, (data, key) => {
                this.ticket[key] = data;
            });

            if (this.appVars.pref.go_back_after_reply == 'yes') {
                if (window.history.state.back) {
                    this.$router.go(-1);
                }
            }
        },
        updateTicketAttr(propName) {
            this.$put(`tickets/${this.ticket.id}/property`, {
                prop_name: propName,
                prop_value: this.ticket[propName]
            })
                .then(response => {
                    this.$notify.success({
                        message: response.message,
                        position: 'bottom-right'
                    });

                    each(response.update_data, (data, key) => {
                        this.ticket[key] = data;
                    });
                    this.fetchTicket();
                })
                .catch((errors) => {
                    this.$handleError(errors);
                });
        },
        getHumanName(person) {
            if (this.appVars.me?.id == person.id) {
                return this.$t('You');
            }

            return person.full_name;
        },
        closeTicket() {
            this.updating = true;
            this.$post(`tickets/${this.ticket.id}/close`)
                .then(response => {
                    this.ticket.status = response.ticket.status;
                    this.$notify.success({
                        message: response.message,
                        position: 'bottom-right'
                    });
                    if (window.history.state.back) {
                        this.$router.go(-1);
                    }
                })
                .catch((errors) => {
                    console.log(errors);
                })
                .always(() => {
                    this.updating = false;
                });
        },
        reOpen() {
            this.updating = true;
            this.$post(`tickets/${this.ticket.id}/re-open`)
                .then(response => {
                    this.ticket.status = response.ticket.status;
                })
                .catch((errors) => {
                    console.log(errors);
                })
                .always(() => {
                    this.updating = false;
                });
        },
        onActivityChange(items) {
            const personIds = [];
            items.forEach((item) => {
                personIds.push(item.val());
            });
            this.active_agents = personIds;
        },
        onTicketDataChange(item) {
            let data = item.val();
            this.ticket[data.type] = data.data;
        },
        handleResponseActionCommand(data) {
            const actionType = data.type;
            const conversation = data.conversation;

            if (actionType == 'delete') {
                this.$confirm(this.$t('response_delete_warning'), 'Warning', {
                    confirmButtonText: this.$t('Delete Response'),
                    cancelButtonText: this.$t('Cancel'),
                    type: 'warning'
                }).then(() => {
                    this.$del(`tickets/${this.ticket.id}/responses/${conversation.id}`)
                        .then(response => {
                            this.$notify.success({
                                message: response.message,
                                position: 'bottom-right',
                                type: 'success'
                            });
                            this.fetchTicket();
                        })
                });
            } else if (actionType == 'edit') {
                if (this.ticket.status == 'closed') {
                    this.$notify({
                        type: 'error',
                        message: this.$t('error_msg_on_closed_ticket_edit'),
                        position: 'bottom-right'
                    });
                    return false;
                }
                this.editing_response = conversation;
                this.edit_response_modal = true;
                this.conversation_type = conversation.conversation_type;
            }
        },
        changeMailbox(mailbox) {
            this.loading != this.loading;
            this.$put(`mailboxes/${this.ticket.mailbox_id}/move_tickets`, {
                new_box_id: mailbox,
                ticket_ids: [this.ticket_id],
                move_type: 'Custom',
            })
                .then(response => {
                    this.$notify.success({
                        message: response.message,
                        position: 'bottom-right'
                    });
                    this.fetchTicket();
                })
                .catch((error) => {
                    this.$handleError(error);
                })
                .always(() => {
                    this.loading = false;
                });
        },
        customerTickets(){
            this.$get('tickets/customer_tickets/' + this.ticket.customer_id, {
                exclude_ticket_id: this.ticket_id,
                page: this.pagination.current_page,
                per_page: this.pagination.per_page,
            })
                .then(response => {
                    this.customer_tickets = response.tickets.data;
                    this.pagination.total = response.tickets.total;
                })
                .catch((errors) => {
                    this.$handleError(errors);
                })
                .always(() => {
                    this.loading = false;
                });
        },
        mergeTickets(ticketToMerge){
            this.$confirm('Are you sure you want to merge these tickets?', 'Merge Tickets', {
                confirmButtonText: 'Merge',
                cancelButtonText: 'Cancel',
                type: 'warning'
            }).then(() => {
                this.loading = true;
                this.$post('tickets/' + this.ticket_id +'/merge_tickets', {
                    ticket_to_merge: ticketToMerge,
                })
                    .then(response => {
                        this.$notify.success({
                            message: response.message,
                            position: 'bottom-right'
                        });
                        this.customerTickets();
                        this.fetchTicket();
                    })
                    .catch((error) => {
                        this.$handleError(error);
                    })
                    .always(() => {
                        this.loading = false;
                    });
            });
        },
        santizeContent(content) {
            if (!content) {
                return content;
            }
            return content.replace(/\n\s*\n/g, '\n').replace(/\n\s*\n/g, '\n');
        },
        isEmpty,
        isArray,
        syncCustomData(data) {
            this.ticket.custom_fields = data;
            this.showCustomDataEditForm = false;
        },
        addWatchers() {
            this.saving = true;
            this.$post(`tickets/${this.ticket.id}/add_watchers`, {
                watchers: this.watchers
            })
                .then(response => {
                    this.$notify.success({
                        message: response.message,
                        position: 'bottom-right'
                    });
                    this.fetchTicket()
                    this.show_watcher_modal = false;
                })
                .catch((errors) => {
                    this.$handleError(errors);
                })
                .always(() => {
                    this.saving = false;
                });
        },
        updateWatcher(){
            this.saving = true;
            this.$post(`tickets/${this.ticket.id}/sync-watchers`, {
                watchers: this.filteredWatchers
            })
                .then(response => {
                    this.$notify.success({
                        message: response.message,
                        position: 'bottom-right'
                    });

                    this.fetchTicket()

                    this.show_watcher_modal = false;
                })
                .catch((errors) => {
                    this.$handleError(errors);
                })
                .always(() => {
                    this.saving = false;
                });
        }
    },
    mounted() {
        this.fetchTicket();
        this.doAction('ticket_view_entered', this.ticket_id, this);
    },
    beforeUnmount() {
        this.doAction('ticket_view_exit', this.ticket_id);
    }
}
</script>

<style scoped>
.agent_title {
    content: '';
    position: relative;
    left: 0;
    top: 0;
    background: #1785EB;
    color: #fff;
    padding: 5px 10px;
    font-size: 11px;
}

.fs_agent {
    border-left: 4px solid #1785EB;
}

.fs_conv_type_note {
    border-left: 0px solid #e6a23c;
}
i.dashicons.dashicons-randomize {
    transform: rotate(90deg);
}
</style>
