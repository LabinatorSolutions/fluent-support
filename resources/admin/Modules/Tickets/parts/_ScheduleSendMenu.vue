<template>
    <el-popover
        v-model:visible="visible"
        placement="top-end"
        :width="step === 'form' ? 440 : (blocked ? 300 : 288)"
        :show-arrow="false"
        :offset="8"
        trigger="click"
        :disabled="busy"
        :popper-class="'fs_schedule_popover fs_schedule_popover_' + step"
        @after-leave="step = 'menu'"
    >
        <template #reference>
            <el-button class="fs_reply_btn fs_filled_btn fs_reply_split_toggle"
                       :aria-label="$t('More send options')"
                       :aria-expanded="visible ? 'true' : 'false'"
                       :disabled="disabled">
                <el-icon><ArrowDown /></el-icon>
            </el-button>
        </template>

        <div v-if="step === 'menu' && blocked && !pending" class="fs_schedule_blocked" role="status">
            <div class="fs_schedule_blocked_head">
                <span class="fs_schedule_blocked_icon"><el-icon><Clock /></el-icon></span>
                <span class="fs_schedule_blocked_title">
                    <strong>{{ $t('Reply already scheduled') }}</strong>
                    <span>{{ blockedTime }}</span>
                </span>
            </div>
            <p>{{ $t('A ticket can have one scheduled reply. Edit or cancel it to schedule this one.') }}</p>
            <el-button v-if="canEdit" class="fs_outline_btn" @click="editPending">
                <el-icon><EditPen /></el-icon>
                {{ $t('Edit scheduled reply') }}
            </el-button>
        </div>

        <div v-else-if="step === 'menu'" class="fs_schedule_menu">
            <el-button v-if="pending" text class="fs_schedule_menu_item" @click="openForm(pending.schedule?.close_ticket === 'yes')">
                <el-icon><Clock /></el-icon>
                <span class="fs_schedule_menu_text">
                    <strong>{{ $t('Reschedule…') }}</strong>
                    <small>{{ currentLabel }}</small>
                </span>
            </el-button>
            <el-button v-else text class="fs_schedule_menu_item" @click="openForm(false)">
                <el-icon><Clock /></el-icon>
                <span class="fs_schedule_menu_text">
                    <strong>{{ $t('Schedule reply…') }}<span v-if="mode === 'upsell'" class="fs_pro_badge">{{ $t('PRO') }}</span></strong>
                    <small>{{ $t('Send it later at a time you pick') }}</small>
                </span>
            </el-button>
        </div>

        <div v-else class="fs_schedule_form" :class="{ fs_schedule_form_preview: mode === 'upsell' }">
            <div class="fs_schedule_form_header">
                <el-button text circle class="fs_schedule_back" :aria-label="$t('Back')" @click="step = 'menu'">
                    <el-icon><ArrowLeft /></el-icon>
                </el-button>
                <span class="fs_schedule_form_title">{{ $t('Scheduling options') }}</span>
                <span v-if="mode === 'upsell'" class="fs_pro_badge">{{ $t('PRO') }}</span>
            </div>
            <div class="fs_schedule_form_body">
                <p v-if="mode === 'upsell'" class="fs_schedule_form_desc">{{ $t('Write a reply now and have it sent at the date and time you pick.') }}</p>
                <div v-else-if="needsText" class="fs_schedule_alert" role="status">
                    <el-icon><WarningFilled /></el-icon>
                    <span>{{ $t('Write a reply before you send or schedule it.') }}</span>
                </div>
                <p v-else class="fs_schedule_form_desc">{{ $t('Choose a date and time in the future when you want this reply to be sent.') }}</p>
                <div class="fs_schedule_fields">
                    <div class="fs_schedule_tile">
                        <el-icon><Calendar /></el-icon>
                        <span class="fs_schedule_tile_body">
                            <span class="fs_schedule_tile_label">{{ $t('Date') }}</span>
                            <span class="fs_schedule_tile_value">{{ dateLabel }}</span>
                        </span>
                        <el-date-picker
                            v-model="date"
                            class="fs_schedule_tile_picker"
                            type="date"
                            value-format="YYYY-MM-DD"
                            :clearable="false"
                            :teleported="false"
                            :disabled="mode === 'upsell'"
                            :disabled-date="isPastDate"
                        />
                    </div>
                    <div class="fs_schedule_tile">
                        <el-icon><Clock /></el-icon>
                        <span class="fs_schedule_tile_body">
                            <span class="fs_schedule_tile_label">{{ $t('Time') }}</span>
                            <span class="fs_schedule_tile_value">{{ time }}</span>
                        </span>
                        <el-time-select
                            v-model="time"
                            class="fs_schedule_tile_picker"
                            start="00:00"
                            step="00:15"
                            end="23:45"
                            format="h:mm A"
                            :clearable="false"
                            :teleported="false"
                            :disabled="mode === 'upsell'"
                        />
                    </div>
                </div>
                <!-- translators: %s is the picked send time in the agent's own time zone -->
                <p v-if="localTimeLabel" class="fs_schedule_tz">{{ $t('Times are in UTC. Your local time: %s', localTimeLabel) }}</p>
                <p v-else class="fs_schedule_tz">{{ $t('Pick the date and time in UTC, not your local time.') }}</p>
                <p v-if="error" class="fs_schedule_error">{{ error }}</p>
                <el-checkbox v-model="closeTicket" :disabled="mode === 'upsell'">{{ $t('Close ticket after it sends') }}</el-checkbox>
                <NarrowPromo
                    v-if="mode === 'upsell'"
                    custom-class="fs_schedule_upsell"
                    :description="$t('Scheduled replies are part of Fluent Support Pro. Upgrade to send replies at a time that suits your customer.')"
                    :button-text="$t('Upgrade to Pro')"
                    utm-content="feature_lock_scheduled_replies"
                />
                <el-button v-else class="fs_filled_btn fs_schedule_submit" :loading="busy" :disabled="needsText || disabled" @click="submit">
                    {{ $t('Schedule for later') }}
                </el-button>
            </div>
        </div>
    </el-popover>
</template>

<script type="text/babel">
import dayjs from 'dayjs';
import NarrowPromo from '@/admin/Components/NarrowPromo.vue';
import { formatUtcTime } from '@/common/dateTime';

/**
 * The ▾ next to Reply: schedule a new reply, or reschedule the one being edited ('pro'),
 * or a preview with an upgrade link ('upsell'). Emits the picked unix time.
 * A ticket holds one scheduled reply: with `blocked` (the pending one) it shows that reply instead.
 */
export default {
    name: 'ScheduleSendMenu',
    components: { NarrowPromo },
    props: ['mode', 'pending', 'blocked', 'canEdit', 'needsText', 'busy', 'disabled'],
    emits: ['submit', 'form-open', 'edit-pending'],
    data() {
        return {
            visible: false,
            step: 'menu',
            date: '',
            time: '9:00 AM',
            closeTicket: false,
            error: ''
        };
    },
    computed: {
        currentLabel() {
            // translators: %s is the date and time the reply will be sent
            return this.$t('Currently %s', formatUtcTime(this.pending?.schedule?.scheduled_at, true));
        },
        blockedTime() {
            // translators: %s is the date and time the scheduled reply will be sent
            return this.$t('Sends %s', formatUtcTime(this.blocked?.schedule?.scheduled_at));
        },
        dateLabel() {
            return this.date ? dayjs(this.date).format('MMM D, YYYY') : '';
        },
        pickedAt() {
            const time = /^(\d{1,2}):(\d{2})\s*(AM|PM)$/i.exec(this.time || '');
            if (!time || !this.date) {
                return 0;
            }
            const hours = (Number(time[1]) % 12) + (time[3].toUpperCase() === 'PM' ? 12 : 0);
            return dayjs.utc(this.date).hour(hours).minute(Number(time[2])).second(0).unix();
        },
        localTimeLabel() {
            if (!this.pickedAt || dayjs().utcOffset() === 0) {
                return '';
            }
            return dayjs.unix(this.pickedAt).format('ddd, MMM D, h:mm A');
        },
        formOpen() {
            return this.visible && this.step === 'form';
        }
    },
    watch: {
        formOpen(open) {
            this.$emit('form-open', open);
        }
    },
    methods: {
        // The picker hands over local-midnight dates, so compare calendar days against today in UTC
        isPastDate(date) {
            return dayjs(date).format('YYYY-MM-DD') < dayjs.utc().format('YYYY-MM-DD');
        },

        openForm(closeTicket) {
            const current = this.pending?.schedule?.scheduled_at;
            let start = dayjs.utc().add(1, 'day').hour(9).minute(0);

            if (current && dayjs.unix(current).isAfter(dayjs())) {
                const time = dayjs.unix(current).utc();
                start = time.minute(Math.ceil(time.minute() / 15) * 15);
            }

            this.date = start.format('YYYY-MM-DD');
            this.time = start.format('h:mm A');
            this.closeTicket = !!closeTicket;
            this.error = '';
            this.step = 'form';
        },

        submit() {
            const timestamp = this.pickedAt;

            if (!timestamp || timestamp <= dayjs().add(1, 'minute').unix()) {
                this.error = this.$t('Please pick a time at least a minute from now.');
                return;
            }

            this.$emit('submit', timestamp, this.closeTicket);
        },

        editPending() {
            this.visible = false;
            this.$emit('edit-pending');
        },

        close() {
            this.visible = false;
        }
    }
};
</script>
