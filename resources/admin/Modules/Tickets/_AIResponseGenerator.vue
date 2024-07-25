<template>
    <div class="fs_container">
        <div class="fs_div">
            <div class="fs_icon_container">
                <img
                    loading="lazy"
                    src="https://cdn.builder.io/api/v1/image/assets/TEMP/cbcacd463bd04094b1ddbaa792ad822f4033c6b88e47146e4f3f8cb7260a55b8?"
                    class="fs_icon"
                />
            </div>
            <div class="fs_text_container">
                <div class="fs_header_text">Ask AI about the ticket</div>
                <div class="fs_description_text">Please insert modal description here.</div>
            </div>
        </div>

        <div class="fs_response_section">
            <div v-loading="loading" class="fs_response_container" v-if="aiResponse && !loading">
                <div class="fs_response_header">
                    <div class="fs_resize">
                        <el-button class="fs_resize_button" text @click="isFullSize = !isFullSize">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" fill="none">
                                <path d="M16 3.25H17.5V7.75H16V4.75H13V3.25H16ZM4 3.25H7V4.75H4V7.75H2.5V3.25H4ZM16 15.25V12.25H17.5V16.75H13V15.25H16ZM4 15.25H7V16.75H2.5V12.25H4V15.25Z" fill="#525866"/>
                            </svg>
                        </el-button>
                    </div>
                    <div class="fs_response_actions">
                        <div class="fs_copy_text">
                            <el-button class="fs_copy_text_button" text @click="copyText">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" fill="none">
                                    <path d="M6.25 5.5V3.25C6.25 3.05109 6.32902 2.86032 6.46967 2.71967C6.61032 2.57902 6.80109 2.5 7 2.5H16C16.1989 2.5 16.3897 2.57902 16.5303 2.71967C16.671 2.86032 16.75 3.05109 16.75 3.25V13.75C16.75 13.9489 16.671 14.1397 16.5303 14.2803C16.3897 14.421 16.1989 14.5 16 14.5H13.75V16.75C13.75 17.164 13.4125 17.5 12.9948 17.5H4.00525C3.90635 17.5006 3.8083 17.4816 3.71674 17.4442C3.62519 17.4068 3.54192 17.3517 3.47174 17.282C3.40156 17.2123 3.34584 17.1294 3.30779 17.0381C3.26974 16.9468 3.2501 16.8489 3.25 16.75L3.25225 6.25C3.25225 5.836 3.58975 5.5 4.0075 5.5H6.25ZM4.75225 7L4.75 16H12.25V7H4.75225ZM7.75 5.5H13.75V13H15.25V4H7.75V5.5Z" fill="#525866"/>
                                </svg>
                            </el-button>
                        </div>

                        <div class="fs_regenerate">
                            <el-button class="fs_regenerate_button" text @click="generateResponse">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" fill="none">
                                    <path d="M5.09725 4.32476C6.45817 3.1455 8.19924 2.4975 10 2.50001C14.1423 2.50001 17.5 5.85776 17.5 10C17.5 11.602 16.9975 13.087 16.1425 14.305L13.75 10H16C16.0001 8.82373 15.6544 7.67336 15.006 6.69195C14.3576 5.71054 13.4349 4.94138 12.3529 4.4801C11.2708 4.01882 10.077 3.88578 8.91997 4.09752C7.7629 4.30926 6.69359 4.85643 5.845 5.67101L5.09725 4.32476ZM14.9028 15.6753C13.5418 16.8545 11.8008 17.5025 10 17.5C5.85775 17.5 2.5 14.1423 2.5 10C2.5 8.39801 3.0025 6.91301 3.8575 5.69501L6.25 10H4C3.9999 11.1763 4.34556 12.3267 4.994 13.3081C5.64244 14.2895 6.56505 15.0586 7.64712 15.5199C8.72918 15.9812 9.92296 16.1142 11.08 15.9025C12.2371 15.6908 13.3064 15.1436 14.155 14.329L14.9028 15.6753Z" fill="#335CFF"/>
                                </svg>
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
import { useFluentHelper } from "@/admin/Composable/FluentFrameworkHelper";
import { ElMessage } from 'element-plus'

export default {
    name: 'AIResponseGenerator',
    props: ['selectedText', 'type'],
    setup(props, context) {
        const { post, translate, handleError } = useFluentHelper();
        const route = useRoute();
        const emit = context.emit;

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

        const copyText = async () => {
            try {
                await navigator.clipboard.writeText(state.aiResponse);
                    ElMessage({
                        showClose: true,
                        message: 'Copied to clipboard',
                        type: "success",
                    });
            } catch (error) {
                return ElMessage({
                    message: 'Oops, this is a error message.',
                    type: 'error'
                })
            }
        };

        const insertReply = (aiResponse) => {
            emit('insert', aiResponse);
            state.visible = false;
        };

        const cancelResponse = () => {
            state.aiResponse = '';
            state.prompt = '';
        };

        return {
            ...toRefs(state),
            generateResponse,
            selectPresetPrompt,
            translate,
            insertReply,
            cancelResponse,
            isSelected,
            copyText
        };
    }
}
</script>

<style scoped>
.fs_div {
    align-self: stretch;
    background-color: #fff;
    display: flex;
    gap: 14px;
    padding: 16px 16px 16px 18px;
    border-bottom: 1px solid rgb(225 228 234);
}
.fs_icon_container {
    justify-content: center;
    align-items: center;
    border-radius: 999px;
    border-color: rgba(225, 228, 234, 1);
    border-style: solid;
    border-width: 1px;
    background-color: #fff;
    display: flex;
    width: 20px;
    height: 20px;
    margin: auto 0;
    padding: 10px;
}
.fs_icon {
    aspect-ratio: 1;
    object-fit: auto;
    object-position: center;
    width: 20px;
}
.fs_text_container {
    display: flex;
    flex-direction: column;
    flex: 1;
}
.fs_header_text {
    color: #0e121b;
    font-feature-settings: "ss11" on, "cv09" on, "liga" off, "calt" off;
    letter-spacing: -0.18px;
    font: 500 16px/150% Inter, sans-serif;
}
.fs_description_text {
    color: #525866;
    font-feature-settings: "ss11" on, "cv09" on, "liga" off, "calt" off;
    margin-top: 4px;
    font: 400 12px/133% Inter, sans-serif;
}
.fs_main_content {
    padding: 20px;
}
.fs_prompt_wrapper {
    position: relative;
}
.fs_textarea {
    width: 100%;
    padding: 12px;
    border-radius: 12px;
    border: 1px solid rgba(225, 228, 234, 1);
    box-shadow: 0px 1px 2px 0px rgba(10, 13, 20, 0.03);
    resize: none;
}
.fs_prompt_button {
    position: absolute;
    bottom: 10px;
    right: 10px;
}

.fs_prompt_button .fs_prompt_submit {
    display: flex;
    padding: 4px;
    justify-content: center;
    align-items: center;
    gap: 2px;
    border-radius: 6px;
    border: 1px solid #E1E4EA;
    background: #FFF;
    box-shadow: 0 1px 2px 0 rgba(10, 13, 20, 0.03);
}

.fs_prompt_button .fs_prompt_submit:hover {
    background: #0E121B;
}

.fs_prompt_button .fs_prompt_submit:hover svg path {
    fill: #FFF;
}

.fs_regenerate .fs_regenerate_button {
    display: flex;
    padding: 4px;
    align-items: center;
    gap: 10px;
    border-radius: 6px;
    background: rgba(71, 108, 255, 0.16);
}

.fs_regenerate .fs_regenerate_button:hover {
    background: rgba(20, 46, 137, 0.16);
}

.fs_resize .fs_resize_button {
    display: flex;
    padding: 4px;
    align-items: center;
    gap: 10px;
    border-radius: 6px;
}

.fs_resize .fs_resize_button:hover {
    background: rgba(20, 46, 137, 0.16);
}

.fs_copy_text .fs_copy_text_button {
    display: flex;
    padding: 4px;
    align-items: center;
    gap: 10px;
    border-radius: 6px;
}
.fs_copy_text .fs_copy_text_button:hover {
    background: rgba(20, 46, 137, 0.16);
}

.fs_prompt_subtitle {
     color: #99a0ae;
     font-feature-settings: "ss11" on, "cv09" on, "liga" off, "calt" off;
     letter-spacing: 0.48px;
     text-transform: uppercase;
     margin-top: 20px;
     font: 500 12px/133% Inter, sans-serif;
 }
.fs_prompt_options_container {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 12px;
}
.fs_prompt_option {
    justify-content: center;
    align-items: center;
    border-radius: 999px;
    border: 1px solid rgba(113, 119, 132, 1);
    display: flex;
    gap: 8px;
    padding: 4px 8px;
    cursor: pointer;
    transition: background-color 0.3s ease, color 0.3s ease;
}
.fs_prompt_option:hover {
    background-color: #E1E4EA;
    color: #222530;
}
.fs_prompt_option_text {
    font-feature-settings: "ss11" on, "cv09" on, "liga" off, "calt" off;
    font-family: Inter, sans-serif;
}
.fs_prompt_icon {
    aspect-ratio: 1;
    object-fit: contain;
    object-position: center;
    width: 100%;
    font-feature-settings: "ss11" on, "cv09" on, "liga" off, "calt" off;
    font-family: Inter, sans-serif;
}
.fs_prompt_subtitle {
    color: #99a0ae;
    font-feature-settings: "ss11" on, "cv09" on, "liga" off, "calt" off;
    letter-spacing: 0.48px;
    text-transform: uppercase;
    margin-top: 20px;
    font: 500 12px/133% Inter, sans-serif;
}
.fs_prompt_options_row {
    display: flex;
    margin-top: 12px;
    gap: 8px;
    font-size: 14px;
    color: #717784;
    font-weight: 500;
    letter-spacing: -0.08px;
    line-height: 143%;
}
.fs_prompt_option {
    justify-content: center;
    border-radius: 999px;
    border-color: rgba(113, 119, 132, 1);
    border-style: solid;
    border-width: 1px;
    display: flex;
    gap: 0px;
    padding: 4px 8px 4px 4px;
}
.fs_prompt_option_selected,
.fs_prompt_option_selected:hover {
    background-color: #335CFF;
    color: #fff;
}
.fs_prompt_option_text {
    font-feature-settings: "ss11" on, "cv09" on, "liga" off, "calt" off;
    font-family: Inter, sans-serif;
}
textarea {
    width: 100%;
    padding: 10px;
    border: 1px solid #ccc;
    border-radius: 4px;
    resize: vertical;
}
.preset-prompts li {
    cursor: pointer;
}
.preset-prompts li:hover {
    text-decoration: underline;
}
.fs_response_container {
    justify-content: center;
    align-self: stretch;
    border-radius: 12px;
    box-shadow: 0px 1px 2px 0px rgba(10, 13, 20, 0.03);
    border-color: rgba(225, 228, 234, 1);
    border-style: solid;
    border-width: 1px;
    background-color: #f5f7fa;
    display: flex;
    max-width: 400px;
    flex-direction: column;
    padding: 20px;
    margin: 20px 20px 0 20px;;
}
.fs_response_header {
    justify-content: space-between;
    display: flex;
    width: 100%;
    gap: 8px;
}
.fs_response_logo,
.fs_response_icon,
.fs_response_small_icon {
    aspect-ratio: 1;
    object-fit: auto;
    object-position: center;
    width: 20px;
    margin: auto 0;
}
.fs_response_actions {
    display: flex;
    gap: 8px;
    justify-content: space-between;
}
.fs_response_insert_icon {
    align-items: center;
    border-radius: 6px;
    background-color: rgba(71, 108, 255, 0.16);
    display: flex;
    justify-content: center;
    width: 28px;
    height: 28px;
    padding: 4px;
}
.fs_insert_button {
    font-feature-settings: "ss11" on, "cv09" on, "liga" off, "calt" off;
    justify-content: center;
    border-radius: 6px;
    background-color: #335cff;
    color: #fff;
    letter-spacing: -0.08px;
    padding: 4px 6px;
    font: 500 14px/143% Inter, sans-serif;
}
.fs_insert_button:hover {
    background-color: #4b6fff;
}
.fs_response_content {
    color: #0e121b;
    letter-spacing: -0.08px;
    margin-top: 12px;
    font: 400 14px/20px Inter, sans-serif;
    max-height: 180px;
    overflow-y: auto;
}

.fs_response_content.full-size {
    max-height: none;
}

.fs_ai_response_loading {
    border: 1px solid #E1E4EA;
    border-radius: 12px;
    padding: 20px;
    margin: 20px 20px 0 20px;;
}
</style>
