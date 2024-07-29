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
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" fill="none">
                            <path d="M16.25 10.6875L4.02083 15.5833C3.77083 15.6806 3.53819 15.6563 3.32292 15.5104C3.10764 15.3646 3 15.1597 3 14.8958V5.10417C3 4.84028 3.10764 4.63542 3.32292 4.48959C3.53819 4.34375 3.77083 4.31945 4.02083 4.41667L16.25 9.3125C16.5694 9.4375 16.7292 9.66667 16.7292 10C16.7292 10.3333 16.5694 10.5625 16.25 10.6875ZM4.5 13.7708L13.9583 10L4.5 6.22917V8.5L9 10L4.5 11.5V13.7708Z" fill="#525866"/>
                        </svg>
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
import { reactive, toRefs } from "vue";
import { useRoute } from "vue-router";
import {useConfirm, useFluentHelper, useNotify} from "@/admin/Composable/FluentFrameworkHelper";
import { ElMessage } from 'element-plus'

export default {
    name: 'AIResponseGenerator',
    props: ['selectedText', 'type'],
    setup(props, context) {
        const { post, translate, handleError, appVars } = useFluentHelper();
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
            presetPrompts: [
                {
                    label: 'Improve Writing',
                    text: 'shorten',
                    description: 'Use AI to make your text more concise and to the point, without losing its essence.'
                },
                {
                    label: 'Fix Spelling & Grammar',
                    text: 'lengthen',
                    description: 'AI will correct spelling mistakes and grammar errors, ensuring your text is polished and professional.'
                },
                {
                    label: 'Make Shorter',
                    text: 'friendly',
                    description: 'AI will adjust your text to be more casual and approachable, suitable for informal communications.'
                },
                {
                    label: 'Make Longer',
                    text: 'professional',
                    description: 'Extend your text with additional details and refined language to enhance its formality and depth.'
                },
                {
                    label: 'Simplify Language',
                    text: 'simplify',
                    description: 'AI will simplify complex language to make your text easier to understand, suitable for broader audiences.'
                }
            ]
        });

        if (props.type === 'ticketResponse') {
            state.presetPrompts = [
                {
                    label: 'Request More Information',
                    text: 'requestInfo',
                    description: 'Ask for more details or clarification regarding the issue raised in the ticket.'
                },
                {
                    label: 'Acknowledge Issue',
                    text: 'acknowledgeIssue',
                    description: 'Acknowledge the issue reported and assure the customer that it is being looked into.'
                },
                {
                    label: 'Provide Solution',
                    text: 'provideSolution',
                    description: 'Give a detailed solution to the problem mentioned in the ticket.'
                },
                {
                    label: 'Follow Up',
                    text: 'followUp',
                    description: 'Follow up with the customer to check if the issue has been resolved satisfactorily.'
                },
                {
                    label: 'Close Ticket',
                    text: 'closeTicket',
                    description: 'Inform the customer that the ticket is being closed due to resolution of the issue.'
                }
            ];
        }

        const generateResponse = (prompt) => {
            state.loading = true;
            const requestData = {
                content: prompt,
                id: state.ticketID,
                type: props.type
            };

            if (props.type !== 'ticketResponse') {
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
