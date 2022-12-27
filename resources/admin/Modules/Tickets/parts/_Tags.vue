<template>
  <div v-if="appVars.ticket_tags.length" v-loading="doing_action" class="fs_tags">
    <el-tag
        effect="plain"
        v-for="(tag,tagIndex) in tags"
        :key="tag.id"
        size="small"
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
        <span style="cursor: pointer" class="fs_add_tag_icon el-tag el-tag--mini el-tag--plain"><el-icon><Plus /></el-icon></span>
      </template>
      <div class="fs_tags_add_wrapper">
        <h3>{{$t('Add Tags')}}</h3>
        <ul v-loading="adding_tag" v-if="available_tags.length">
          <li v-for="tag in available_tags" :key="tag.id" @click="attachTag(tag)">
            <el-icon> <Plus/> </el-icon>
            {{tag.title}}
          </li>
        </ul>
        <p v-else>{{$t('No available tags found')}}</p>
      </div>
    </el-popover>
  </div>
</template>
<script type="text/babel">
import { useFluentHelper, useNotify } from "@/admin/Composable/FluentFrameworkHelper";
import {toRefs, reactive, computed} from "vue";

export default {
  name: 'ticketTags',
  props: ['ticket_id', 'tags', 'creatable'],
  setup(props, _) {
    const {
      appVars,
      post,
      del,
      handleError,
    } = useFluentHelper();
    const { notify } = useNotify();
    const state = reactive({
      doing_action: false,
      adding_tag: false
    });

    const available_tags = computed(() => {
      if(!props.creatable) {
        return [];
      }
      const tags = appVars.ticket_tags;
      const appliedTagIds = [];
      props.tags.forEach((tag) => {
        appliedTagIds.push(parseInt(tag.id));
      });

      return tags.filter((tag) => {
        return appliedTagIds.indexOf(parseInt(tag.id)) === -1;
      });
    });

    const removeTag = (tagIndex, tag) =>{
      state.doing_action = true;
      del(`tickets/${props.ticket_id}/tags/${tag.id}`)
          .then(response => {
            props.tags.splice(tagIndex, 1);
            notify({
              message: response.message,
              type: 'success',
              position: 'bottom-right'
            });
          })
          .catch((errors) => {
            handleError(errors);
          })
          .always(() => {
            state.doing_action = false;
          });
    }

    const attachTag = (tag) => {
      state.adding_tag = true;
      post(`tickets/${props.ticket_id}/tags`, {
        tag_id: tag.id
      })
          .then(response => {
            props.tags.push(tag);
            notify({
              message: response.message,
              type: 'success',
              position: 'bottom-right'
            });
          })
          .catch((errors) => {
            handleError(errors);
          })
          .always(() => {
            state.adding_tag = false;
          });
    }

    return {
      ...toRefs(state),
      available_tags,
      removeTag,
      attachTag
    }
  }
}
</script>
