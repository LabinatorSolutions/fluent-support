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
                <div class="fs_header_text">Ask AI about the ticket</div>
                <div class="fs_description_text">Please insert modal description here.</div>
            </div>
            <div class="fs_close">
                <el-button class="fs_close_button" @click="closeModal">
                    <img :src="appVars.asset_url + 'images/closeIcon.svg'" alt="">
                </el-button>
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
                            <el-button class="fs_regenerate_button" @click="generateResponse">
                                <img :src="appVars.asset_url + 'images/regenerate.svg'" alt="">
                            </el-button>
                        </div>

                        <div class="fs_response_insert_button">
                            <el-button class="fs_insert_button"  @click="insertReply(aiResponse)">Insert Response</el-button>
                        </div>
                    </div>
                </div>
                    <div :class="['fs_response_content', { 'full-size': isFullSize }]">
                        <p>{{ aiResponse }}</p>
                    </div>
            </div>
            <div class="fs_ai_response_loading" v-if="loading">
                <el-skeleton :rows="4" animated />
            </div>
        </div>

        <div class="fs_main_content">
            <div class="fs_prompt_wrapper">
                <textarea v-model="prompt" rows="3" placeholder="Enter your prompt here..." class="fs_textarea"></textarea>
                <div class="fs_prompt_button">
                    <el-button class="fs_prompt_submit" @click="generateResponse(prompt)">
                        <img :src="appVars.asset_url + 'images/aiPromptSubmitButton.svg'" alt="">
                    </el-button>
                </div>
            </div>
            <div>
                <div class="fs_prompt_subtitle">Some Popular Prompts</div>
                <div class="fs_prompt_options_container">
                    <div
                        v-for="prompt in presetPrompts"
                        :key="prompt.text"
                        :class="['fs_prompt_option', { 'fs_prompt_option_selected': prompt === selectedPrompt }]"
                        @click="selectPresetPrompt(prompt)"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 16 16" fill="none">
                            <circle cx="8" cy="8" r="2" :fill="isSelected(prompt) ? '#FFF' : '#717784'"/>
                        </svg>
                        <div class="fs_prompt_option_text">{{ prompt.label }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { reactive, toRefs, onMounted } from "vue";
import { useRoute } from "vue-router";
import { useFluentHelper, useNotify} from "@/admin/Composable/FluentFrameworkHelper";

export default {
    name: 'AIResponseGenerator',
    props: ['selectedText', 'type'],
    setup(props, context) {
        const { post, get, translate, handleError, appVars } = useFluentHelper();
        const route = useRoute();
        const emit = context.emit;
        const { notify } = useNotify();

        const state = reactive({
            prompt: '',
            presetPrompt: '',
            aiResponse: '',
            loading: false,
            ticketID: parseInt(route.params.ticket_id),
            selectedText: props.selectedText,
            selectedPrompt: '',
            isFullSize: false,
            presetPrompts: [],
        })

        const generateResponse = (prompt) => {
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

            post(`chatGPT/${state.ticketID}/generate-response`, requestData)
                .then(response => {
                    state.aiResponse = response;
                    state.loading = false;

                    if (state.prompt) {
                        state.selectedPrompt = '';
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
            emit('close');
            resetData();
        }

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
        }

        const insertReply = (aiResponse) => {
            emit('insert', aiResponse);
            resetData();
        };

        const fetchPresets = () => {
            get('chatGPT/preset-prompts', {type : props.type})
                .then(response => {
                    state.presetPrompts = response;
                })
                .catch(errors => {
                    handleError(errors);
                });
        };

        onMounted(()=>{
            fetchPresets();
        })

        return {
            ...toRefs(state),
            generateResponse,
            selectPresetPrompt,
            translate,
            insertReply,
            isSelected,
            copyText,
            closeModal,
            appVars
        };
    }
}
</script>

<style scoped>
</style>
