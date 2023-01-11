const moment = require('moment');
moment.locale('en-gb');
const appStartTime = new Date();

export function dateTimeHelper() {
     function humanDiffTime(date) {
        const dateString = (date === undefined) ? null : date;
        if (!dateString) {
            return '';
        }
        const endTime = new Date();
        const timeDiff = endTime - appStartTime; // in ms
        const dateObj = moment(dateString);
        return dateObj.from(moment(window.fluentSupportAdmin.server_time).add(timeDiff, 'milliseconds'));
    }

    function dateTimeFormat(date, format) {
        const dateString = (date === undefined) ? null : date;
        const dateObj = moment(dateString);
        return dateObj.isValid() ? dateObj.format(format) : null;
    }
    function localDate(date) {
        return moment.utc(date).local();
    }
    function longLocalDate(date) {
        return this.dateTimeFormat(
            date, 'ddd, DD MMM, YYYY'
        );
    }

    return {
        humanDiffTime,
        dateTimeFormat,
        localDate,
        longLocalDate
    }
}
