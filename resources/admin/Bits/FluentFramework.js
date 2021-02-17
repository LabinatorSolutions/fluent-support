import app from '@/admin/Bits/elements';
// import Rest from '@/admin/Bits/Rest';

// import {
//     applyFilters,
//     addFilter,
//     addAction,
//     doAction,
//     removeAllActions
// } from '@wordpress/hooks';

// const moment = require('moment');
// require('moment/locale/en-gb');
// moment.locale('en-gb');

export default class FluentFramework {
    constructor() {
        // this.doAction = doAction;
        // this.addFilter = addFilter;
        // this.addAction = addAction;
        // this.applyFilters = applyFilters;
        // this.removeAllActions = removeAllActions;
        //
        // this.$rest = Rest;
        // this.appVars = window.fluentSupportAdmin;
        // this.app = this.extendVueConstructor();
        this.app = app
    }

    extendVueConstructor() {

        return app;
        const self = this;

        app.mixin({
            data() {
                return {
                    appVars: self.appVars
                }
            },
            methods: {
                addFilter,
                applyFilters,
                doAction,
                addAction,
                removeAllActions,
                longLocalDate: self.longLocalDate,
                longLocalDateTime: self.longLocalDateTime,
                dateTimeFormat: self.dateTimeFormat,
                localDate: self.localDate,
                formatMoney: self.formatMoney,
                ucFirst: self.ucFirst,
                ucWords: self.ucWords,
                slugify: self.slugify,
                moment: moment
            },
            computed: {
                totalExpense() {
                    return window.fluentSupportAdmin.total;
                },
                firstEntry: {
                    get() {
                        return window.fluentSupportAdmin.firstEntry;
                    },
                    set(value) {
                        window.fluentSupportAdmin.firstEntry = value;
                    }
                },
                lastEntry: {
                    get() {
                        return window.fluentSupportAdmin.lastEntry;
                    },
                    set(value) {
                        window.fluentSupportAdmin.lastEntry = value;
                    }
                }
            }
        });

        // app.filter('dateTimeFormat', self.dateTimeFormat);
        // app.filter('ucFirst', self.ucFirst);
        // app.filter('ucWords', self.ucWords);
        // app.filter('money', (val) => {
        //     return val;
        // });

        return app;
    }

    registerBlock(blockLocation, blockName, block) {
        this.addFilter(blockLocation, this.appVars.slug, function (components) {
            components[blockName] = block;
            return components;
        });
    }

    registerTopMenu(title, route) {
        if (!title || !route.name || !route.path || !route.component) {
            return;
        }

        this.addFilter('fluent_framework_top_menus', this.appVars.slug, function (menus) {
            menus = menus.filter(m => m.route !== route.name);
            menus.push({
                route: route.name,
                title: title
            });
            return menus;
        });

        this.addFilter('fluent_framework_global_routes', this.appVars.slug, function (routes) {
            routes = routes.filter(r => r.name !== route.name);
            routes.push(route);
            return routes;
        });
    }

    $get(url, options = {}) {
        return window.FluentFramework.$rest.get(url, options);
    }

    $post(url, options = {}) {
        return window.FluentFramework.$rest.post(url, options);
    }

    $del(url, options = {}) {
        return window.FluentFramework.$rest.delete(url, options);
    }

    $put(url, options = {}) {
        return window.FluentFramework.$rest.put(url, options);
    }

    $patch(url, options = {}) {
        return window.FluentFramework.$rest.patch(url, options);
    }

    dateTimeFormat(date, format) {
        const dateString = (date === undefined) ? null : date;
        const dateObj = moment(dateString);
        return dateObj.isValid() ? dateObj.format(format) : null;
    }

    localDate(date) {
        return moment.utc(date).local();
    }

    longLocalDate(date) {
        return this.dateTimeFormat(
            date, 'ddd, DD MMM, YYYY'
        );
    }

    longLocalDateTime(date) {
        return this.dateTimeFormat(
            date, 'ddd, DD MMM, YYYY hh:mm:ssa'
        );
    }

    ucFirst(text) {
        return text[0].toUpperCase() + text.slice(1).toLowerCase();
    }

    ucWords(text) {
        return (text + '').replace(/^(.)|\s+(.)/g, function ($1) {
            return $1.toUpperCase();
        })
    }

    slugify(text) {
        return text.toString().toLowerCase()
            .replace(/\s+/g, '-') // Replace spaces with -
            .replace(/[^\w\\-]+/g, '') // Remove all non-word chars
            .replace(/\\-\\-+/g, '-') // Replace multiple - with single -
            .replace(/^-+/, '') // Trim - from start of text
            .replace(/-+$/, ''); // Trim - from end of text
    }
}
