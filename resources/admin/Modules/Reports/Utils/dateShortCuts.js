import dayjs from 'dayjs';

const shortcuts = [
  {
    text: "Today",
    value: () => {
      const start = dayjs().startOf('day').toDate();
      const end = dayjs().endOf('day').toDate();
      return [start, end];
    },
  },
  {
    text: "This Week",
    value: () => {
      const start = dayjs().startOf('week').toDate();
      const end = dayjs().endOf('week').toDate();
      return [start, end];
    },
  },
  {
    text: "Last Week",
    value: () => {
      const start = dayjs().subtract(1, 'week').startOf('week').toDate();
      const end = dayjs().subtract(1, 'week').endOf('week').toDate();
      return [start, end];
    },
  },
  {
    text: "Last Month",
    value: () => {
      const start = dayjs().subtract(1, 'month').startOf('month').toDate();
      const end = dayjs().subtract(1, 'month').endOf('month').toDate();
      return [start, end];
    },
  },
  {
    text: "Last 3 Months",
    value: () => {
      const start = dayjs().subtract(3, 'month').startOf('month').toDate();
      const end = dayjs().endOf('day').toDate();
      return [start, end];
    },
  },
  {
      text: "Last 6 Months",
      value: () => {
        const start = dayjs().subtract(6, 'month').startOf('month').toDate();
        const end = dayjs().endOf('day').toDate();
        return [start, end];
      },
  },
  {
      text: "Last 1 Year",
      value: () => {
        const start = dayjs().subtract(1, 'year').startOf('day').toDate();
        const end = dayjs().endOf('day').toDate();
        return [start, end];
      },
  },
];

const additionalShortcuts = [
  {
    text: "Today",
    value: () => {
      const start = dayjs().startOf('day').toDate();
      const end = dayjs().endOf('day').toDate();
      return [start, end];
    },
  },
  {
    text: "Yesterday",
    value: () => {
      const start = dayjs().subtract(1, 'day').startOf('day').toDate();
      const end = dayjs().subtract(1, 'day').endOf('day').toDate();
      return [start, end];
    },
  },
  {
    text: "This Week",
    value: () => {
      const start = dayjs().startOf('week').toDate();
      const end = dayjs().endOf('week').toDate();
      return [start, end];
    },
  },
  {
    text: "Last 7 Days",
    value: () => {
      const start = dayjs().subtract(6, 'day').startOf('day').toDate();
      const end = dayjs().endOf('day').toDate();
      return [start, end];
    },
  },
  {
    text: "Last 2 Weeks",
    value: () => {
      const start = dayjs().subtract(2, 'week').startOf('week').toDate();
      const end = dayjs().subtract(1, 'week').endOf('week').toDate();
      return [start, end];
    },
  },
  {
    text: "This Month",
    value: () => {
      const start = dayjs().startOf('month').toDate();
      const end = dayjs().endOf('month').toDate();
      return [start, end];
    },
  },
  {
    text: "Last Month",
    value: () => {
      const start = dayjs().subtract(1, 'month').startOf('month').toDate();
      const end = dayjs().subtract(1, 'month').endOf('month').toDate();
      return [start, end];
    },
  },
];

export { shortcuts, additionalShortcuts };
