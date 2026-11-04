export const timesheetUtils = {
    /**
     * Total billable time for one person across all dates.
     * @param {Array} dateLabels
     * @param {Object} timeSheets - Keyed by date, then by id
     * @param {Number} id
     * @returns {string}
     */
    calculateEntityTotalMinutes: (dateLabels, timeSheets, id) => {
        const minutes = dateLabels.reduce((acc, date) => {
            const sheets = timeSheets?.[date]?.[id] || [];
            return acc + sheets.reduce((acc, sheet) => acc + sheet.billable_minutes, 0);
        }, 0);
        return timesheetUtils.formatMinutes(minutes);
    },

    /**
     * @param {Object} timeSheets
     * @param {string} date
     * @param {Number} id
     * @returns {string}
     */
    calculateTimeSheetTotal: (timeSheets, date, id) => {
        const sheets = timeSheets?.[date]?.[id] ?? [];
        const minutes = sheets.reduce((acc, sheet) => acc + sheet.billable_minutes, 0);
        return timesheetUtils.formatMinutes(minutes);
    },

    /**
     * @param {Number} minutes
     * @returns {string} e.g. "1h 5m", or "--" for zero
     */
    formatMinutes: (minutes) => {
        let intMinutes = parseInt(minutes);
        if (!intMinutes) return '--';

        const hours = Math.floor(intMinutes / 60);
        intMinutes %= 60;

        if (!hours) return `${intMinutes}m`;
        if (!intMinutes) return `${hours}h`;
        return `${hours}h ${intMinutes}m`;
    },

    /**
     * Download the report by navigating to the admin-ajax export URL.
     * @param {Object} config
     * @param {Array} config.selectedItems
     * @param {Array} config.dateRange
     * @param {string} config.action - admin-ajax action
     * @param {string} config.itemKey - Query key for selectedItems. mailbox_id is omitted when empty.
     * @returns {void}
     */
    exportReport: (config) => {
        const { selectedItems, dateRange, action, itemKey } = config;

        const formattedDateRange = Array.isArray(dateRange) && dateRange.length === 2 
            ? dateRange.join(',') 
            : (dateRange || '');
        
        let formattedSelectedItems;
        if (Array.isArray(selectedItems)) {
            formattedSelectedItems = selectedItems.length > 0 
                ? selectedItems.join(',') 
                : (itemKey === 'mailbox_id' ? null : 'null');
        } else if (selectedItems === null || selectedItems === undefined || selectedItems === '') {
            formattedSelectedItems = (itemKey === 'mailbox_id') ? null : 'null';
        } else {
            formattedSelectedItems = String(selectedItems);
        }

        const queryParams = new URLSearchParams({
            action,
            _wpnonce: window.fluentSupportAdmin.nonce,
        });

        queryParams.append('dateRange', formattedDateRange);
        if (formattedSelectedItems !== null) {
            queryParams.append(itemKey, formattedSelectedItems);
        }

        location.href = `${window.fluentSupportAdmin.ajaxurl}?${queryParams.toString()}`;
    }
};
