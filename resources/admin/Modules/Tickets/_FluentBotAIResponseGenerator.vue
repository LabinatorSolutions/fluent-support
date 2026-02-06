<template>
    <div class="fs_container fs_fluent_bot_ai_response_generator">
        <div class="fs_ai_header_section">
            <div class="fs_icon_container">
                <img
                    loading="lazy"
                    :src="appVars.asset_url + 'images/aiMagicIcon.svg'"
                    class="fs_icon"
                />
            </div>

            <div class="fs_text_container">
                <div class="fs_header_text">{{ $t(title) }}</div>
                <div class="fs_description_text">{{ $t(description) }}</div>
            </div>

            <div class="fs_close">
                <el-button class="fs_close_button" @click="closeModal">
                    <img :src="appVars.asset_url + 'images/closeIcon.svg'" alt="">
                </el-button>
            </div>
        </div>

        <div class="fs_product_selector">
            <div class="fs_product_label">{{ $t('Select Product') }}</div>
            <el-select
                v-model="selectedProduct"
                :placeholder="$t('Select a product')"
                size="small"
                class="fs_select_field fs_product_dropdown"
                filterable
                clearable
                :value-key="'id'"
                :default-value="0"
            >
                <el-option
                    :key="0"
                    :label="$t('General Bot (No specific product selected)')"
                    :value="0"
                />
                <el-option
                    v-for="product in products"
                    :key="product.id"
                    :label="product.title"
                    :value="product.id"
                />
            </el-select>
        </div>

        <div class="fs_draft" v-if="draftData.length > 1">
            <el-button class="fs_draft_button" @click="showDraft = !showDraft">
                <span>{{ $t('Draft') }}</span>
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
            <div v-loading="loading && !isStreaming" class="fs_response_container" v-if="aiResponse || isStreaming">
                <div class="fs_response_header">
                    <div class="fs_resize">
                        <el-button class="fs_resize_button" text @click="isFullSize = !isFullSize">
                            <img :src="appVars.asset_url + 'images/resize.svg'" alt="">
                        </el-button>
                    </div>
                    <div class="fs_response_actions">
                        <div class="fs_copy_text">
                            <el-button class="fs_copy_text_button" text @click="copyText" :disabled="isStreaming">
                                <img :src="appVars.asset_url + 'images/copyText.svg'" alt="">
                            </el-button>
                        </div>

                        <div class="fs_regenerate">
                            <el-button class="fs_regenerate_button" @click="generateResponse(finalPrompts)" :disabled="isStreaming">
                                <img :src="appVars.asset_url + 'images/regenerate.svg'" alt="">
                            </el-button>
                        </div>

                        <div class="fs_response_insert_button">
                            <el-button class="fs_insert_button" @click="insertReply(aiResponse)" :disabled="isStreaming">
                                {{ $t('Insert Content') }}
                            </el-button>
                        </div>
                    </div>
                </div>
                <div :class="['fs_response_content', { 'full-size': isFullSize, 'typing': isStreaming }]">
                    <div class="fs_response_text" v-html="formattedResponse"></div>
                    <div v-if="isStreaming && typingQueue.length === 0" class="fs_streaming_indicator">
                        <span>Generating</span><span class="fs_typing_dots">●●●</span>
                    </div>
                </div>
            </div>
            <div class="fs_ai_response_loading" v-if="loading && !isStreaming && !aiResponse">
                <el-skeleton :rows="4" animated />
            </div>
        </div>

        <div class="fs_main_content">
            <div class="fs_prompt_wrapper">
                <textarea v-model="prompt" rows="3" :placeholder="$t('Enter your prompt here...')" class="fs_textarea" required :disabled="isStreaming"></textarea>
                <div class="fs_prompt_button">
                    <el-button class="fs_prompt_submit" @click="generateResponse(prompt)" :disabled="isStreaming">
                        <img :src="appVars.asset_url + 'images/aiPromptSubmitButton.svg'" alt="">
                    </el-button>
                </div>
            </div>
            <div v-if="errorMessage" class="fs_error_message">{{ errorMessage }}</div>
            <div>
                <div class="fs_prompt_subtitle">{{ $t('Some General Prompts') }}</div>
                <div class="fs_prompt_options_container">
                    <div
                        v-for="prompt in presetPrompts"
                        :key="prompt.text"
                        :class="['fs_prompt_option', { 'fs_prompt_option_selected': prompt === selectedPrompt, 'disabled': isStreaming }]"
                        @click="!isStreaming && selectPresetPrompt(prompt)"
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

<script type="text/babel">
import { marked } from 'marked';
import DOMPurify from 'dompurify';
import hljs from 'highlight.js';
import 'highlight.js/styles/github.css'; // GitHub theme for syntax highlighting

export default {
    name: 'FluentBotAIResponseGenerator',
    props: ['aiProvider', 'ticketId', 'productID'],

    data() {
        return {
            prompt: '',
            presetPrompt: '',
            errorMessage: '',
            aiResponse: '',
            loading: false,
            ticketID: this.ticketId,
            selectedPrompt: '',
            isFullSize: true,
            presetPrompts: [],
            draftData: [],
            showDraft: false,
            finalPrompts: '',
            products: [],
            selectedProduct: this.productID ? parseInt(this.productID) : 0,
            conversationId: null,
            isStreaming: false,
            streamBuffer: '',
            displayedText: '',
            typingQueue: []
        };
    },

    computed: {
        title() {
            return 'Generate Responses with Fluent Bot';
        },
        description() {
            return 'Let Fluent Bot generate ticket responses to enhance support efficiency.';
        },
        formattedResponse() {
            if (!this.aiResponse) return '';

            try {
                // Clean up the response by removing empty lines before sending to marked
                const cleanedResponse = this.aiResponse;
                // Use marked to convert markdown to HTML
                const html = marked.parse(cleanedResponse);

                // Sanitize and return the HTML output
                return DOMPurify.sanitize(html);
            } catch (error) {
                console.error('Error parsing markdown:', error);
                // Fallback to plain text with line breaks
                return DOMPurify.sanitize(this.aiResponse.replace(/\n/g, '<br>'));
            }
        },
    },

    watch: {
        formattedResponse() {
            this.$nextTick(() => {
                // Re-highlight all code blocks after DOM update
                hljs.highlightAll();
            });
        }
    },

    methods: {
        // Function to format text for editor insertion with better formatting
        getFormattedTextForEditor(text) {
            if (!text) return '';

            // Convert markdown to well-formatted plain text while preserving structure
            return text
                // Convert headings to bold text with proper spacing
                .replace(/#{1,6}\s+(.+)/g, (match, heading) => `\n\n**${heading.trim()}**\n\n`)

                // Preserve bold formatting
                .replace(/\*\*([^*]+)\*\*/g, '**$1**')

                // Convert italic to emphasis
                .replace(/\*([^*]+)\*/g, '_$1_')

                // Format code blocks with proper indentation
                .replace(/```(\w*)\n?([\s\S]*?)```/g, (match, lang, code) => {
                    const formattedCode = code.trim()
                        .split('\n')
                        .map(line => `    ${line}`) // Indent each line
                        .join('\n');
                    return `\n\n${lang ? `${lang.toUpperCase()} Code:` : 'Code:'}\n${formattedCode}\n\n`;
                })

                // Format inline code
                .replace(/`([^`]+)`/g, '`$1`')

                // Convert links to readable format
                .replace(/\[([^\]]+)\]\(([^)]+)\)/g, '$1 ($2)')

                // Format bullet points
                .replace(/^\s*[-*+]\s+/gm, '• ')

                // Format numbered lists
                .replace(/^\s*(\d+)\.\s+/gm, '$1. ')

                // Clean up excessive line breaks but preserve paragraph structure
                .replace(/\n{3,}/g, '\n\n')

                // Ensure proper spacing around formatted elements
                .replace(/(\*\*[^*]+\*\*)/g, '\n$1\n')
                .replace(/\n{3,}/g, '\n\n')

                .trim();
        },

        // Keep the old function for copy functionality (plain text)
        getCleanTextForEditor(text) {
            if (!text) return '';

            // Remove markdown syntax for plain text insertion
            return text
                .replace(/#+\s+/g, '') // Remove headings
                .replace(/\*\*/g, '') // Remove bold
                .replace(/\*/g, '') // Remove italic
                .replace(/`{3}[\s\S]*?`{3}/g, match => {
                    // Preserve code blocks but remove the backticks
                    return match
                        .replace(/^```\w*\n/, '') // Remove opening ```
                        .replace(/```$/, ''); // Remove closing ```
                })
                .replace(/`([^`]+)`/g, '$1') // Remove inline code markers
                .replace(/\[([^\]]+)\]\(([^)]+)\)/g, '$1') // Convert links to text
                .trim();
        },

        saveDraft() {
            const draftKey = 'createResponseDraft';
            const draft = JSON.parse(this.$getData(draftKey) || '[]') || [];
            if (draft.length >= 3) {
                draft.shift();
            }
            // Save the clean text version for drafts
            const cleanText = this.getCleanTextForEditor(this.aiResponse);
            draft.push(cleanText);
            this.$saveData(draftKey, JSON.stringify(draft));
            this.draftData = draft;
        },

        addToStream(text) {
            // Don't clean the text here - preserve original formatting from SSE
            // But filter out empty or whitespace-only content before sending to marked
            if (text && text.trim() !== '') {
                this.aiResponse += text;
            }
        },

        // Helper function to process SSE events
        processEventData(eventType, data, trimmedPrompt) {
            if (eventType === 'message' && data && data.trim() !== '') {
                this.streamBuffer += data;
                this.addToStream(data);
            } else if (eventType === 'conversation_id' && data) {
                this.conversationId = data.trim();
            } else if (eventType === 'end') {
                this.loading = false;
                this.isStreaming = false;
                this.finalPrompts = trimmedPrompt;
                if (this.prompt || this.aiResponse) {
                    this.selectedPrompt = '';
                    this.saveDraft();
                }
                return true; // Signal to stop processing
            } else if (eventType === 'error') {
                this.loading = false;
                this.isStreaming = false;
                this.errorMessage = 'Failed to generate response. Please try again.';
                return true; // Signal to stop processing
            }
            return false; // Continue processing
        },

        generateResponse(prompt) {
            const trimmedPrompt = prompt.trim();

            if (!trimmedPrompt) {
                this.errorMessage = 'Prompt is required.';
                return;
            }

            this.errorMessage = '';
            this.loading = true;
            this.isStreaming = true;
            this.aiResponse = '';
            this.displayedText = '';
            this.typingQueue = [];
            this.streamBuffer = '';

            const requestData = {
                content: trimmedPrompt,
                id: this.ticketID,
                type: 'createResponse',
                provider: this.aiProvider,
                product_id: this.selectedProduct,
            };

            // Include conversation_id if we have one from previous interactions
            if (this.conversationId) {
                requestData.conversation_id = this.conversationId;
            }

            // Use fetch for proper streaming
            const baseUrl = this.appVars.rest.url;
            const nonce = this.appVars.rest.nonce;

            fetch(`${baseUrl}/fluent-bot/${this.ticketID}/generate-stream-response`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': nonce
                },
                body: JSON.stringify(requestData)
            })
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }

                    const reader = response.body.getReader();
                    const decoder = new TextDecoder();
                    let buffer = '';

                    const readStream = () => {
                        return reader.read().then(({ done, value }) => {
                            if (done) {
                                this.loading = false;
                                this.isStreaming = false;
                                this.finalPrompts = trimmedPrompt;

                                if (this.prompt || this.aiResponse) {
                                    this.selectedPrompt = '';
                                    this.saveDraft();
                                }
                                return;
                            }

                            const chunk = decoder.decode(value, { stream: true });
                            buffer += chunk;

                            // Process SSE data line by line to handle malformed events
                            const lines = buffer.split('\n');
                            let processedLines = [];
                            let currentDataLines = [];
                            let currentEventType = 'message'; // Default to message
                            let currentEventId = '';

                            for (let i = 0; i < lines.length; i++) {
                                const line = lines[i];

                                if (line.startsWith('event: ')) {
                                    // Process any accumulated data before switching events
                                    if (currentDataLines.length > 0) {
                                        const data = currentDataLines.join('\n');
                                        const shouldStop = this.processEventData(currentEventType, data, trimmedPrompt);
                                        if (shouldStop) return;
                                        currentDataLines = [];
                                    }
                                    currentEventType = line.substring(7).trim();
                                } else if (line.startsWith('id: ')) {
                                    currentEventId = line.substring(4).trim();
                                } else if (line.startsWith('data: ')) {
                                    currentDataLines.push(line.substring(6));
                                } else if (line.trim() === '') {
                                    // Empty line - process accumulated data
                                    if (currentDataLines.length > 0) {
                                        const data = currentDataLines.join('\n');
                                        const shouldStop = this.processEventData(currentEventType, data, trimmedPrompt);
                                        if (shouldStop) return;
                                        currentDataLines = [];
                                    }
                                }
                                processedLines.push(line);
                            }

                            // Process any remaining data
                            if (currentDataLines.length > 0) {
                                const data = currentDataLines.join('\n');
                                const shouldStop = this.processEventData(currentEventType, data, trimmedPrompt);
                                if (shouldStop) return;
                            }

                            // Keep the last incomplete line in buffer
                            buffer = lines[lines.length - 1] || '';

                            return readStream();
                        });
                    };

                    return readStream();
                })
                .catch(error => {
                    this.loading = false;
                    this.isStreaming = false;
                    this.errorMessage = 'Failed to generate response. Please try again.';
                });
        },

        selectPresetPrompt(preset) {
            this.selectedPrompt = preset;
            this.conversationId = null;
            const selectedPrompt = this.presetPrompts.find(item => item.text === preset.text);
            this.presetPrompt = `${selectedPrompt.description}`;
            this.prompt = '';
            this.generateResponse(this.presetPrompt);
        },

        isSelected(prompt) {
            return this.selectedPrompt === prompt;
        },

        closeModal() {
            this.removeDraft();
            this.$emit('close');
            this.resetData();
        },

        async copyText() {
            try {
                // Copy the clean text version instead of HTML
                const cleanText = this.getCleanTextForEditor(this.aiResponse);
                await navigator.clipboard.writeText(cleanText);
                this.$notify({
                    message: "Copied to clipboard",
                    type: "success",
                    position: "bottom-right",
                });
            } catch (error) {
                this.$notify({
                    message: "Something went wrong",
                    type: "danger",
                    position: "bottom-right",
                });
            }
        },

        resetData() {
            this.aiResponse = '';
            this.selectedPrompt = '';
            this.prompt = '';
            this.conversationId = null;
            this.isStreaming = false;
            this.displayedText = '';
            this.typingQueue = [];
            this.streamBuffer = '';

            this.removeDraft();
        },

        insertReply(aiResponse) {
            // Get the rendered HTML from the display element instead of raw markdown
            const responseElement = document.querySelector('.fs_ai_response_content');
            let htmlContent = '';

            if (responseElement) {
                // Get the actual rendered HTML from the display
                htmlContent = responseElement.innerHTML;
            } else {
                // Fallback: convert markdown to HTML
                try {
                    htmlContent = marked.parse(aiResponse);
                    htmlContent = DOMPurify.sanitize(htmlContent);
                } catch (error) {
                    htmlContent = aiResponse.replace(/\n/g, '<br>');
                }
            }

            this.$emit('insert', htmlContent);
            this.resetData();
        },

        fetchPresets() {
            this.$get('fluent-bot/preset-prompts', {
                type: 'createResponse',
                provider: this.aiProvider
            })
                .then(response => {
                    this.presetPrompts = response;
                })
                .catch(errors => {
                    this.$handleError(errors);
                });
        },

        removeDraft() {
            const draftKey = 'createResponseDraft';
            this.removeData(draftKey);
            this.draftData = [];
        },

        removeData(key) {
            let existingData = this.$getData('__fluentsupport_data');
            if (!existingData) {
                return;
            }
            existingData = JSON.parse(existingData);
            delete existingData[key];
            this.$saveData('__fluentsupport_data', JSON.stringify(existingData));
        },

        getSnippet(text) {
            return text.length > 30 ? text.substring(0, 30) + '...' : text;
        },

        selectDraft(draft) {
            this.aiResponse = draft;
            this.displayedText = draft;
            this.typingQueue = [];
            this.streamBuffer = draft;
        }
    },

    mounted() {
        // Configure marked with highlight.js
        marked.setOptions({
            breaks: false,
            gfm: true,
            headerIds: false,
            mangle: false,
            highlight: (code, lang) => {
                if (lang && hljs.getLanguage(lang)) {
                    return hljs.highlight(code, { language: lang }).value;
                }
                return hljs.highlightAuto(code).value;
            }
        });

        this.products = this.appVars.support_products || [];
        this.fetchPresets();
        this.removeDraft();
    },

    beforeUnmount() {
        // No cleanup needed for direct streaming
    }
};
</script>

