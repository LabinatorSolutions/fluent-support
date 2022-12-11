import { ElNotification } from 'element-plus';
const moment = require('moment');
require('moment/locale/en-gb');
moment.locale('en-gb');

import { dateTimeHelper } from "@/admin/Composable/dateTimeHelper";
import { useRestApi } from '@/admin/Composable/Rest';

export function useFluentHelper(){
    const { humanDiffTime, dateTimeFormat, localDate, longLocalDate } = dateTimeHelper();
    const { get, post, del, put, patch} = useRestApi();
    const { notify } = useNotify();
    const appVars = window.fluentSupportAdmin;
    const has_pro = window.fluentSupportAdmin.has_pro;

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

    function translate(string) {
        return window.window.fluentSupportAdmin.i18n[string] || string;

        return string;
    }

    function ucFirst(text) {
        return text[0].toUpperCase() + text.slice(1).toLowerCase();
    }

    function ucWords(text) {
        return (text + '').replace(/^(.)|\s+(.)/g, function ($1) {
            return $1.toUpperCase();
        })
    }

    //Store in local storage
    function saveData(key, data) {
        let existingData = window.localStorage.getItem('__fluentsupport_data');

        if (!existingData) {
            existingData = {};
        } else {
            existingData = JSON.parse(existingData);
        }

        existingData[key] = data;

        window.localStorage.setItem('__fluentsupport_data', JSON.stringify(existingData));
    }

    //Get from local storage
    function  getData(key, defaultValue = false) {
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

    //Error handler
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

    function setTitle(title) {
        document.title = title;
    }

    return {
        appVars,
        get,
        post,
        del,
        put,
        patch,
        convertToText,
        translate,
        ucFirst,
        ucWords,
        handleError,
        saveData,
        getData,
        moment,
        humanDiffTime,
        dateTimeFormat,
        localDate,
        longLocalDate,
        setTitle,
        has_pro
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
