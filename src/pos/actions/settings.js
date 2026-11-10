import { __ } from '@wordpress/i18n';
import { fetchRequest } from '../services/request';
import database from '../services/database';
import { notify } from '../services/notifications';
import { getDefaultKeyboardShortcutMap } from '../services/keyboard-shortcuts';
import { getPosApi, getPosConfig } from '../services/runtime';

import { SETTINGS } from '../state/action-types';
export { SETTINGS };
const SETTINGS_TABLE = 'settings';
const isSuccessfulResponse = response => response && response.success;
const getResponseNoticeType = response => isSuccessfulResponse(response) ? 'success' : 'danger';
const getResponseNoticeTitle = response => isSuccessfulResponse(response) ? __('Success', 'multipos-for-fluentcart') : __('Error', 'multipos-for-fluentcart');
const getResponseMessage = response => response && response.message ? response.message : __('Request completed.', 'multipos-for-fluentcart');
const getStoredSettingsRecord = () => database.table(SETTINGS_TABLE).get(0);

export const setSettings = settings => {
    return {
        type: SETTINGS,
        settings
    }
};

const getDefaultSettings = () => ({
    id: 0,
    printer_width: getPosConfig().printer_width || '',
    printer_height: getPosConfig().printer_height || '',
    printer_margin: getPosConfig().printer_margin || '',
    keyboard_shortcuts: 'enabled',
    keyboard_shortcuts_map: getDefaultKeyboardShortcutMap(),
});

export const getSettings = () => dispatch => {
    return getStoredSettingsRecord().then(settingsRecord => {
        const settingsObj = {
            ...getDefaultSettings(),
            ...(settingsRecord || {}),
        };

        dispatch(setSettings(settingsObj));

        return settingsObj;
    });
};

export const saveSettings = settings => dispatch => {
    const nextSettings = {
        ...getDefaultSettings(),
        ...(settings || {}),
        id: 0,
    };

    return database.table(SETTINGS_TABLE).put(nextSettings).then(() => dispatch(getSettings()));
};

export const saveCashier = cashierData => () => {
    return fetchRequest(getPosApi().SAVE_CASHIER_ENDPOINT, { cashier_data: cashierData }).then(response => {
        notify({
            title: getResponseNoticeTitle(response),
            message: getResponseMessage(response),
            type: getResponseNoticeType(response),
        });

        return response;
    });
};
