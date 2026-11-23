export function hasAdvancedFilterValue(value) {
    if (Array.isArray(value)) {
        return value.some(hasAdvancedFilterValue);
    }

    if (typeof value === 'string') {
        return value.trim() !== '';
    }

    return typeof value === 'number' && Number.isFinite(value);
}

export function countAdvancedFilterData(groups) {
    if (!Array.isArray(groups)) {
        return 0;
    }

    return groups.reduce((count, group) => {
        if (!Array.isArray(group)) {
            return count;
        }

        return count + group.filter((filter) => {
            return filter && hasAdvancedFilterValue(filter.value);
        }).length;
    }, 0);
}
