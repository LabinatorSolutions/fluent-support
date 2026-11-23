<template>
    <el-dropdown ref="dropdown" trigger="click" placement="bottom-end" popper-class="fs_live_snapshot_popper" @command="() => {}">
        <button class="fs_pill_dropdown_btn fs_live_pill_btn" :class="{ 'is-snapshot': isSnapshotView }">
            <svg v-if="isSnapshotView" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="9" /><path d="M12 7v5l3 2" /></svg>
            <span v-else class="fs_live_dot"></span>
            {{ triggerLabel }}
            <el-icon class="fs_dropdown_arrow"><ArrowDown /></el-icon>
        </button>
        <template #dropdown>
            <div class="fs_live_snapshot_panel">
                <button
                    class="fs_radio_row"
                    :class="{ 'is-selected': modelValue === 'live' }"
                    @click="selectMode('live')"
                >
                    <span class="fs_radio_dot" :class="{ 'is-selected': modelValue === 'live' }"></span>
                    <span class="fs_radio_text">
                        <span class="fs_radio_label">{{ $t("Live") }}</span>
                        <span class="fs_radio_help">{{ $t("Current numbers. They update as tickets change.") }}</span>
                    </span>
                </button>

                <button
                    class="fs_radio_row"
                    :class="{ 'is-selected': modelValue === 'snapshot' }"
                    :disabled="!snapshotGroups.length"
                    @click="selectMode('snapshot')"
                >
                    <span class="fs_radio_dot" :class="{ 'is-selected': modelValue === 'snapshot' }"></span>
                    <span class="fs_radio_text">
                        <span class="fs_radio_label">{{ $t("Snapshot") }}</span>
                        <span class="fs_radio_help">{{ $t("A saved copy of this report, taken every 6 hours. Use it to see how things looked earlier.") }}</span>
                    </span>
                </button>

                <div class="fs_snapshot_history">
                    <p class="fs_snapshot_status" v-if="loading">{{ $t("Loading snapshots…") }}</p>
                    <p class="fs_snapshot_status" v-else-if="fetchError">{{ $t("Couldn't load snapshots.") }}</p>
                    <p class="fs_snapshot_status" v-else-if="!snapshotGroups.length">{{ $t("No snapshots yet. The first one is saved within 6 hours.") }}</p>
                    <template v-else>
                        <div class="fs_snapshot_group" v-for="(group, gi) in snapshotGroups" :key="gi">
                            <p class="fs_snapshot_group_label">{{ group.label }}</p>
                            <button
                                class="fs_snapshot_item"
                                v-for="(item, ii) in group.items"
                                :key="ii"
                                :class="{ 'is-selected': isSelectedSnapshot(item) }"
                                @click="selectSnapshot(item)"
                            >
                                <span class="fs_radio_dot fs_radio_dot--sm" :class="{ 'is-selected': isSelectedSnapshot(item) }"></span>
                                <span class="fs_snapshot_time">{{ item.time }}</span>
                            </button>
                        </div>
                    </template>
                </div>
            </div>
        </template>
    </el-dropdown>
</template>

<script type="text/babel">
import { ArrowDown } from "@element-plus/icons-vue";
import dayjs from "dayjs";

export default {
    name: "LiveSnapshotSelector",
    props: {
        modelValue: {
            type: String,
            default: "live"
        },
        selectedSnapshot: {
            type: Object,
            default: null
        },
        reportType: {
            type: String,
            required: true
        }
    },
    emits: ["update:modelValue", "update:selectedSnapshot", "change"],
    components: {
        ArrowDown
    },
    data() {
        return {
            loading: false,
            fetchError: false,
            snapshots: []
        };
    },
    computed: {
        isSnapshotView() {
            return this.modelValue === "snapshot" && !!this.selectedSnapshot;
        },
        triggerLabel() {
            if (this.isSnapshotView) {
                // Date and time: snapshots span several days.
                const stamp = `${dayjs(this.selectedSnapshot.date).format("MMM D")}, ${this.selectedSnapshot.time}`;
                // translators: %s is the snapshot date and time, e.g. "Nov 18, 2:00 PM"
                return this.$t("Snapshot: %s", stamp);
            }
            return this.$t("Live");
        },
        snapshotGroups() {
            const groups = [];
            const groupsByDate = {};

            this.snapshots.forEach((snapshot) => {
                const stamp = dayjs(snapshot.snapshot_time);
                const dateKey = stamp.format("YYYY-MM-DD");

                if (!groupsByDate[dateKey]) {
                    const dayLabel = stamp.isSame(dayjs(), "day")
                        ? this.$t("Today")
                        : stamp.isSame(dayjs().subtract(1, "day"), "day")
                            ? this.$t("Yesterday")
                            : null;
                    const formattedDate = stamp.format("MMM DD, YYYY").toUpperCase();
                    groupsByDate[dateKey] = {
                        label: dayLabel ? `${dayLabel}, ${formattedDate}` : formattedDate,
                        items: []
                    };
                    groups.push(groupsByDate[dateKey]);
                }

                groupsByDate[dateKey].items.push({
                    id: snapshot.id,
                    date: dateKey,
                    time: stamp.format("h:mm A")
                });
            });

            return groups;
        }
    },
    methods: {
        fetchSnapshots() {
            this.loading = true;
            this.fetchError = false;
            this.$get("advanced-reports/snapshots", { report_type: this.reportType })
                .then((response) => {
                    this.snapshots = response.snapshots;
                })
                .catch((error) => {
                    this.snapshots = [];
                    this.fetchError = true;
                    this.$handleError(error);
                })
                .always(() => {
                    this.loading = false;
                });
        },
        closeMenu() {
            this.$refs.dropdown && this.$refs.dropdown.handleClose();
        },
        selectMode(mode) {
            if (mode === "live") {
                this.$emit("update:modelValue", "live");
                this.$emit("update:selectedSnapshot", null);
                this.$emit("change", { mode, snapshot: null });
                this.closeMenu();
                return;
            }
            // Open the newest snapshot so snapshot mode is never empty.
            const latest = this.selectedSnapshot || (this.snapshotGroups[0] && this.snapshotGroups[0].items[0]);
            if (latest) {
                this.selectSnapshot(latest);
            }
        },
        isSelectedSnapshot(item) {
            return this.selectedSnapshot && this.selectedSnapshot.id === item.id;
        },
        selectSnapshot(item) {
            this.$emit("update:modelValue", "snapshot");
            this.$emit("update:selectedSnapshot", item);
            this.$emit("change", { mode: "snapshot", snapshot: item });
            this.closeMenu();
        }
    },
    mounted() {
        this.fetchSnapshots();
    }
};
</script>
