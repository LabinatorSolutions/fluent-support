<template>
    <div class="fs_container">
        <div class="fs_ai_header_section">
            <div class="fs_icon_container">
                <img
                    loading="lazy"
                    :src="appVars.asset_url + 'images/aiMagicIcon.svg'"
                    class="fs_icon"
                />
            </div>

            <div class="fs_text_container">
                <div class="fs_header_text">{{ translate(title) }}</div>
                <div class="fs_description_text">{{ translate(description) }}</div>
            </div>

            <div class="fs_close">
                <el-button class="fs_close_button" @click="closeModal">
                    <img :src="appVars.asset_url + 'images/closeIcon.svg'" alt="">
                </el-button>
            </div>
        </div>

        <div class="fs_draft" v-if="draftData.length > 1">
            <el-button class="fs_draft_button" @click="showDraft = !showDraft">
                <span>{{translate('Draft')}}</span>
                <img :class="['fs_draft_arrow', { 'rotate-down': showDraft }]" :src="appVars.asset_url + 'images/arrowRight.svg'" alt="">
            </el-button>
            <div>
                <el-collapse-transition>
                    <div class="fs_draft_widget" v-show="showDraft" >
                        <div
                            v-for="(draft, index) in draftData"
                            :key="index"
                            class="fs_draft_item"
                            @click="selectDraft(draft)"
                        >
                            <h3>Draft {{index+1}}</h3>
                            <p>{{ getSnippet(draft) }}</p>
                        </div>
                    </div>
                </el-collapse-transition>
            </div>
        </div>

        <div class="fs_response_section">
            <div v-loading="loading" class="fs_response_container" v-if="aiResponse && !loading">
                <div class="fs_response_header">
                    <div class="fs_resize">
                        <el-button class="fs_resize_button" text @click="isFullSize = !isFullSize">
                            <img :src="appVars.asset_url + 'images/resize.svg'" alt="">
                        </el-button>
                    </div>
                    <div class="fs_response_actions">
                        <div class="fs_copy_text">
                            <el-button class="fs_copy_text_button" text @click="copyText">
                                <img :src="appVars.asset_url + 'images/copyText.svg'" alt="">
                            </el-button>
                        </div>

                        <div class="fs_regenerate">
                            <el-button class="fs_regenerate_button" @click="generateResponse(finalPrompts)">
                                <img :src="appVars.asset_url + 'images/regenerate.svg'" alt="">
                            </el-button>
                        </div>

                        <div class="fs_response_insert_button">
                            <el-button class="fs_insert_button" @click="insertReply(aiResponse)">{{ translate('Insert Content') }}</el-button>
                        </div>
                    </div>
                </div>
                <div :class="['fs_response_content', { 'full-size': isFullSize }]">
                    <div v-html="formattedResponse"></div>
                </div>
            </div>
            <div class="fs_ai_response_loading" v-if="loading">
                <el-skeleton :rows="4" animated />
            </div>
        </div>

        <div class="fs_main_content">
            <div class="fs_prompt_wrapper">
                <textarea v-model="prompt" rows="3" placeholder="Enter your prompt here..." class="fs_textarea" required></textarea>
                <div class="fs_prompt_button">
                    <el-button class="fs_prompt_submit" @click="generateResponse(prompt)" >
                        <img :src="appVars.asset_url + 'images/aiPromptSubmitButton.svg'" alt="">
                    </el-button>
                </div>
            </div>
            <div v-if="errorMessage" class="fs_error_message">{{ errorMessage }}</div>
            <div>
                <div class="fs_prompt_subtitle">{{ translate('Some General Prompts') }}</div>
                <div class="fs_prompt_options_container">
                    <div
                        v-for="prompt in presetPrompts"
                        :key="prompt.text"
                        :class="['fs_prompt_option', { 'fs_prompt_option_selected': prompt === selectedPrompt }]"
                        @click="selectPresetPrompt(prompt)"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 16 16" fill="none">
                            <circle cx="8" cy="8" r="2" :fill="isSelected(prompt) ? '#FFF' : '#717784'" />
                        </svg>
                        <div class="fs_prompt_option_text">{{ prompt.label }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { reactive, toRefs, onMounted, computed } from "vue";
import { useRoute } from "vue-router";
import { useFluentHelper, useNotify } from "@/admin/Composable/FluentFrameworkHelper";

export default {
    name: 'AIResponseGenerator',
    props: ['selectedText', 'type'],
    setup(props, context) {
        const { post, get, translate, handleError, appVars, saveData, getData, removeData } = useFluentHelper();
        const route = useRoute();
        const emit = context.emit;
        const { notify } = useNotify();

        const state = reactive({
            prompt: '',
            presetPrompt: '',
            errorMessage: '',
            aiResponse: '',
            loading: false,
            ticketID: parseInt(route.params.ticket_id),
            selectedText: props.selectedText,
            selectedPrompt: '',
            isFullSize: false,
            presetPrompts: [],
            draftData: [],
            showDraft: false,
            finalPrompts: []
        });

        const modifyResponseTitle = 'Enhance Responses with AI';
        const modifyResponseDescription = 'Refine ticket responses with OpenAI to enhance clarity and precision.';

        const generateResponseTitle = 'Generate Responses with AI';
        const generateResponseDescription = 'Let OpenAI generate ticket responses to enhance support efficiency.';

        const title = computed(() =>
            props.type === 'modifyResponse' ? modifyResponseTitle : generateResponseTitle
        );

        const description = computed(() =>
            props.type === 'modifyResponse' ? modifyResponseDescription : generateResponseDescription
        );

        const formattedResponse = computed(() => {
            return state.aiResponse
                .replace(/\n\n/g, '</p><p>')
                .replace(/\n/g, '<br>')
                .replace(/ {2,}/g, match => match.replace(/ /g, '&nbsp;'));
        });

        const saveDraft = () => {
            const draftKey = props.type === 'modifyResponse' ? 'modifyResponseDraft' : 'createResponseDraft';
            const draft = JSON.parse(getData(draftKey)) || [];
            if (draft.length >= 3) {
                draft.shift();
            }
            draft.push(state.aiResponse);
            saveData(draftKey, JSON.stringify(draft));
            state.draftData = draft;
        };

        const generateResponse = (prompt) => {
            if (!prompt.trim()) {
                state.errorMessage = 'Prompt is required.';
                return;
            }
            state.errorMessage = '';

            state.loading = true;
            const requestData = {
                content: prompt,
                id: state.ticketID,
                type: props.type
            };

            if (props.type !== 'createResponse') {
                requestData.selectedText = props.selectedText;
                requestData.type = props.type;
            }

            post(`openai/${state.ticketID}/generate-response`, requestData)
                .then(response => {
                    state.aiResponse = response;
                    state.loading = false;
                    state.finalPrompts = prompt;
                    if (state.prompt || state.aiResponse) {
                        state.selectedPrompt = '';
                        saveDraft();
                    }
                })
                .catch(errors => {
                    state.loading = false;
                    handleError(errors);
                });
        };

        const selectPresetPrompt = (preset) => {
            state.selectedPrompt = preset;
            const selectedPrompt = state.presetPrompts.find(item => item.text === preset.text);
            state.presetPrompt = `${selectedPrompt.description}`;
            state.prompt = '';
            generateResponse(state.presetPrompt);
        };

        const isSelected = (prompt) => {
            return state.selectedPrompt === prompt;
        };

        const closeModal = () => {
            removeDraft();
            emit('close');
            resetData();
        };

        const copyText = async () => {
            try {
                await navigator.clipboard.writeText(state.aiResponse);
                notify({
                    message: "Copied to clipboard",
                    type: "success",
                    position: "bottom-right",
                });
            } catch (error) {
                notify({
                    message: "Something went wrong",
                    type: "danger",
                    position: "bottom-right",
                });
            }
        };

        const resetData = () => {
            state.aiResponse = '';
            state.selectedPrompt = '';
            state.prompt = '';
            removeDraft();
        };

        const insertReply = (aiResponse) => {
            emit('insert', aiResponse);
            resetData();
        };

        const fetchPresets = () => {
            get('openai/preset-prompts', { type: props.type })
                .then(response => {
                    state.presetPrompts = response;
                })
                .catch(errors => {
                    handleError(errors);
                });
        };

        const removeDraft = () => {
            const draftKey = props.type === 'modifyResponse' ? 'modifyResponseDraft' : 'createResponseDraft';
            removeData(draftKey);
            state.draftData = [];
        };

        const getSnippet = (text) => {
            return text.length > 30 ? text.substring(0, 30) + '...' : text;
        };

        const selectDraft = (draft) => {
            state.aiResponse = draft;
        };

        onMounted(() => {
            fetchPresets();
            removeDraft();
        });

        return {
            ...toRefs(state),
            generateResponse,
            selectPresetPrompt,
            translate,
            insertReply,
            isSelected,
            copyText,
            closeModal,
            appVars,
            title,
            description,
            getSnippet,
            selectDraft,
            saveDraft,
            formattedResponse
        };
    }
};
</script>



