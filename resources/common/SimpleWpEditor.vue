<template>
    <div class="wp_vue_editor_wrapper">
        <textarea v-if="hasWpEditor" class="wp_vue_editor" :id="editor_id">{{ plain_content }}</textarea>
        <textarea v-else
                  style="margin-top: 30px;"
                  class="wp_vue_editor wp_vue_editor_plain"
                  v-model="plain_content"
                  @click="updateCursorPos">
        </textarea>
    </div>
</template>

<script type="text/babel">
export default {
    name: 'simple_wp_editor',
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
        autofocus: {
            type: Boolean,
            default() {
                return false;
            }
        },
        height: {
            type: Number,
            default() {
                return 400;
            }
        },
        extra_style: {
            default() {
                return ''
            }
        },
        ticketId: {
            type: [Number, String],
            default() {
                return null;
            }
        },
        is_direct_paste: {
            type: Boolean,
            default() {
                return false;
            }
        }
    },
    data() {
        return {
            hasWpEditor: (!!window.wp.editor && !!wp.editor.autop) || !!window.wp.oldEditor,
            editor: window.wp.oldEditor || window.wp.editor,
            plain_content: this.modelValue,
            cursorPos: (this.modelValue) ? this.modelValue.length : 0,
            app_ready: false,
            currentEditor: false,
            isImageUploading: false
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

            let defaultStyles = 'blockquote {padding: 10px 10px;margin: 0;background: #f2f2f2;}';
           // this.editor.remove(this.editor_id);
            const that = this;
            this.editor.initialize(this.editor_id, {
                mediaButtons: false,
                tinymce: {
                    auto_focus: that.autofocus,
                    min_height: that.height,
                    fontsize_formats: '8px 10px 12px 14px 16px 18px 24px 28px 30px 32px',
                    toolbar1: 'formatselect,code,table,bold,italic,bullist,numlist,link,blockquote,alignleft,aligncenter,alignright,underline,strikethrough,forecolor,removeformat,codeformat,outdent,indent,undo,redo',
                    setup(editor) {
                        editor.on('change', function (ed, l) {
                            that.changeContentEvent();
                        });

                        editor.on('paste', function (e) {
                            let hasImage = false;
                            for (let i = 0; i < e.clipboardData.items.length; ++i) {
                                let item = e.clipboardData.items[i];
                                if (item.kind == "file" && item.type.startsWith("image/")) {
                                    hasImage = true;
                                    const file = item.getAsFile();
                                    const name = e.clipboardData.getData("text") || 'clipboard-image.png';
                                    that.handleUploadImage(new File([file], name, {type: file.type}));
                                    e.preventDefault();
                                }
                            }

                            if(!hasImage) {
                                // set the cursor position at the end
                                setTimeout(() => {
                                    editor.selection.select(editor.getBody(), true);
                                    editor.selection.collapse(false);

                                    // scroll to the bottom
                                    editor.getBody().scrollTop = editor.getBody().scrollHeight + 100;
                                }, 10);
                            }
                        });

                        that.currentEditor = editor;
                    },
                    formats: {
                        // Changes the alignment buttons to add a class to each of the matching selector elements
                        alignleft: {
                            selector: 'p,h1,h2,h3,h4,h5,h6,td,th,div,ul,ol,li,table,img',
                            classes: 'align-left',
                            styles: {'text-align': 'left'}
                        },
                        aligncenter: {
                            selector: 'p,h1,h2,h3,h4,h5,h6,td,th,div,ul,ol,li,table,img',
                            classes: 'align-center',
                            styles: {'text-align': 'center'},
                            attributes: {align: 'center'}
                        },
                        alignright: {
                            selector: 'p,h1,h2,h3,h4,h5,h6,td,th,div,ul,ol,li,table,img',
                            classes: 'align-right',
                            styles: {'text-align': 'right'},
                            attributes: {align: 'right'}
                        }
                    },
                    content_style: that.extra_style + defaultStyles,
                    resize: true
                },
                quicktags: true
            });
        },
        insertHtml(content) {
            this.currentEditor.insertContent(content);
        },
        changeContentEvent() {
            const content = this.editor.getContent(this.editor_id);
            this.$emit('update:modelValue', content);
        },
        updateCursorPos() {
            var cursorPos = jQuery('.wp_vue_editor_plain').prop('selectionStart');
            this.cursorPos = cursorPos;
        },
        handleUploadImage(file, name, options) {
            if(!this.is_direct_paste) {
                this.$notify.error(this.$t('Direct paste is not enabled for this editor.'));
                return;
            }

            this.isImageUploading = true;
            const formData = new FormData();
            formData.append('file', file);
            formData.append('ticket_id', this.ticketId);
            formData.append('intended_ticket_hash', this.appVars.intended_ticket_hash || '');
            formData.append('max_width', 1000);
            formData.append('max_height', 760);
            formData.append('type', 'direct_paste');
            formData.append('resize', true);

            this.$uploadFile('ticket_file_upload', formData)
                .then(response => {
                    if(response.attachments) {
                        this.insertHtml(`<img src="${response.attachments}" style="max-width: 700px; height: auto; width: 100%;" /><p>&nbsp;</p>`);
                    }
                })
                .catch((error) => {
                    this.$handleError(error);
                })
                .finally(() => {
                    this.isImageUploading = false;
                });
        }
    },
    mounted() {
        this.initEditor();
        this.app_ready = true;

        setTimeout(() => {
            if(!this.currentEditor) {
                this.initEditor();
            }
        }, 500);
    },
    beforeUnmount() {
        if (this.hasWpEditor) {
            this.editor.remove(this.editor_id);
        }
    }
}
</script>
