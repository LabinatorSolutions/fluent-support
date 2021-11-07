<template>
    <div class="activities fs_box fs_box_wrapper">
        <div v-if="!loading" class="fs_box_header" style="font-size: 16px;">
            <div class="fs_box_head">
                Overall Activities
            </div>
            <div class="fs_box_actions">
                <el-button @click="showSettingsModal = true" size="small" type="default" icon="el-icon-setting"></el-button>
            </div>
        </div>
        <div class="fs_box fs_activity_box" v-if="activities">
            <div class="fs_box_body">
                <template v-if="!loading">
                    <ul>
                        <li v-for="activity in activities" @click.prevent="handleClick(activity, $event)" :key="activity.id"
                            :class="activity.person_type=='agent' ? 'fs_agent_activity' : 'fs_customer_activity'">
                            <div class="fs_activity">
                                <div class="fs_activity_avatar">
                                    <img v-if="activity.person" :src="activity.person?.photo"
                                         :alt="activity.person.full_name"/>
                                </div>
                                <div class="fs_activity_info">
                                    <span v-html="activity.description"></span> <br/>
                                    <samp>{{ $timeDiff(activity.created_at) }}</samp>
                                </div>
                            </div>
                        </li>
                    </ul>
                </template>
                <div class="fs_padded_20" v-else>
                    <el-skeleton :rows="3" animated/>
                </div>
            </div>
        </div>

        <el-dialog
            v-model="showSettingsModal"
            title="Activity Log Settings"
            width="60%"
            :append-to-body="true"
        >
            <activity-settings @updated="showSettingsModal = false" />
        </el-dialog>
    </div>
</template>

<script type="text/babel">

import ActivitySettings from './_ActivitySettings';

export default {
    name: "ActivityLogger",
    components: {
        ActivitySettings
    },
    data() {
        return {
            activities: [],
            loading: false,
            me: this.appVars.me,
            showSettingsModal: false
        }
    },
    methods: {
        fetchActivities() {
            this.loading = !this.loading;
            this.$get('activity-logger')
                .then(response => {
                    this.activities = response.activities;
                })
                .catch(error => {
                    this.$handleError(error)
                })
                .always(() => {
                    this.loading = !this.loading;
                })
        },
        handleClick(activity, $event) {
            const route = $event.target.getAttribute('href');

            if(!route) {
                return false;
            }



            if(route == '#view_ticket') {
                this.$router.push({ name: 'view_ticket', params: {ticket_id: activity.object_id }});
            }

        }
    },
    mounted() {
        this.fetchActivities();
        this.$setTitle('Activities');
    }
}
</script>
