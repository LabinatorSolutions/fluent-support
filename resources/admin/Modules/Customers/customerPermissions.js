// Deletion cascades into tickets, conversations and attachments, so the API
// restricts it to administrators unless the customer_delete_requires_admin filter
// says otherwise. Mirror that so agents are not shown buttons that 403.
export function canDeleteCustomers(appVars) {
    const permissions = appVars?.me?.permissions || [];

    if (!permissions.includes('fst_sensitive_data')) {
        return false;
    }

    if (!appVars?.customer_delete_requires_admin) {
        return true;
    }

    return permissions.includes('administrator');
}
