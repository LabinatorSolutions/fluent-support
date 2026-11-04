<template>
    <el-dropdown v-if="active" ref="dropdown" v-bind="$attrs">
        <slot/>
        <template #dropdown>
            <slot name="dropdown"/>
        </template>
    </el-dropdown>
    <div
        v-else
        ref="placeholder"
        class="el-dropdown"
        :class="$attrs.class"
        @pointerenter="activate(false)"
        @focusin="activate(false)"
        @click="activate(true)"
    >
        <slot/>
    </div>
</template>

<script>
// An el-dropdown that mounts on first pointer, focus or click. Until then it renders only the trigger
// in the same wrapper. Each el-dropdown mounts a tooltip, popper and teleport (about 9 components),
// so per-row menus in long lists (one per reply in a ticket thread) were a large share of the mount cost.
// Takes the same props, events and slots as el-dropdown; use trigger="click".
export default {
    name: 'LazyDropdown',
    inheritAttrs: false,
    data() {
        return {
            active: false
        };
    },
    methods: {
        activate(open) {
            if (this.active) {
                return;
            }
            const hadFocus = this.$refs.placeholder?.contains(document.activeElement);
            this.active = true;
            this.$nextTick(() => {
                const dropdown = this.$refs.dropdown;
                if (!dropdown) {
                    return;
                }
                // The trigger was re-created: hand keyboard focus to the new one, and open the menu
                // for a click that landed before the swap (keyboard Enter/Space or touch).
                if (hadFocus || open) {
                    dropdown.$el.querySelector('button, [tabindex]')?.focus();
                }
                if (open) {
                    dropdown.handleOpen();
                }
            });
        }
    }
};
</script>
