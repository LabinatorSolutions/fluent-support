import dayjs from 'dayjs';
import utc from 'dayjs/plugin/utc';

dayjs.extend(utc);

/**
 * A unix timestamp in UTC, e.g. "Wed, Oct 28 · 9:00 AM UTC" or, short, "Wed 9:00 AM UTC".
 *
 * @param {number} timestamp Unix seconds
 * @param {boolean} short
 * @return {string} '' when there is no timestamp
 */
export function formatUtcTime(timestamp, short = false) {
    if (!timestamp) {
        return '';
    }

    return dayjs.unix(timestamp).utc().format(short ? 'ddd h:mm A' : 'ddd, MMM D · h:mm A') + ' UTC';
}
