<template>
    <div class="wp_vue_editor_wrapper">
        <div class="fs_shortcode_saved_replies">
            <div class="fs_cc_email_toggle_button" v-if="showCcToggleButton">
                <el-button size="small" type="primary" v-if="!add_cc" @click="handleCc('show')">
                    <span>{{ $t('Add Cc') }}</span>
                </el-button>
                <el-button size="small" type="primary" v-if="!add_cc" @click="handleCc('show')">
                    <span>{{ $t('Add Cc') }}</span>
                </el-button>
                <el-button size="small" type="danger" v-else @click="handleCc('hide')">
                    <span>{{ $t('Discard Cc') }}</span>
                </el-button>
            </div>

            <div class="fs_chatGPT_box" v-if="aiResponse">
                <el-popover
                    placement="bottom"
                    :width="480"
                    trigger="click"
                    :visible="showAIResponseBox"
                >
                    <template #reference>
                        <el-button icon="MagicStick" @click="showAIResponseBox = !showAIResponseBox">
                        </el-button>
                    </template>
                    <div class="fs_template_inserter">
                        <div>
                            <AIResponseGenerator type="ticketResponse" @close="closeAIResponsePromptBox" @insert="insertAIResponse"/>
                        </div>
                    </div>
                </el-popover>
            </div>

            <div class="fc_shortcode_box" v-if="showShortcodes" style="padding: 5px;">
                <el-dropdown type="primary" trigger="click">
                    <el-button size="small" type="primary" style="margin-right: .3em;">
                        {{$t('Shortcodes')}} <el-icon style="vertical-align: middle;"><ArrowDown /></el-icon>
                    </el-button>
                    <template #dropdown>
                        <el-dropdown-menu>
                            <el-dropdown-item v-for="(value, key) in shortcodes" :key="key" :value="key" @click="insertShortcode">
                                {{ value }}
                            </el-dropdown-item>
                        </el-dropdown-menu>
                    </template>
                </el-dropdown>
            </div>

            <div class="fc_saved_replies_box" v-if="showSavedReplies">
                <template-inserter @insert="insertTemplate" />
            </div>
        </div>
        <textarea v-if="hasWpEditor" class="wp_vue_editor" :id="editor_id">{{ modelValue }}</textarea>
        <textarea
            v-else
            class="wp_vue_editor wp_vue_editor_plain"
            v-model="plain_content"
            @click="updateCursorPos"
        ></textarea>
        <div v-if="showActionBar" :style="actionBarStyle">
            <el-popover
                placement="bottom"
                :width="480"
                trigger="click"
                :visible="showChatGPTPromptBox"
            >
                <template #reference>
                    <el-button @click="editSelection()" size="small" type="default">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" fill="none">
                            <path d="M11.6048 10.6479L9.81458 8.85764C9.57058 8.61364 9.44858 8.49164 9.31692 8.42639C9.0665 8.30229 8.7725 8.30229 8.522 8.42639C8.39042 8.49164 8.26839 8.61364 8.02436 8.85764C7.78032 9.10173 7.6583 9.22373 7.59307 9.35531C7.46898 9.60581 7.46898 9.89981 7.59307 10.1502C7.6583 10.2819 7.78032 10.4039 8.02436 10.6479L9.81458 12.4381M11.6048 10.6479L16.9757 16.0187C17.2197 16.2627 17.3417 16.3847 17.4069 16.5164C17.531 16.7668 17.531 17.0608 17.4069 17.3113C17.3417 17.4429 17.2197 17.5649 16.9757 17.809C16.7316 18.053 16.6096 18.175 16.478 18.2402C16.2275 18.3643 15.9335 18.3643 15.6831 18.2402C15.5514 18.175 15.4294 18.053 15.1854 17.809L9.81458 12.4381M11.6048 10.6479L9.81458 12.4381" stroke="#525866" stroke-width="1.25" stroke-linecap="round" stroke-linejoin="round"/>
                            <path d="M14.1673 1.66669L14.413 2.33052C14.7351 3.201 14.8962 3.63623 15.2137 3.95373C15.5312 4.27123 15.9663 4.43228 16.8368 4.75438L17.5007 5.00002L16.8368 5.24566C15.9663 5.56776 15.5312 5.72882 15.2137 6.04631C14.8962 6.36381 14.7351 6.79904 14.413 7.66952L14.1673 8.33335L13.9217 7.66952C13.5996 6.79905 13.4385 6.36381 13.121 6.04631C12.8035 5.72881 12.3683 5.56776 11.4978 5.24566L10.834 5.00002L11.4978 4.75438C12.3683 4.43228 12.8035 4.27123 13.121 3.95373C13.4385 3.63623 13.5996 3.201 13.9217 2.33052L14.1673 1.66669Z" stroke="#525866" stroke-width="1.25" stroke-linejoin="round"/>
                            <path d="M5 3.33331L5.18423 3.83119C5.42581 4.48404 5.5466 4.81047 5.78472 5.0486C6.02284 5.28671 6.34927 5.4075 7.00212 5.64908L7.5 5.83331L7.00212 6.01755C6.34927 6.25912 6.02284 6.37991 5.78472 6.61804C5.5466 6.85615 5.42581 7.18259 5.18423 7.83544L5 8.33331L4.81577 7.83544C4.57419 7.18259 4.4534 6.85615 4.21528 6.61804C3.97716 6.37991 3.65073 6.25912 2.99788 6.01755L2.5 5.83331L2.99788 5.64908C3.65073 5.4075 3.97716 5.28671 4.21528 5.04859C4.4534 4.81047 4.57419 4.48404 4.81577 3.83119L5 3.33331Z" stroke="#525866" stroke-width="1.25" stroke-linejoin="round"/>
                        </svg>
                    </el-button>
                </template>
                <div class="fs_template_inserter">
                    <div>
                        <AIResponseGenerator type="modifyResponse" :selectedText="selectedText" @close="closeSelectedTextPromptBox" @insert="insertAIResponse"/>
                    </div>
                </div>
            </el-popover>

        </div>
    </div>
</template>

<script type="text/babel">
export default {
    name: 'wp_editor',
    components: {
        TemplateInserter: () => true ? import('../Modules/Tickets/_templateInserter') : undefined,
        AIResponseGenerator: () => import('../Modules/Tickets/_AIResponseGenerator'),
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
        editorShortcodes: {
            type: Array,
            default() {
                return []
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
        aiResponse: {
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
            shortcodes: {
                '{{customer.first_name}}': 'Customer First Name',
                '{{customer.last_name}}': 'Customer Last Name',
                '{{customer.full_name}}': 'Customer Full Name',
                '{{customer.email}}': 'Customer Email',
                '{{customer.title}}': 'Customer Title',
                '{{customer.status}}': 'Customer Status',
                '{{agent.first_name}}': 'Agent First Name',
                '{{agent.last_name}}': 'Agent Last Name',
                '{{agent.full_name}}': 'Agent Full Name',
                '{{agent.email}}': 'Agent Email',
                '{{agent.title}}': 'Agent Title'
            },
            showActionBar: false,
            actionBarStyle: {},
            showChatGPTPromptBox: false,
            showAIResponseBox: false,
            selectedText: '',
            editorData: {}
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
                }
            };

            if (this.autofocus) {
                mceConfig.auto_focus = this.editor_id;
            }

            this.editor.initialize(this.editor_id, {
                mediaButtons: this.mediaButtons,
                tinymce: mceConfig,
                quicktags: true
            });

            jQuery('#' + this.editor_id).on('change', function (e) {
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
        },

        closeAIResponsePromptBox() {
            this.showAIResponseBox = false;
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
            let tinyInstance = tinyMCE.editors[wpActiveEditor];
            tinyInstance.insertContent(content.target._value);
            this.$emit('update:modelValue', this.modelValue + content.target._value)
        },

        insertTemplate(content) {
            let tinyInstance = tinyMCE.editors[wpActiveEditor];
            tinyInstance.setContent(this.modelValue + content)
            this.$emit('update:modelValue', this.modelValue + content)
        },

        insertAIResponse(content) {
            let tinyInstance = tinyMCE.editors[wpActiveEditor];
            const selection = tinyInstance.selection;
            selection.setContent(content);
            this.$emit('update:modelValue', tinyInstance.getContent());
            this.showChatGPTPromptBox = false;
            this.showAIResponseBox = false;

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
                        top: `${rectStart.top + 40}px`,
                        left: `${rectStart.right - 20}px`,
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

    },
    beforeCreate() {
        if (window.fluentSupportAdmin) {
            this.$options.components.TemplateInserter = require('../Modules/Tickets/_templateInserter').default
            this.$options.components.AIResponseGenerator = require('../Modules/Tickets/_AIResponseGenerator').default
        }
    },
    mounted() {
        this.initEditor();
        this.app_ready = true;
    }
}
</script>
<style lang="scss">
.wp_vue_editor {
    width: 100%;
    min-height: 100px;
}

.wp_vue_editor_wrapper {
    position: relative;
    width: 100%;

    .wp-media-buttons .insert-media {
        vertical-align: middle;
    }

    .popover-wrapper {
        z-index: 2;
        position: absolute;
        top: 0;
        right: 0;

        &-plaintext {
            left: auto;
            right: 0;
            top: -32px;
        }
    }

    .wp-editor-tabs {
        float: left;
    }
}

.mce-fluentcrm_editor_btn {
    button {
        font-size: 10px !important;
        border: 1px solid gray;
        margin-top: 3px;
    }

    &:hover {
        border: 1px solid transparent !important;
    }
}

.fs_shortcode_saved_replies {
    display: inline-flex;
    position: absolute;
    right: 0px;
    z-index: 2;
    align-items: center;
    .fc_saved_replies_box {
        button {
            padding: 6px 11px;
        }
    }
}

.action-bar {
    background-color: white;
    border: 1px solid #ccc;
    padding: 5px;
    box-shadow: 0px 0px 10px rgba(0, 0, 0, 0.1);
}

</style>
