import dayjs from 'dayjs';

/**
 * Format date range for display in UI
 * @param {Array} dateRange - Array of two date strings [start, end]
 * @returns {string} Formatted date range string or empty string
 */
export const formatDateRangeForDisplay = (dateRange) => {
    if (!dateRange || dateRange.length !== 2) {
        return '';
    }
    if (!dateRange[0] || !dateRange[1]) {
        return '';
    }
    const start = dayjs(dateRange[0]);
    const end = dayjs(dateRange[1]);
    if (!start.isValid() || !end.isValid()) {
        return '';
    }
    if (start.format('MMM') === end.format('MMM') && start.format('YYYY') === end.format('YYYY')) {
        return `${start.format('MMM DD')} - ${end.format('DD YYYY')}`;
    }
    return `${start.format('MMM DD')} - ${end.format('MMM DD YYYY')}`;
};

/**
 * Format a single date for API calls
 * @param {string|Date} date - Date to format
 * @returns {string} Formatted date string (YYYY-MM-DD) or empty string
 */
export const formatDateForAPI = (date) => {
    if (!date) {
        return '';
    }
    return dayjs(date).format('YYYY-MM-DD');
};

/**
 * Format date range for API calls
 * @param {Array} dateRange - Array of two date strings [start, end]
 * @returns {Object} Object with from and to properties
 */
export const formatDateRangeForAPI = (dateRange) => {
    return {
        from: (dateRange && dateRange.length > 0) ? formatDateForAPI(dateRange[0]) : '',
        to: (dateRange && dateRange.length > 1) ? formatDateForAPI(dateRange[1]) : '',
    };
};

/**
 * Handle check all checkbox change
 * @param {boolean} checkAll - Whether all items should be checked
 * @param {Object} options - Options object containing exportOptions
 * @returns {Object} Object with selectedOptions and isIndeterminate
 */
export const handleCheckAllChange = (checkAll, options = {}) => {
    const { exportOptions = [] } = options;
    return {
        selectedOptions: checkAll ? Object.keys(exportOptions) : [],
        isIndeterminate: false,
    };
};

/**
 * Handle individual column checkbox changes
 * @param {Array} selectedOptions - Currently selected options
 * @param {Object} options - Options object containing exportOptions
 * @returns {Object} Object with checkAll and isIndeterminate
 */
export const handleColumnChanges = (selectedOptions, options = {}) => {
    const { exportOptions = [] } = options;
    const totalOptions = Object.keys(exportOptions).length;
    
    if (selectedOptions.length === totalOptions) {
        return {
            checkAll: true,
            isIndeterminate: false,
        };
    } else if (selectedOptions.length === 0) {
        return {
            checkAll: false,
            isIndeterminate: false,
        };
    } else {
        return {
            checkAll: false,
            isIndeterminate: true,
        };
    }
};

/**
 * Get formatted date range parameters for API request
 * @param {Array} dateRange - Array of two date strings [start, end]
 * @returns {Object} Object with from and to date strings
 */
export const getDateRangeParams = (dateRange) => {
    return formatDateRangeForAPI(dateRange);
};

/**
 * Get default 7-day date range (last 7 days including today)
 * @returns {Array} Array of two date strings [start, end] in YYYY-MM-DD format
 */
export const getDefaultDateRange = () => {
    return [
        dayjs().subtract(6, 'day').format('YYYY-MM-DD'), // 7 days ago (including today = 7 days)
        dayjs().format('YYYY-MM-DD') // Today
    ];
};

