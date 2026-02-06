<template>
    <div class="wp_vue_editor_wrapper">
        <div class="fs_action_buttons">
            <div class="fs_ai_tools_box" v-if="fluentBotIntegration">
                <el-popover
                    placement="bottom"
                    :width="480"
                    trigger="click"
                    :visible="showFluentBotAIResponseBox"
                >
                    <template #reference>
                        <el-button class="fs_fluent_bot_response_button" @click="showFluentBotAIResponseBox = !showFluentBotAIResponseBox">
                            <img :src="appVars.asset_url + 'images/aiFillButton.svg'" alt="">
                            <p>
                                {{$t('Ask FluentBot')}}
                            </p>
                        </el-button>
                    </template>
                    <div class="fs_template_inserter">
                        <div>
                            <FluentBotAIResponseGenerator type="createResponse" :ticketId="ticketId" :productID="productID" :is_agent="is_agent" @close="closeFluentBotAIResponsePromptBox" @insert="insertAIResponse"/>
                        </div>
                    </div>
                </el-popover>
            </div>
            <div class="fs_ai_tools_box" v-if="openAIIntegration">
                <el-popover
                    placement="bottom"
                    :width="480"
                    trigger="click"
                    :visible="showAIResponseBox"
                >
                    <template #reference>
                        <el-button class="fs_openAI_response_button" @click="showAIResponseBox = !showAIResponseBox">
                            <img :src="appVars.asset_url + 'images/aiFillButton.svg'" alt="">
                            <p>
                                {{$t('Ask AI')}}
                            </p>
                        </el-button>
                    </template>
                    <div class="fs_template_inserter">
                        <div>
                            <AIResponseGenerator type="createResponse" @close="closeAIResponsePromptBox" @insert="insertAIResponse"/>
                        </div>

                    </div>
                </el-popover>
            </div>

            <div class="fs_cc_email_toggle_button" v-if="showCcToggleButton">
                <el-button size="small" type="primary" v-if="!add_cc" @click="handleCc('show')">
                    <span>{{ $t('Apply Cc') }}</span>
                </el-button>
                <el-button size="small" type="danger" v-else @click="handleCc('hide')">
                    <span>{{ $t('Discard Cc') }}</span>
                </el-button>
            </div>

            <div class="fs_saved_replies_box" v-if="showSavedReplies">
                <template-inserter @insert="insertTemplate" />
            </div>

            <div class="fs_shortcode_box" v-if="hasShortcodes" >
                <el-dropdown type="primary" trigger="click" :popper-options="{ strategy: 'fixed' }">
                    <el-button size="small" type="primary">
                        {{$t('Smart Codes')}} <el-icon style="vertical-align: middle;"><ArrowDown /></el-icon>
                    </el-button>
                    <template #dropdown>
                        <el-dropdown-menu class="fs_global_dropdown fs_shortcode_dropdown">
                            <el-dropdown-item v-for="(value, key) in shortcodes" :key="key" :value="key" @click="insertShortcode">
                                {{ value }}
                            </el-dropdown-item>
                        </el-dropdown-menu>
                    </template>
                </el-dropdown>
            </div>
        </div>
        <ImagePasteUploader ref="imagePasteUploader" @imagePath="setImagePath" v-loading="loadingImage"/>
        <textarea v-if="hasWpEditor" class="wp_vue_editor" :id="editor_id">
            {{ modelValue }}
        </textarea>
        <textarea
            v-else
            class="wp_vue_editor wp_vue_editor_plain"
            v-model="plain_content"
            @click="updateCursorPos"
        ></textarea>
        <div v-if="loadingImage" class="fs_loading_overlay">
        </div>
        <div class="fs_ai_modify_response_box" v-if="showActionBar && openAIIntegration" :style="actionBarStyle">
            <el-popover
                placement="bottom"
                :width="480"
                trigger="click"
                :visible="showChatGPTPromptBox"
                popper-class="fs_ai_response_popover"
            >
                <template #reference>
                    <el-button class="fs_ai_popover_button" @click="editSelection()" size="small" type="default">
                        <img :src="appVars.asset_url + 'images/aiIcon.svg'" alt="">
                    </el-button>
                </template>
                <div class="fs_ai_response_box">
                    <div>
                        <AIResponseGenerator type="modifyResponse" :selectedText="selectedText" @close="closeSelectedTextPromptBox" @insert="insertAIResponse"/>
                    </div>
                </div>
            </el-popover>

        </div>
    </div>
</template>

<script type="text/babel">
import ImagePasteUploader from './FormElements/_ImagePasteUploader';
export default {
    name: 'wp_editor',
    components: {
        TemplateInserter: () => true ? import('../Modules/Tickets/_templateInserter') : undefined,
        AIResponseGenerator: () => import('../Modules/Tickets/_AIResponseGenerator'),
        FluentBotAIResponseGenerator: () => import('../Modules/Tickets/_FluentBotAIResponseGenerator.vue'),
        ImagePasteUploader,
    },

    props: {
        editor_id: {
            type: String,
            default() {
                return 'wp_editor_' + Date.now() + parseInt(Math.random() * 1000);
            }
        },
        modelValue: {
            type: String,
            default() {
                return '';
            }
        },
        editor_shortcodes: {
            type: Object,
            default() {
                return {}
            }
        },
        height: {
            type: Number,
            default() {
                return 250;
            }
        },
        mediaButtons: {
            type: Boolean,
            default() {
                return true;
            }
        },
        autofocus: {
            type: Boolean,
            default() {
                return false;
            }
        },
        showCcToggleButton: {
            type: Boolean,
            default() {
                return false
            }
        },
        add_cc: {
            type: Boolean,
            default() {
                return false
            }
        },
        showShortcodes: {
            type: Boolean,
            default() {
                return false
            }
        },
        openAIIntegration: {
            type: Boolean,
            default() {
                return false
            }
        },
        fluentBotIntegration: {
            type: Boolean,
            default() {
                return false
            }
        },
        showSavedReplies: {
            type: Boolean,
            default() {
                return false
            }
        },
        ticketId: {
            type: [String, Number],
            default() {
                return ''
            }
        },
        productID: {
            type: [String, Number],
            default() {
                return ''
            }
        },
        is_agent: {
            type: [Boolean, String],
            default() {
                return false
            }
        },
        is_direct_paste: {
            type: Boolean,
            default() {
                return false
            }
        }
    },
    emits: ['update:modelValue', 'toggleCcOption'],
    data() {
        return {
            showButtonDesigner: false,
            hasWpEditor: (!!window.wp?.editor && !!wp?.editor?.autop) || !!window.wp?.oldEditor,
            editor: window.wp?.oldEditor || window.wp?.editor,
            plain_content: this.modelValue,
            cursorPos: (this.modelValue) ? this.modelValue.length : 0,
            app_ready: false,
            buttonInitiated: false,
            currentEditor: false,

            showActionBar: false,
            actionBarStyle: {},
            showChatGPTPromptBox: false,
            showAIResponseBox: false,
            showFluentBotAIResponseBox: false,
            selectedText: '',
            editorData: {},
            loadingImage: false
        }
    },
    computed: {
        shortcodes() {
            return this.editor_shortcodes;
        },
        hasShortcodes() {
            return this.editor_shortcodes && Object.keys(this.editor_shortcodes).length > 0;
        }
    },
    watch: {
        plain_content() {
            this.$emit('update:modelValue', this.plain_content);
        },
    },
    methods: {
        initEditor() {
            if (!this.hasWpEditor) {
                return;
            }

            this.editor.remove(this.editor_id);
            const that = this;
            const mceConfig = {
                height: that.height,
                toolbar1: 'formatselect,code,table,bold,italic,bullist,numlist,link,blockquote,alignleft,aligncenter,alignright,underline,strikethrough,forecolor,removeformat,codeformat,outdent,indent,undo,redo',
                setup(editor) {
                    editor.on('change', function (ed, l) {
                        that.changeContentEvent();
                    });
                    editor.on('mouseup', function (event) {
                        that.showActionBarOnSelection(editor);
                    });

                    if (that.is_direct_paste) {
                        editor.on('paste', function(event) {
                            const clipboardData = event.clipboardData || window.clipboardData;

                            if (clipboardData && clipboardData.items) {
                                let hasImage = false;

                                for (let i = 0; i < clipboardData.items.length; i++) {
                                    const item = clipboardData.items[i];
                                    if (item.kind === 'file') {
                                        hasImage = true;
                                        break;
                                    }
                                }

                                if (hasImage) {
                                    that.loadingImage = true;
                                    that.$refs.imagePasteUploader.handleImagePaste(event, that.ticketId, that.is_agent);
                                }
                            }
                        });
                    }
                }
            };

            if (this.autofocus) {
                mceConfig.auto_focus = this.editor_id;
            }

            const initializeEditor = () => {
                this.editor.initialize(this.editor_id, {
                    mediaButtons: this.mediaButtons,
                    tinymce: mceConfig,
                    quicktags: true
                });
            };

            if (document.readyState === "complete" || document.readyState === "interactive") {
                initializeEditor();
            } else {
                jQuery(document).ready(initializeEditor);
            }

            jQuery('#' + this.editor_id).on('change', function () {
                that.changeContentEvent();
            });
        },
        insertHtml(content) {
            this.currentEditor.insertContent(content);
        },
        changeContentEvent() {
            const content = this.editor.getContent(this.editor_id);
            this.$emit('update:modelValue', content);
        },

        closeSelectedTextPromptBox() {
            this.showChatGPTPromptBox = false;
            this.showActionBar = false;
        },

        closeAIResponsePromptBox() {
            this.showAIResponseBox = false;
        },

        closeFluentBotAIResponsePromptBox() {
            this.showFluentBotAIResponseBox = false;
        },

        handleCommand(command) {
            if (this.hasWpEditor) {
                window.tinymce.activeEditor.insertContent(command);
            } else {
                var part1 = this.plain_content.slice(0, this.cursorPos);
                var part2 = this.plain_content.slice(this.cursorPos, this.plain_content.length);
                this.plain_content = part1 + command + part2;
                this.cursorPos += command.length;
            }
        },

        updateCursorPos() {
            var cursorPos = jQuery('.wp_vue_editor_plain').prop('selectionStart');
            this.cursorPos = cursorPos;
        },

        handleCc(command) {
            this.$emit('toggleCcOption', command);
        },

        insertShortcode(content) {
            const shortcode = content.target._value;

            if (this.hasWpEditor) {
                let tinyInstance = tinyMCE.get(this.editor_id);
                if (tinyInstance) {
                    tinyInstance.focus();
                    tinyInstance.insertContent(shortcode);
                    const updatedContent = tinyInstance.getContent();
                    this.$emit('update:modelValue', updatedContent);
                }
            } else {
                const textarea = document.querySelector(`#${this.editor_id}`);
                if (textarea) {
                    const start = textarea.selectionStart;
                    const end = textarea.selectionEnd;
                    const text = textarea.value;
                    const before = text.substring(0, start);
                    const after = text.substring(end, text.length);
                    const newValue = before + shortcode + after;
                    this.plain_content = newValue;
                    // Set cursor position after the inserted shortcode
                    this.$nextTick(() => {
                        textarea.selectionStart = textarea.selectionEnd = start + shortcode.length;
                        textarea.focus();
                    });
                }
            }
        },

        insertTemplate(content) {
            let tinyInstance = tinyMCE.editors[wpActiveEditor];
            tinyInstance.setContent(this.modelValue + content)
            this.$emit('update:modelValue', this.modelValue + content)
        },

        insertAIResponse(content) {
            let tinyInstance = tinyMCE.get(wpActiveEditor);

            // Content should already be HTML at this point
            tinyInstance.insertContent(content);

            this.$emit('update:modelValue', tinyInstance.getContent({ format: 'html' }));

            this.showChatGPTPromptBox = false;
            this.showAIResponseBox = false;
            this.showFluentBotAIResponseBox = false;
            this.showActionBar = false;
        },

        showActionBarOnSelection(editor) {
            this.editorData = editor;
            const selection = this.editorData.selection;

            if (!selection.isCollapsed()) {
                const selectedText = selection.getContent({ format: 'text' });

                if (selectedText.length > 0) {
                    // Get the bounding rectangle of the entire selection
                    const range = selection.getRng();
                    const rect = range.getBoundingClientRect();

                    // Find the bounding rect of the first line of the selection
                    const rangeStart = range.cloneRange();
                    rangeStart.setStart(rangeStart.startContainer, rangeStart.startOffset);
                    rangeStart.setEnd(rangeStart.startContainer, rangeStart.startOffset);
                    const rectStart = rangeStart.getBoundingClientRect();

                    this.actionBarStyle = {
                        top: `${rectStart.top + 80}px`,
                        left: `${rectStart.right + 40}px`,
                        position: 'absolute',
                        zIndex: 1
                    };

                    this.selectedText = selectedText;
                    this.showChatGPTPromptBox = false;
                    this.showActionBar = true;
                }
            } else {
                this.showActionBar = false;
            }
        },

        overallTicketResponse() {
            this.showAIResponseBox = !this.showAIResponseBox;
        },

        editSelection() {
            if (!this.editor) {
                console.error('TinyMCE editor instance or selection not available.');
                return;
            }

            const selection = this.editorData.selection;
            const selectedText = selection.getContent({ format: 'text' }).trim();
            if (selectedText) {
                this.selectedText = selectedText;
                this.showChatGPTPromptBox = true;
            }
        },
        setImagePath(imageUrl) {
            const tinyInstance = tinyMCE.get(this.editor_id);
            if (!tinyInstance) return;

            const imageElement = new Image();
            imageElement.src = imageUrl;

            imageElement.onload = () => {
                const maxDimension = 300;
                const {naturalWidth, naturalHeight} = imageElement;
                const aspectRatio = naturalWidth / naturalHeight;

                let width = naturalWidth;
                let height = naturalHeight;

                if (naturalWidth > naturalHeight) {
                    width = maxDimension;
                    height = maxDimension / aspectRatio;
                } else {
                    height = maxDimension;
                    width = maxDimension * aspectRatio;
                }

                const imgTag = `<img src="${imageUrl}" alt="Uploaded Image" style="width: ${width}px; height: ${height}px;" />`;
                tinyInstance.insertContent(imgTag);

                this.loadingImage = false;
            };
        },
    },
    beforeCreate() {
        if (window.fluentSupportAdmin) {
            this.$options.components.TemplateInserter = require('../Modules/Tickets/_templateInserter').default
            this.$options.components.AIResponseGenerator = require('../Modules/Tickets/_AIResponseGenerator').default
            this.$options.components.FluentBotAIResponseGenerator = require('../Modules/Tickets/_FluentBotAIResponseGenerator').default
        }
    },
    mounted() {
        this.initEditor();
        this.app_ready = true;
    }
}
</script>
