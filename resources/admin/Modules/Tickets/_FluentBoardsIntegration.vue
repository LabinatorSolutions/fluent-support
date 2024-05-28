<template>
    <div v-if="app_ready" class="fs_fluent_boards" >
        <div class="fs_fbs_select_container">
            <div class="fs_fbs_select_wrapper">
                <label for="select-board" class="fs_fbs_select_label">{{ translate("Select Board") }}</label>
                <el-select id="select-board" @change="fetchStages" v-model="boardId">
                    <el-option
                        v-for="board in fluentBoardsData"
                        :key="board.id"
                        :value="board.id"
                        :label="board.title"></el-option>
                </el-select>
            </div>
            <div class="fs_fbs_select_wrapper">
                <label for="select-task" class="fs_fbs_select_label">{{ translate("Select Stage") }}</label>
                <el-select id="select-task"  v-model="stageId" :disabled="!boardId">
                    <el-option
                        v-for="stage in  fluentBoardStages"
                        :key="stage.id"
                        :value="stage.id"
                        :label="stage.title"></el-option>
                </el-select>
            </div>
        </div>

        <div>
            <div class="fs_fbs_date_range">
                <div class="fs_start_date_picker">
                    <label for="ticket-content" class="fs_fbs_input_label">{{ translate("Start date ") }}</label>
                    <el-date-picker
                        v-model="startDate"
                        class="fs_date_picker"
                        type="date"
                        placeholder="Pick a day"
                        value-format="YYYY-MM-DD"
                    />
                </div>
                <div class="fs_start_date_picker">
                    <label for="ticket-content" class="fs_fbs_input_label">{{ translate("End date ") }}</label>
                    <el-date-picker
                        v-model="endDate"
                        type="date"
                        class="fs_date_picker"
                        placeholder="Pick a day"
                        value-format="YYYY-MM-DD"
                    />
                </div>
            </div>

            <div class="fs_fbs_input_container">
                <label for="ticket-title" class="fs_fbs_input_label">{{ translate("Task Title") }}</label>
                <el-input id="ticket-title" v-model="task.title" />
            </div>

            <div class="fs_fbs_input_container">
                <label for="ticket-content" class="fs_fbs_input_label">{{ translate("Task Description") }}</label>
                <wp-editor id="ticket-content" v-model="task.content" />
            </div>


            <div class="fs_fbs_button_container">
                <el-button :disabled="!isAddButtonEnabled" @click="createTask" type="success">
                    {{ translate('Create Task') }}
                </el-button>
            </div>
            </div>

    </div>
    <div v-else>
        <el-skeleton :rows="5" animated/>
    </div>
</template>

<script type="text/babel">
import WpEditor from '../../Pieces/_wp_editor';
import {onMounted, reactive, toRefs, computed} from "vue";
import {useConfirm, useFluentHelper, useNotify} from "@/admin/Composable/FluentFrameworkHelper";

export default {
    name: 'FluentBoardsIntegration',
    props: {
        ticket: {
            type: Object,
            required: true
        },
        fluentcrm_profile: {
            type: Object,
            required: true
        }
    },
    components: {
        WpEditor
    },
    setup({ ticket, fluentcrm_profile }, context) {
        const emit = context.emit;
        const { notify } = useNotify();
        const { confirm } = useConfirm();
        const {
            appVars,
            get,
            post,
            put,
            translate,
            handleError,
            has_pro,
        } = useFluentHelper();

        const state = reactive({
            loading: false,
            fluentBoardsData: [],
            fluentBoardStages: [],
            boardId: null,
            stageId: null,
            app_ready: false,
            startDate: '',
            endDate:''
        });

        const task = reactive({ ...ticket });

        const isAddButtonEnabled = computed(() => {
            return task.title !== '' && state.stageId !== null;
        });

        const fetchBoards = () => {
            get('tickets/fluent-boards/boards')
                .then(response => {
                    state.fluentBoardsData = response.boards;
                    state.app_ready = true;
                })
                .catch((errors) => {
                    handleError(errors);
                });
        };

        const fetchStages = () => {
            get('tickets/fluent-boards/stages/' + state.boardId)
                .then(response => {
                    state.fluentBoardStages = response.stages;
                })
                .catch((errors) => {
                    handleError(errors);
                });
        };

        const createTask = () => {
            post('tickets/fluent-boards/stages', {
                source_id: task.id,
                title: task.title,
                description: task.content,
                board_id: state.boardId,
                stage_id: state.stageId,
                started_at: state.startDate,
                due_at: state.endDate,
                source: 'FluentSupport',
                crm_contact_id: fluentcrm_profile.id || null
            })
                .then(response => {
                    notify({
                        message: response.message,
                        type: "success",
                        position: "bottom-right",
                    });
                    emit('created');
                })
                .catch((errors) => {
                    handleError(errors);
                })
                .always(() => {
                    state.loading = false;
                });
        };

        onMounted(() => {
            fetchBoards();
        });

        return {
            ...toRefs(state),
            task,
            fetchBoards,
            fetchStages,
            createTask,
            appVars,
            isAddButtonEnabled,
            get,
            post,
            put,
            translate,
            handleError,
            has_pro,
            emit,
        };
    }
}
</script>

<style lang="scss">
.fs_fbs_date_range {
    display: flex;
    justify-content: space-between;
    width: 95%;
    gap: 10px;
    .fs_start_date_picker {
        width: 54% !important;
        margin-right: 35px;

        .fs_date_picker {
            width: 100% !important;
        }
    }
}
</style>
