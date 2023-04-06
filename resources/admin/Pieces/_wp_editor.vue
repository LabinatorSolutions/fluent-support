<template>
    <div class="wp_vue_editor_wrapper">
        <div class="fs_shortcode_saved_replies">
            <div class="fc_shortcode_box" v-if="showShortcodes" style="padding: 5px;">
                <el-dropdown type="primary" trigger="click">
                    <el-button size="small" type="primary" style="margin-right: .3em;">
                        {{$t('Shortcodes')}} <el-icon style="vertical-align: middle;"><ArrowDown /></el-icon>
                    </el-button>
                    <template #dropdown>
                        <el-dropdown-menu>
                            <el-dropdown-item v-for="(value ,key) in shortcodes" :key="key" :value="key" @click="insertShortcode">
                                {{value}}
                            </el-dropdown-item>
                        </el-dropdown-menu>
                    </template>
                </el-dropdown>
            </div>
            <div class="fc_saved_replies_box" v-if="showSavedReplies">
                <template-inserter @insert="insertTemplate"/>
            </div>
        </div>
        <textarea v-if="hasWpEditor" class="wp_vue_editor" :id="editor_id">{{ modelValue }}</textarea>
        <textarea v-else
                  class="wp_vue_editor wp_vue_editor_plain"
                  v-model="plain_content"
                  @click="updateCursorPos">
        </textarea>
    </div>
</template>

<script type="text/babel">
export default {
    name: 'wp_editor',
    components: {
        TemplateInserter: () => true ? import('../Modules/Tickets/_templateInserter') : undefined
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
        showShortcodes: {
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
    emits: ['update:modelValue'],
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
                '{{customer.first_name}}' : 'Customer First Name',
                '{{customer.last_name}}' : 'Customer Last Name',
                '{{customer.full_name}}' : 'Customer Full Name',
                '{{customer.email}}' : 'Customer Email',
                '{{customer.title}}' : 'Customer Title',
                '{{customer.status}}' : 'Customer Status',
                '{{agent.first_name}}' : 'Agent First Name',
                '{{agent.last_name}}' : 'Agent Last Name',
                '{{agent.full_name}}' : 'Agent Full Name',
                '{{agent.email}}' : 'Agent Email',
                '{{agent.title}}' : 'Agent Title'
            },
        }
    },
    watch: {
        plain_content() {
            this.$emit('update:modelValue', this.plain_content);
        }
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

        insertShortcode(content) {
            let tinyInstance = tinyMCE.editors[wpActiveEditor];
            tinyInstance.setContent(this.modelValue + content.target._value);
            this.$emit('update:modelValue', this.modelValue + content.target._value)
        },

        insertTemplate(content) {
            let tinyInstance = tinyMCE.editors[wpActiveEditor];
            tinyInstance.setContent(this.modelValue + content)
            this.$emit('update:modelValue', this.modelValue + content)
        }
    },
    beforeCreate() {
        if(window.fluentSupportAdmin) {
            this.$options.components.TemplateInserter = require('../Modules/Tickets/_templateInserter').default
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

</style>
