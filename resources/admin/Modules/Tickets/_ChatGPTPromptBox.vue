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
                    <img
                        loading="lazy"
                        src="https://cdn.builder.io/api/v1/image/assets/TEMP/981741ef99f69feb7a0e263c2a597e8199a950434fae0e506010e51ca27c4745?"
                        class="fs_response_logo"
                    />
                    <div class="fs_response_actions">
                        <img
                            loading="lazy"
                            src="https://cdn.builder.io/api/v1/image/assets/TEMP/1d26c22c91547948f1945588bc26e0a2f2b665e51592a02e6d2e61e4c5d4c97a?"
                            class="fs_response_icon"
                        />
                        <div class="fs_response_insert_icon">
                            <img
                                loading="lazy"
                                src="https://cdn.builder.io/api/v1/image/assets/TEMP/33b02a002d1b1bf13727e504c778bd619082a8bc9224d92c062756572dc1076c?"
                                class="fs_response_small_icon"
                            />
                        </div>
                        <div class="fs_response_insert_button">
                            <el-button class="fs_insert_button" type="primary" @click="insertReply(aiResponse)">Insert Response</el-button>
                        </div>
                    </div>
                </div>
                <div class="fs_response_content">
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
                    <el-button @click="generateResponse">
                        <img
                            loading="lazy"
                            src="https://cdn.builder.io/api/v1/image/assets/TEMP/933f740ba3e1c2e6c19acc7e18a018cc745e3d7e2eb86a1be09adcbacacbd339?"
                            class="fs_prompt_icon"
                        />
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

export default {
    name: 'ChatGPTPromptBox',
    props: ['selectedText'],
    setup(props, context) {
        const { post, translate, handleError } = useFluentHelper();
        const route = useRoute();
        const emit = context.emit;

        const state = reactive({
            prompt: '',
            aiResponse: '',
            loading: false,
            ticketID: parseInt(route.params.ticket_id),
            selectedText: props.selectedText,
            selectedPrompt:'',
            presetPrompts: [
                { label: 'Make it Shorter', text: 'shorten' },
                { label: 'Make it Longer', text: 'lengthen' },
                { label: 'Make it Friendly', text: 'friendly' },
                { label: 'Make it Professional', text: 'professional' },
                { label: 'Simplify', text: 'simplify' },
            ]
        });

        const generateResponse = () => {
            state.loading = true;
            post(`chatGPT/${state.ticketID}/generate-response`, {
                content: state.prompt,
                selectedText: props.selectedText,
                id: state.ticketID,
                type: 'generateResponse'
            })
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
            state.prompt = `${selectedPrompt.label}: ${state.selectedText}`;
            generateResponse();
        };

       const isSelected = (prompt) => {
            return state.selectedPrompt === prompt;
        }

        const insertReply = (aiResponse) => {
            emit('insert', aiResponse);
            state.visible = false;
        };
        const cancelResponse = () => {
            state.aiResponse = '';
            state.prompt= '';
        }

        return {
            ...toRefs(state),
            generateResponse,
            selectPresetPrompt,
            translate,
            insertReply,
            cancelResponse,
            isSelected,
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
.fs_prompt_icon {
    width: 20px;
    height: 20px;
}
.fs_prompt_subtitle {
     color: var(--text-soft-400, #99a0ae);
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
    font-feature-settings: "ss11" on, "cv09" on, "liga" off, "calt" off;
    letter-spacing: -0.08px;
    margin-top: 12px;
    font: 400 14px/20px Inter, sans-serif;
}
.fs_ai_response_loading {
    border: 1px solid #E1E4EA;
    border-radius: 12px;
    padding: 20px;
    margin: 20px 20px 0 20px;;
}

</style>
