<template>
    <div v-if="appVars.ticket_tags.length" v-loading="doing_action" class="fs_tags">
        <el-tag
            effect="plain"
            v-for="(tag,tagIndex) in tags"
            :key="tag.id"
            size="mini"
            @close="removeTag(tagIndex, tag)"
            :closable="creatable">
            {{ tag.title }}
        </el-tag>
        <el-popover
            v-if="creatable"
            placement="bottom"
            trigger="click"
        >
            <template #reference>
                <el-button class="fs_add_tag_icon" size="mini" icon="el-icon-plus"></el-button>
            </template>
            <div class="fs_tags_add_wrapper">
                <h3>Add Tags</h3>
                <ul v-loading="adding_tag" v-if="available_tags.length">
                    <li v-for="tag in available_tags" :key="tag.id" @click="attachTag(tag)">
                        <span class="el-icon el-icon-plus"></span>
                        {{tag.title}}
                    </li>
                </ul>
                <p v-else>No available tags found</p>
            </div>
        </el-popover>
    </div>
</template>
<script type="text/babel">
export default {
    name: 'ticketTags',
    props: ['ticket_id', 'tags', 'creatable'],
    data() {
        return {
            doing_action: false,
            adding_tag: false
        }
    },
    computed: {
        available_tags() {
            if(!this.creatable) {
                return [];
            }
            const tags = this.appVars.ticket_tags;
            const appliedTagIds = [];
            this.tags.forEach((tag) => {
                appliedTagIds.push(tag.id);
            });
            return tags.filter((tag) => {
                return appliedTagIds.indexOf(tag.id) === -1;
            });
        }
    },
    methods: {
        removeTag(tagIndex, tag) {
            this.doing_action = true;
            this.$del(`tickets/${this.ticket_id}/tags/${tag.id}`)
                .then(response => {
                    this.tags.splice(tagIndex, 1);
                })
                .catch((errors) => {
                    this.$handleError(errors);
                })
                .always(() => {
                    this.doing_action = false;
                });
        },
        attachTag(tag) {
            this.adding_tag = true;
            this.$post(`tickets/${this.ticket_id}/tags`, {
                tag_id: tag.id
            })
                .then(response => {
                    this.tags.push(tag);
                })
                .catch((errors) => {
                    this.$handleError(errors);
                })
                .always(() => {
                    this.adding_tag = false;
                });
        }
    }
}
</script>
