// Stands in for FluentCommunity's shared Element Plus (`@fluentcommunity/element-plus`);
// specs replace it with vi.mock.
export const ElCheckbox = { name: 'ElCheckbox' };
export const ElMessageBox = () => Promise.resolve();
export const ElNotification = Object.assign(() => {}, { error: () => {} });
