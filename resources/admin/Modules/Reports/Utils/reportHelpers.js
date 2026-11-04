import dayjs from 'dayjs';

/**
 * @param {Array} dateRange - [start, end]
 * @returns {string} e.g. "Mar 01 - 15 2026", or '' when invalid
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
 * @param {string|Date} date
 * @returns {string} YYYY-MM-DD, or ''
 */
export const formatDateForAPI = (date) => {
    if (!date) {
        return '';
    }
    return dayjs(date).format('YYYY-MM-DD');
};

/**
 * @param {Array} dateRange - [start, end]
 * @returns {Object} { from, to }
 */
export const formatDateRangeForAPI = (dateRange) => {
    return {
        from: (dateRange && dateRange.length > 0) ? formatDateForAPI(dateRange[0]) : '',
        to: (dateRange && dateRange.length > 1) ? formatDateForAPI(dateRange[1]) : '',
    };
};

/**
 * @param {boolean} checkAll
 * @param {Object} options - { exportOptions }
 * @returns {Object} { selectedOptions, isIndeterminate }
 */
export const handleCheckAllChange = (checkAll, options = {}) => {
    const { exportOptions = [] } = options;
    return {
        selectedOptions: checkAll ? Object.keys(exportOptions) : [],
        isIndeterminate: false,
    };
};

/**
 * @param {Array} selectedOptions
 * @param {Object} options - { exportOptions }
 * @returns {Object} { checkAll, isIndeterminate }
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
 * @param {Array} dateRange - [start, end]
 * @returns {Object} { from, to }
 */
export const getDateRangeParams = (dateRange) => {
    return formatDateRangeForAPI(dateRange);
};

/**
 * @returns {Array} [today, today] as YYYY-MM-DD
 */
export const getDefaultDateRange = () => {
    const today = dayjs().format('YYYY-MM-DD');
    return [today, today];
};


/**
 * el-date-picker `disabled-date` predicate. Reports only cover elapsed time.
 * @param {Date} date
 * @returns {boolean}
 */
export const isFutureDate = (date) => {
    return date.getTime() > Date.now();
};

/**
 * Compare two [start, end] ranges by value. Report views use it to skip a
 * `date_range` prop update they already hold, which would loop refetches.
 * @param {Array} a
 * @param {Array} b
 * @returns {boolean}
 */
export const isSameDateRange = (a, b) => {
    if (!Array.isArray(a) || !Array.isArray(b)) {
        return a === b;
    }
    return a.length === b.length && a.every((value, index) => value === b[index]);
};

/**
 * Add thousand separators to numeric stats. Non-numbers ("2 years", "--") pass through.
 * @param {number|string} value
 * @param {*} [emptyFallback] - Returned in place of an empty value
 * @returns {string|*}
 */
export const formatStatValue = (value, emptyFallback) => {
    if (value === null || value === undefined || value === "") {
        return emptyFallback === undefined ? value : emptyFallback;
    }
    if (isNaN(value)) {
        return value;
    }
    return Number(value).toLocaleString();
};
