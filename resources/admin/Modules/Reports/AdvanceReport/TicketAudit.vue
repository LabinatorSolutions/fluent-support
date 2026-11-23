<template>
    <div class="fs_ticket_audit_page">
        <!-- Header -->
        <div class="fs_ticket_audit_header">
            <div class="fs_ticket_audit_header_row">
                <h3 class="fs_page_title">{{ $t("AI Ticket Audit") }}</h3>
            </div>
            <div v-if="has_pro" class="fs_ticket_audit_header_row fs_ticket_audit_toolbar">
                <div class="fs_toolbar_left">
                    <div class="fs_date_button_group">
                        <div class="fs_date_button_group_item fs_date_picker_wrapper">
                            <div class="fs_date_display">
                                <IconPack icon-key="calendar" :width="20" :height="20" class="fs_calendar_icon" />
                                <span v-if="formattedDateRange" class="fs_date_text">{{ formattedDateRange }}</span>
                                <span v-else class="fs_date_placeholder">{{ $t('Select date range') }}</span>
                            </div>
                            <el-date-picker
                                v-model="localDateRange"
                                type="daterange"
                                :editable="false"
                                :disabled="runState === 'running'"
                                @change="handleDateChange"
                                range-separator="To"
                                :disabled-date="disabledDate"
                                value-format="YYYY-MM-DD"
                                :start-placeholder="$t('Start date')"
                                :end-placeholder="$t('End date')"
                                :shortcuts="shortcuts"
                                class="fs_date_range_picker"
                            />
                        </div>
                    </div>
                </div>
                <div class="fs_toolbar_right">
                    <el-button
                        class="fs_filled_btn"
                        type="primary"
                        :loading="runState === 'running'"
                        :disabled="runState === 'running'"
                        @click="runAudit"
                    >
                        {{ runState === 'running' ? $t('Running…') : $t('Run Audit') }}
                    </el-button>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div v-if="has_pro" class="fs_ticket_audit_content" v-loading="loading">
            <div class="fs_page_error" v-if="fetchError">
                <p>{{ $t("Couldn't load this data.") }}</p>
                <el-button size="small" @click="fetchResults">{{ $t("Retry") }}</el-button>
            </div>

            <template v-else>
                <!-- Idle -->
                <div class="fs_audit_empty_state" v-if="runState === 'idle'">
                    <span class="fs_audit_empty_icon">
                        <IconPack icon-key="ai_ticket_audit" :width="22" :height="22" />
                    </span>
                    <h4>{{ $t('No audit for this range yet') }}</h4>
                    <p>{{ $t('Ticket_Audit_Empty_Help') }}</p>
                    <el-button class="fs_filled_btn" type="primary" @click="runAudit">{{ $t('Run Audit') }}</el-button>
                </div>

                <!-- Running -->
                <div class="fs_audit_run_card" v-if="runState === 'running'">
                    <div class="fs_audit_run_head">
                        <h4><span class="fs_audit_spinner"></span> {{ $t('Auditing tickets…') }}</h4>
                    </div>
                    <div class="fs_audit_progress_track">
                        <div class="fs_audit_progress_fill" :style="{ width: progressPercent + '%' }"></div>
                    </div>
                    <div class="fs_audit_run_meta">
                        <!-- translators: %1$s is the number of tickets audited so far, %2$s the total number of tickets in the run -->
                        <span>{{ $t('%1$s of %2$s tickets processed', processedCount, totalCandidates) }}</span>
                        <span>{{ progressPercent }}% {{ $t('complete') }}</span>
                    </div>
                    <div class="fs_audit_run_ticker" v-if="recentAudited.length">
                        <div class="fs_audit_ticker_row" v-for="item in recentAudited" :key="item.ticket_id">
                            <span class="fs_dist_dot" :class="'fs_mood_dot--' + moodKey(item.mood)"></span>
                            <span>
                                #{{ item.ticket_id }} “{{ item.title }}” &rarr;
                                <b>{{ item.status === 'failed' ? $t('Failed') : item.mood }}</b>
                                <!-- translators: %s is the sentiment score the AI audit gave the ticket -->
                                <template v-if="item.status !== 'failed'">, {{ $t('score %s', item.score) }}</template>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Done -->
                <template v-if="runState === 'done'">
                    <div class="fs_stat_cards_grid">
                        <div class="fs_stat_card_tile">
                            <span class="fs_stat_card_icon fs_stat_card_icon--information">
                                <IconPack icon-key="ai_ticket_audit" :width="20" :height="20" />
                            </span>
                            <div class="fs_stat_card_text">
                                <p class="fs_stat_label">{{ $t('Tickets audited') }}</p>
                                <span class="fs_stat_value">{{ summary.total }}</span>
                            </div>
                        </div>
                        <div class="fs_stat_card_tile">
                            <span class="fs_stat_card_icon fs_stat_card_icon--feature">
                                <IconPack icon-key="active_agents" :width="20" :height="20" />
                            </span>
                            <div class="fs_stat_card_text">
                                <p class="fs_stat_label">{{ $t('Completed successfully') }}</p>
                                <span class="fs_stat_value">{{ summary.success }}</span>
                            </div>
                        </div>
                        <div class="fs_stat_card_tile">
                            <span class="fs_stat_card_icon fs_stat_card_icon--warning">
                                <IconPack icon-key="error_warning_line" :width="20" :height="20" />
                            </span>
                            <div class="fs_stat_card_text">
                                <p class="fs_stat_label">{{ $t('Failed') }}</p>
                                <span class="fs_stat_value">{{ summary.failed }}</span>
                            </div>
                        </div>
                        <div class="fs_stat_card_tile">
                            <span class="fs_stat_card_icon fs_stat_card_icon--away">
                                <IconPack icon-key="warning" :width="20" :height="20" />
                            </span>
                            <div class="fs_stat_card_text">
                                <p class="fs_stat_label">{{ $t('Avg. Sentiment Score') }}</p>
                                <span class="fs_stat_value">{{ summary.average_score }}</span>
                            </div>
                        </div>
                        <div class="fs_stat_card_tile">
                            <span class="fs_stat_card_icon fs_stat_card_icon--highlighted">
                                <IconPack icon-key="alert_fill" :width="20" :height="20" />
                            </span>
                            <div class="fs_stat_card_text">
                                <p class="fs_stat_label">{{ $t('Need attention') }}</p>
                                <span class="fs_stat_value">{{ summary.needs_attention }}</span>
                            </div>
                        </div>
                    </div>

                    <template v-if="summary.total">
                        <div class="fs_audit_split_row">
                            <div class="fs_overview_section">
                                <div class="fs_section_header">
                                    <h4 class="fs_section_title">{{ $t('Sentiment distribution') }}</h4>
                                </div>
                                <div class="fs_section_body">
                                    <div class="fs_dist_row" v-for="mood in moods" :key="mood">
                                        <span class="fs_dist_label">
                                            <span class="fs_dist_dot" :class="'fs_mood_dot--' + moodKey(mood)"></span>
                                            {{ $t(mood) }}
                                        </span>
                                        <div class="fs_dist_track">
                                            <div
                                                class="fs_dist_fill"
                                                :class="'fs_mood_fill--' + moodKey(mood)"
                                                :style="{ width: distPercent(mood) + '%' }"
                                            ></div>
                                        </div>
                                        <span class="fs_dist_count">{{ distribution[mood] || 0 }}</span>
                                    </div>
                                </div>
                            </div>

                            <div class="fs_overview_section">
                                <div class="fs_section_header">
                                    <h4 class="fs_section_title">{{ $t('Needs attention first') }}</h4>
                                </div>
                                <div class="fs_section_body">
                                    <div
                                        class="fs_attention_item"
                                        v-for="row in needsAttentionRows"
                                        :key="row.ticket_id"
                                        @click="goToTicket(row.ticket_id)"
                                    >
                                        <span class="fs_dist_dot" :class="'fs_mood_dot--' + moodKey(row.mood)"></span>
                                        <span class="fs_att_title">#{{ row.ticket_id }} {{ row.title }}</span>
                                        <span class="fs_att_score">{{ row.score }}</span>
                                    </div>
                                    <p class="fs_section_empty" v-if="!needsAttentionRows.length">
                                        {{ $t('No tickets need attention in this range.') }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="fs_overview_section">
                            <div class="fs_section_header fs_audit_table_header">
                                <div class="fs_status_tabs">
                                    <div class="fs_segmented_control">
                                        <button
                                            v-for="chip in moodChips"
                                            :key="chip.key"
                                            class="fs_segment_button"
                                            :class="{ 'fs_segment_active': activeMoodFilter === chip.key }"
                                            @click="selectMoodFilter(chip.key)"
                                        >
                                            <span
                                                class="fs_dist_dot"
                                                :class="'fs_mood_dot--' + moodKey(chip.key)"
                                                v-if="chip.key !== 'all' && chip.key !== 'failed'"
                                            ></span>
                                            {{ chip.label }} ({{ chip.count }})
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div
                                class="fs_table_wrap"
                                :class="{ 'is-scrolled': tableScrolled }"
                                @scroll="onTableScroll"
                            >
                                <table class="fs_data_table">
                                    <thead>
                                        <tr>
                                            <th>{{ $t("Ticket") }}</th>
                                            <th>{{ $t("Product") }}</th>
                                            <th>{{ $t("Agent") }}</th>
                                            <th>{{ $t("Mood") }}</th>
                                            <th>{{ $t("Score") }}</th>
                                            <th>{{ $t("AI Summary") }}</th>
                                            <th>{{ $t("Audited") }}</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="row in rows" :key="row.ticket_id">
                                            <td class="fs_ticket_cell">
                                                <div class="fs_ticket_cell_inner">
                                                    <span class="fs_ticket_id_muted">#{{ row.ticket_id }}</span>
                                                    <a href="javascript:void(0)" class="fs_ticket_link" :title="row.title" @click="goToTicket(row.ticket_id)">{{ row.title }}</a>
                                                    <span class="fs_stale_badge" v-if="row.stale">{{ $t('Updated since audit') }}</span>
                                                </div>
                                            </td>
                                            <td>{{ row.product || '—' }}</td>
                                            <td>{{ row.agent || '—' }}</td>
                                            <td>
                                                <span v-if="row.status === 'failed'" class="fs_fail_tag">{{ $t('Failed') }}</span>
                                                <span v-else class="fs_mood_badge" :class="'fs_mood_badge--' + moodKey(row.mood)">{{ row.mood }}</span>
                                            </td>
                                            <td>
                                                <template v-if="row.score !== null && row.score !== undefined">{{ row.score }}<span class="fs_score_scale">/10</span></template>
                                                <template v-else>—</template>
                                            </td>
                                            <td class="fs_summary_cell">{{ row.summary || '—' }}</td>
                                            <td>{{ formatDate(row.audited_at) }}</td>
                                            <td>
                                                <el-button class="fs_outline_btn" size="small" v-if="row.status !== 'failed'" @click="openEscalate(row)">
                                                    {{ $t('Escalate') }}
                                                </el-button>
                                            </td>
                                        </tr>
                                        <tr v-if="!rows.length">
                                            <td colspan="8">
                                                <p class="fs_section_empty">{{ $t('No Data') }}</p>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <div class="fs_pagination_wrapper" v-if="rows.length">
                                <span class="fs_pagination_left">
                                    <!-- translators: %1$s is the current page number, %2$s the total number of pages -->
                                    <p>{{ $t('Page %1$s of %2$s', pagination.current_page, Math.ceil(pagination.total / pagination.per_page)) }}</p>
                                    <pagination
                                        @fetch="fetchResults(false)"
                                        :pagination="pagination"
                                        layout="sizes"
                                    />
                                </span>
                                <span class="fs_pagination_right">
                                    <pagination
                                        @fetch="fetchResults(false)"
                                        :pagination="pagination"
                                        :background="true"
                                        layout="prev, pager, next"
                                    />
                                </span>
                            </div>
                        </div>
                    </template>

                    <div class="fs_audit_empty_state" v-else>
                        <p>{{ $t('No tickets found for this range.') }}</p>
                    </div>
                </template>
            </template>
        </div>

        <!-- Shown instead of the report when Pro is not active -->
        <div v-else class="fs_report_pro_showcase">
            <ProShowcase
                :feature="$t('AI Ticket Audit')"
                :heading="$t('Let AI flag the tickets that need you')"
                :description="$t('Run an audit for a date range: AI reads each open ticket and gives it a mood, a sentiment score and a one-line summary, so you can see who is unhappy.')"
                :specifics="promoSpecifics"
                utm-content="feature_lock_report_ai-ticket-audit"
                docs-url="https://docs.fluentsupport.com/ai-ticket-audit"
            >
                <template #preview>
                    <div class="fs_pro_preview">
                        <div class="fs_pro_preview_card fs_pro_preview_fields">
                            <div class="fs_pro_preview_title">
                                <span class="fs_pro_preview_checkbox"></span>
                                <!-- translators: %s: ticket number -->
                                {{ $t('Ticket #%s', 1729) }}
                            </div>
                            <div class="fs_pro_preview_row fs_pro_preview_reveal" style="--fs-row-index: 0">
                                <span class="fs_pro_preview_row_label">{{ $t('Mood') }}</span>
                                <span class="fs_pro_preview_row_value">{{ $t('Frustrated') }}</span>
                            </div>
                            <div class="fs_pro_preview_row fs_pro_preview_reveal" style="--fs-row-index: 1">
                                <span class="fs_pro_preview_row_label">{{ $t('Score') }}</span>
                                <span class="fs_pro_preview_row_value">2.5/10</span>
                            </div>
                            <div class="fs_pro_preview_row fs_pro_preview_row_focal fs_pro_preview_reveal" style="--fs-row-index: 2">
                                <span class="fs_pro_preview_row_label">{{ $t('Escalate ticket') }}</span>
                                <span class="fs_pro_preview_badge fs_pro_preview_badge_warning">{{ $t('Critical') }}</span>
                            </div>
                        </div>
                    </div>
                </template>
            </ProShowcase>
        </div>

        <el-dialog v-if="has_pro" v-model="escalateVisible" :title="$t('Escalate ticket')" width="440px" class="fs_dialog fs_escalate_dialog">
            <p class="fs_escalate_ticket_name" v-if="escalateRow">#{{ escalateRow.ticket_id }} — {{ escalateRow.title }}</p>
            <span class="fs_priority_badge">{{ $t('Sets priority to Critical') }}</span>

            <div class="fs_field">
                <label>{{ $t('Assign to') }}</label>
                <el-select
                    v-model="escalateAgentId"
                    :placeholder="$t('Select agent')"
                    :loading="agentsFilterLoading"
                    :disabled="agentsFilterError"
                    filterable
                    class="fs_select_field"
                    style="width: 100%"
                >
                    <el-option v-for="agent in agentsFilter" :key="agent.id" :label="agent.name" :value="agent.id" />
                </el-select>
                <p class="fs_field_error" v-if="agentsFilterError">
                    {{ $t("Couldn't load agents.") }}
                    <a href="javascript:void(0)" @click="fetchAgentsFilter">{{ $t('Retry') }}</a>
                </p>
            </div>

            <div class="fs_field">
                <label>{{ $t('Internal note') }}</label>
                <el-input v-model="escalateNote" type="textarea" :rows="3" class="fs_textarea_input" />
            </div>

            <template #footer>
                <span class="fs_dialog_footer">
                    <el-button class="fs_outline_btn" @click="escalateVisible = false">{{ $t('Cancel') }}</el-button>
                    <el-button
                        class="fs_filled_btn"
                        type="primary"
                        :loading="escalateSubmitting"
                        :disabled="agentsFilterLoading || agentsFilterError || !agentsFilter.length"
                        @click="submitEscalate"
                    >
                        {{ $t('Escalate ticket') }}
                    </el-button>
                </span>
            </template>
        </el-dialog>
    </div>
</template>

<script type="text/babel">
import IconPack from "@/admin/Components/IconPack.vue";
import Pagination from "@/admin/Pieces/Pagination.vue";
import ProShowcase from "@/admin/Components/ProShowcase.vue";
import { shortcuts } from "../Utils/dateShortCuts";
import { formatDateRangeForDisplay, isFutureDate, isSameDateRange } from "../Utils/reportHelpers";

const MOODS = ["Happy", "Neutral", "Frustrated", "Very Unhappy"];
const BATCH_SIZE = 5;

export default {
    name: "TicketAudit",
    props: ["url", "date_range"],
    emits: ["date-change"],
    components: {
        IconPack,
        Pagination,
        ProShowcase
    },
    data() {
        return {
            loading: false,
            fetchError: false,
            localDateRange: this.date_range,
            shortcuts: shortcuts,
            moods: MOODS,

            runState: "idle", // idle | running | done
            totalCandidates: 0,
            processedCount: 0,
            recentAudited: [],

            summary: { total: 0, success: 0, failed: 0, average_score: 0, needs_attention: 0 },
            distribution: {},
            rows: [],
            needsAttentionRows: [],
            activeMoodFilter: "all",

            // Drives the pinned-column shadow; only shown once scrolled.
            tableScrolled: false,

            pagination: { current_page: 1, per_page: 20, total: 0 },

            resultsRequestToken: 0,
            pendingExternalDateRange: null,

            agentsFilter: [],
            agentsFilterLoading: false,
            agentsFilterError: false,
            escalateVisible: false,
            escalateRow: null,
            escalateAgentId: null,
            escalateNote: "",
            escalateSubmitting: false,
            escalateStepsDone: { agent: false, priority: false, note: false }
        };
    },
    computed: {
        promoSpecifics() {
            return [
                this.$t('Mood, score out of 10 and AI summary for every audited ticket'),
                this.$t('The unhappiest tickets shown first under Needs attention'),
                this.$t('Escalate to Critical, assign an agent and add a note')
            ];
        },
        formattedDateRange() {
            return formatDateRangeForDisplay(this.localDateRange);
        },
        progressPercent() {
            if (!this.totalCandidates) {
                return 0;
            }
            return Math.min(100, Math.round((this.processedCount / this.totalCandidates) * 100));
        },
        moodChips() {
            return [
                { key: "all", label: this.$t("All"), count: this.summary.total },
                ...MOODS.map(mood => ({ key: mood, label: this.$t(mood), count: this.distribution[mood] || 0 })),
                { key: "failed", label: this.$t("Failed"), count: this.summary.failed }
            ];
        }
        // Mood filtering and needs-attention are resolved server-side in fetchResults().
    },
    watch: {
        date_range: {
            handler(newVal) {
                // Ignore our own date-change echoing back through the prop. Only
                // external changes (back/forward, another report tab) should refetch.
                if (this.isSameDateRange(newVal, this.localDateRange)) {
                    return;
                }
                if (this.runState === "running") {
                    // runAudit() applies it once the run finishes.
                    this.pendingExternalDateRange = newVal;
                    return;
                }
                this.localDateRange = newVal;
                this.activeMoodFilter = "all";
                this.pagination.current_page = 1;
                this.fetchResults();
            },
            deep: true
        }
    },
    methods: {
        isSameDateRange,
        onTableScroll(event) {
            this.tableScrolled = event.target.scrollLeft > 0;
        },
        moodKey(mood) {
            return String(mood || "").toLowerCase().replace(/\s+/g, "-");
        },
        distPercent(mood) {
            if (!this.summary.success) {
                return 0;
            }
            return Math.round(((this.distribution[mood] || 0) / this.summary.success) * 100);
        },
        disabledDate: isFutureDate,
        formatDate(value) {
            return value ? this.$dayjs(value).format("MMM D, h:mm A") : "—";
        },
        handleDateChange() {
            this.$emit("date-change", this.localDateRange);
            this.activeMoodFilter = "all";
            this.pagination.current_page = 1;
            this.fetchResults();
        },
        goToTicket(ticketId) {
            this.$router.push({ name: "view_ticket", params: { ticket_id: ticketId } });
        },

        selectMoodFilter(key) {
            if (this.activeMoodFilter === key) {
                return;
            }
            this.activeMoodFilter = key;
            this.pagination.current_page = 1;
            // Aggregates depend only on the date range, not the mood filter.
            this.fetchResults(false);
        },

        async fetchResults(withAggregates = true) {
            if (!this.has_pro) {
                return;
            }
            if (!this.localDateRange || this.localDateRange.length !== 2) {
                // Invalidate any in-flight request so stale results can't land.
                this.resultsRequestToken++;
                this.loading = false;
                this.fetchError = false;
                this.summary = { total: 0, success: 0, failed: 0, average_score: 0, needs_attention: 0 };
                this.distribution = {};
                this.needsAttentionRows = [];
                this.rows = [];
                this.pagination.current_page = 1;
                this.pagination.total = 0;
                this.runState = "idle";
                return;
            }
            this.loading = true;
            this.fetchError = false;
            const [from, to] = this.localDateRange;
            // Only the latest call's response may apply.
            const requestToken = ++this.resultsRequestToken;
            try {
                const response = await this.$get("advanced-reports/audit/results", {
                    from,
                    to,
                    page: this.pagination.current_page,
                    per_page: this.pagination.per_page,
                    mood_filter: this.activeMoodFilter,
                    with_aggregates: withAggregates ? 1 : 0
                });
                if (requestToken !== this.resultsRequestToken) {
                    return;
                }
                if (withAggregates) {
                    this.summary = response.summary;
                    this.distribution = response.distribution;
                    this.needsAttentionRows = response.needs_attention_rows || [];
                }
                this.rows = response.rows.data || [];
                this.pagination.current_page = response.rows.current_page;
                // The server may cap per_page, so use what it applied.
                this.pagination.per_page = response.rows.per_page;
                this.pagination.total = response.rows.total;
                this.runState = this.summary.total ? "done" : "idle";
            } catch (error) {
                if (requestToken !== this.resultsRequestToken) {
                    return;
                }
                this.fetchError = true;
                this.$handleError(error);
                // Don't leave the toolbar disabled if runAudit()'s final refresh fails.
                if (this.runState === "running") {
                    this.runState = "idle";
                }
            } finally {
                if (requestToken === this.resultsRequestToken) {
                    this.loading = false;
                }
            }
        },

        async fetchAgentsFilter() {
            if (!this.has_pro) {
                return;
            }
            this.agentsFilterLoading = true;
            this.agentsFilterError = false;
            try {
                const response = await this.$get("advanced-reports/performance-overview/filters");
                this.agentsFilter = response.agents || [];
            } catch (error) {
                // The Escalate modal shows an error with a Retry button.
                this.agentsFilterError = true;
            } finally {
                this.agentsFilterLoading = false;
            }
        },

        async runAudit() {
            if (!this.has_pro) {
                return;
            }
            if (!this.localDateRange || this.localDateRange.length !== 2) {
                return;
            }

            this.runState = "running";
            this.processedCount = 0;
            this.recentAudited = [];
            const [from, to] = this.localDateRange;

            let candidateResponse;
            try {
                candidateResponse = await this.$post("advanced-reports/audit/run", { from, to });
            } catch (error) {
                this.runState = "idle";
                this.$handleError(error);
                return;
            }

            this.totalCandidates = candidateResponse.queued;
            const ticketIds = candidateResponse.ticket_ids || [];

            if (candidateResponse.total > candidateResponse.queued) {
                this.$notify({
                    type: "warning",
                    title: this.$t("Batch limited"),
                    // translators: %1$s is the number of tickets queued for the audit, %2$s the number of tickets in the date range
                    message: this.$t("%1$s of %2$s tickets queued. Narrow the date range or run again to cover the rest.", candidateResponse.queued, candidateResponse.total)
                });
            }

            let failedCount = 0;

            for (let i = 0; i < ticketIds.length; i += BATCH_SIZE) {
                const chunk = ticketIds.slice(i, i + BATCH_SIZE);
                try {
                    const batchResponse = await this.$post("advanced-reports/audit/process-batch", { ticket_ids: chunk, from, to });
                    const results = batchResponse.results || [];
                    this.recentAudited = results.concat(this.recentAudited).slice(0, 5);
                    this.processedCount += results.length;
                    // Tickets without a result (e.g. lock timeout) count as failed.
                    failedCount += Math.max(0, chunk.length - results.length);
                } catch (error) {
                    // Keep going. One failed batch shouldn't abort the run.
                    failedCount += chunk.length;
                }
            }

            if (failedCount > 0) {
                this.$notify({
                    type: "warning",
                    title: this.$t("Audit incomplete"),
                    // translators: %s is the number of tickets the audit run could not process
                    message: this.$_n("%s ticket wasn't audited in this run. Click Run Audit again to retry it.", "%s tickets weren't audited in this run. Click Run Audit again to retry any that still need it.", failedCount)
                });
            }

            // Apply a date_range change the watcher queued during the run.
            if (this.pendingExternalDateRange) {
                this.localDateRange = this.pendingExternalDateRange;
                this.pendingExternalDateRange = null;
            }

            this.activeMoodFilter = "all";
            this.pagination.current_page = 1;
            await this.fetchResults();
        },

        openEscalate(row) {
            this.escalateRow = row;
            this.escalateAgentId = null;
            // translators: %s is the one-line summary the AI audit wrote for the ticket
            this.escalateNote = row.summary ? this.$t("AI audit: %s", row.summary) : "";
            // Escalation is three separate REST calls. Track which succeeded so a
            // retry only re-runs the rest.
            this.escalateStepsDone = { agent: false, priority: false, note: false };
            this.escalateVisible = true;
        },

        async submitEscalate() {
            if (!this.escalateAgentId) {
                this.$handleError(this.$t("Please select an agent to assign"));
                return;
            }

            this.escalateSubmitting = true;
            const ticketId = this.escalateRow.ticket_id;

            try {
                if (!this.escalateStepsDone.agent) {
                    await this.$put(`tickets/${ticketId}/property`, { prop_name: "agent_id", prop_value: this.escalateAgentId });
                    this.escalateStepsDone.agent = true;
                }

                if (!this.escalateStepsDone.priority) {
                    await this.$put(`tickets/${ticketId}/property`, { prop_name: "priority", prop_value: "critical" });
                    this.escalateStepsDone.priority = true;
                }

                if (this.escalateNote && !this.escalateStepsDone.note) {
                    await this.$post(`tickets/${ticketId}/responses`, {
                        content: this.escalateNote,
                        conversation_type: "internal_info"
                    });
                    this.escalateStepsDone.note = true;
                }

                this.$notify({
                    type: "success",
                    title: this.$t("Escalated"),
                    message: this.$t("Ticket has been escalated.")
                });
                this.escalateVisible = false;
            } catch (error) {
                const completedSteps = [];
                if (this.escalateStepsDone.agent) completedSteps.push(this.$t("agent assigned"));
                if (this.escalateStepsDone.priority) completedSteps.push(this.$t("priority set to Critical"));
                if (this.escalateStepsDone.note) completedSteps.push(this.$t("note added"));

                if (completedSteps.length) {
                    // Tell the user what already went through. The dialog stays open for a retry.
                    this.$notify({
                        type: "warning",
                        title: this.$t("Escalation incomplete"),
                        // translators: %1$s is a comma-separated list of the escalation steps that succeeded, e.g. "agent assigned, note added"; %2$s is the label of the Escalate ticket button
                        message: this.$t("Already done: %1$s. The rest failed. Click \"%2$s\" again to retry it.", completedSteps.join(", "), this.$t("Escalate ticket"))
                    });
                } else {
                    this.$handleError(error);
                }
            } finally {
                this.escalateSubmitting = false;
            }
        }
    },
    mounted() {
        this.fetchAgentsFilter();
        this.fetchResults();
    }
};
</script>

