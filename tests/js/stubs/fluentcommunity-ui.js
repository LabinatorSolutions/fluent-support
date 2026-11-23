// Stands in for FluentCommunity's shared UI runtime (`@fluentcommunity/ui`): like the real
// one, it hands out the hooks start.js put on window.FluentCommunityUtil.
export const hooks = (window.FluentCommunityUtil || {}).hooks;
