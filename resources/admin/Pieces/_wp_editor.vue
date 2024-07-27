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
                        <el-button class="fs_ai_response_button" @click="showAIResponseBox = !showAIResponseBox">
                            <div>
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 16 16" fill="none">
                                    <path d="M9.5 1L9.92326 2.87909C10.2823 4.47298 11.527 5.71767 13.1209 6.07671L15 6.5L13.1209 6.92329C11.527 7.28233 10.2823 8.52699 9.92326 10.1209L9.5 12L9.07674 10.1209C8.71766 8.52699 7.47301 7.28233 5.87911 6.92329L4 6.5L5.87911 6.07671C7.47294 5.71767 8.71766 4.47298 9.07674 2.8791L9.5 1Z" fill="white"/>
                                    <path d="M4 9L4.23089 10.025C4.42673 10.8943 5.10565 11.5733 5.97502 11.7691L7 12L5.97502 12.2309C5.10565 12.4267 4.42673 13.1056 4.23089 13.975L4 15L3.76911 13.975C3.57327 13.1056 2.89435 12.4267 2.02496 12.2309L1 12L2.02496 11.7691C2.89435 11.5733 3.57327 10.8944 3.76911 10.025L4 9Z" fill="white"/>
                                </svg>
                            </div>
                            <p>
                                Ask AI
                            </p>
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
        <div class="fs_ai_modify_response_box" v-if="showActionBar" :style="actionBarStyle">
            <el-popover
                placement="bottom"
                :width="480"
                trigger="click"
                :visible="showChatGPTPromptBox"
            >
                <template #reference>
                    <el-button class="fs_ai_popover_button" @click="editSelection()" size="small" type="default">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 16 16" fill="none">
                            <path d="M9.71345 0L10.1972 2.14753C10.6075 3.96911 12.03 5.39163 13.8516 5.80196L15.9992 6.28571L13.8516 6.76947C12.03 7.1798 10.6075 8.60227 10.1972 10.4239L9.71345 12.5714L9.22972 10.4239C8.81935 8.60227 7.39689 7.1798 5.57529 6.76947L3.42773 6.28571L5.57529 5.80196C7.3968 5.39163 8.81935 3.96911 9.22972 2.14754L9.71345 0Z" fill="white"/>
                            <path d="M3.42857 9.14285L3.69244 10.3143C3.91626 11.3078 4.69217 12.0837 5.68574 12.3076L6.85714 12.5714L5.68574 12.8353C4.69217 13.0591 3.91626 13.835 3.69244 14.8286L3.42857 16L3.1647 14.8286C2.94088 13.835 2.16497 13.0591 1.17139 12.8353L0 12.5714L1.17139 12.3076C2.16497 12.0837 2.94088 11.3079 3.1647 10.3143L3.42857 9.14285Z" fill="white"/>
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
            this.showActionBar = false;
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
                        top: `${rectStart.top + 40}px`,
                        left: `${rectStart.right + 30}px`,
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

.fs_chatGPT_box .fs_ai_response_button {
    align-items: center;
    padding: 5px 6px;
    height: 24px;
    font-size: 0;
    font-weight: 500;
    line-height: 1.33;  /* Line height should be in decimal */
    color: var(--static-static-white, #fff);
    background: linear-gradient(
            180deg,
            rgba(255, 255, 255, 0.16) 0%,
            rgba(255, 255, 255, 0) 100%
    ), var(--bg-strong-950, #0e121b);
    border: 1px solid rgba(255, 255, 255, 0.16);
    border-radius: var(--radius-4, 4px);
    box-shadow:
        0px 1px 2px 0px rgba(27, 28, 29, 0.48),
        0px 0px 0px 1px #242628;
    transition: background 0.3s, box-shadow 0.3s;
}

.fs_chatGPT_box .fs_ai_response_button span {
    display: flex;
    justify-content: space-between;
    gap: 7px;
}

.fs_chatGPT_box .fs_ai_response_button p {
    font-size: 12px;
    font-weight: 500;
    line-height: 1.33;
}

.fs_chatGPT_box .fs_ai_response_button:hover {
    background: linear-gradient(
            180deg,
            rgba(255, 255, 255, 0.32) 0%,
            rgba(255, 255, 255, 0.16) 100%
    ), var(--bg-strong-950, #0e121b);
    box-shadow:
        0px 2px 4px 0px rgba(27, 28, 29, 0.64),
        0px 0px 0px 1px #3a3b3d;
}

.fs_ai_modify_response_box .fs_ai_popover_button {
    padding: 6px;
    gap: 10px;
    border-radius: 50%;
    background: #0E121B;
    height: 30px;
    width: 30px;

}


</style>
