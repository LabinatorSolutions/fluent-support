import { ElNotification } from 'element-plus';

const moment = require('moment');
require('moment/locale/en-gb');
moment.locale('en-gb');

const request = function(method, route, data = {}) {
    const url = `${window.fluentSupportAdmin.rest.url}/${route}`;

    const headers = {'X-WP-Nonce': window.fluentSupportAdmin.rest.nonce};

    if (['PUT', 'PATCH', 'DELETE'].indexOf(method.toUpperCase()) !== -1) {
        headers['X-HTTP-Method-Override'] = method;
        method = 'POST';
    }

    return window.jQuery.ajax({
        url: url,
        type: method,
        data: data,
        headers: headers
    });
}

export function useFluentHelper(){

    const { notify } = useNotify();

    const appVars = window.fluentSupportAdmin;

    function $get(route, data) {
        return request('GET', route, data)
    }

    function $post(route, data) {
        return request('POST', route, data)
    }

    function $del(route, data) {
        return request('DELETE', route, data);
    }

    function $put(route, data) {
        return request('PUT', route, data);
    }

    function $patch(route, data) {
        return request('PATCH', route, data);
    }

    function convertToText(obj) {
        const string = [];
        if (typeof (obj) === 'object' && (obj.join === undefined)) {
            for (const prop in obj) {
                string.push(convertToText(obj[prop]));
            }
        } else if (typeof (obj) === 'object' && !(obj.join === undefined)) {
            for (const prop in obj) {
                string.push(convertToText(obj[prop]));
            }
        } else if (typeof (obj) === 'function') {

        } else if (typeof (obj) === 'string') {
            string.push(obj)
        }
        return string.join('<br />')
    }

    function $t(string) {
        return window.window.fluentSupportAdmin.i18n[string] || string;

        return string;
    }

    function $saveData(key, data) {
        let existingData = window.localStorage.getItem('__fluentsupport_data');

        if (!existingData) {
            existingData = {};
        } else {
            existingData = JSON.parse(existingData);
        }

        existingData[key] = data;

        window.localStorage.setItem('__fluentsupport_data', JSON.stringify(existingData));
    }

    function  $getData(key, defaultValue = false) {
        let existingData = window.localStorage.getItem('__fluentsupport_data');
        existingData = JSON.parse(existingData);
        if (!existingData) {
            return defaultValue;
        }

        if (existingData[key]) {
            return existingData[key];
        }

        return defaultValue;

    }

    function handleError(response) {
        if (response.responseJSON) {
            response = response.responseJSON;
        }
        let errorMessage = '';
        if (typeof response === 'string') {
            errorMessage = response;
        } else if (response && response.message) {
            errorMessage = response.message;
        } else {
            errorMessage = convertToText(response);
        }
        if (!errorMessage) {
            errorMessage = 'Something is wrong!';
        }
        notify({
            type: 'error',
            title: 'Error',
            message: errorMessage,
            dangerouslyUseHTMLString: true,
            position: 'bottom-right'
        });
    }

    function convertToText(obj) {
        const string = [];
        if (typeof (obj) === 'object' && (obj.join === undefined)) {
            for (const prop in obj) {
                string.push(this.convertToText(obj[prop]));
            }
        } else if (typeof (obj) === 'object' && !(obj.join === undefined)) {
            for (const prop in obj) {
                string.push(this.convertToText(obj[prop]));
            }
        } else if (typeof (obj) === 'function') {

        } else if (typeof (obj) === 'string') {
            string.push(obj)
        }

        return string.join('<br />')
    }


    return {
        appVars,
        $get,
        $post,
        $del,
        $put,
        $patch,
        convertToText,
        $t,
        handleError,
        convertToText,
        $saveData,
        $getData,
        moment
    }
}

export function useNotify(config){

    const notify= (config)=> {
        ElNotification({
            ...config
        })
    }

    return {
        notify
    }
}