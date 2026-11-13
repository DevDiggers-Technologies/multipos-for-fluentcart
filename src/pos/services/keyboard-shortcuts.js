import { getPosRoute } from './routes';
import { __ } from '@wordpress/i18n';

export const POS_KEYBOARD_SHORTCUTS = [
    { id: 'home', defaultShortcut: 'Alt+1', label: __('Home', 'devdiggers-multipos-for-fluentcart'), route: () => getPosRoute() },
    { id: 'customers', defaultShortcut: 'Alt+2', label: __('Customers', 'devdiggers-multipos-for-fluentcart'), route: () => getPosRoute('/customers') },
    { id: 'tables', defaultShortcut: 'Alt+3', label: __('Tables', 'devdiggers-multipos-for-fluentcart'), route: () => getPosRoute('/tables/all') },
    { id: 'orders', defaultShortcut: 'Alt+4', label: __('Orders', 'devdiggers-multipos-for-fluentcart'), route: () => getPosRoute('/orders/online') },
    { id: 'statistics', defaultShortcut: 'Alt+5', label: __('Statistics', 'devdiggers-multipos-for-fluentcart'), route: () => getPosRoute('/statistics') },
    { id: 'settings', defaultShortcut: 'Alt+6', label: __('Settings', 'devdiggers-multipos-for-fluentcart'), route: () => getPosRoute('/settings') },
    { id: 'cart', defaultShortcut: 'Alt+7', label: __('Cart', 'devdiggers-multipos-for-fluentcart'), route: () => getPosRoute('/cart') },
    { id: 'checkout', defaultShortcut: 'Alt+8', label: __('Checkout', 'devdiggers-multipos-for-fluentcart'), route: () => getPosRoute('/pay') },
];

const NUMBER_SHORTCUT_KEYS = ['1', '2', '3', '4', '5', '6', '7', '8', '9'];
const SHORTCUT_KEYS = [...NUMBER_SHORTCUT_KEYS, ...'ABCDEFGHIJKLMNOPQRSTUVWXYZ'.split('')];
const getShortcutOptions = () => [
    ...SHORTCUT_KEYS.map(key => `Alt+${key}`),
    ...NUMBER_SHORTCUT_KEYS.map(key => `Ctrl+Shift+${key}`),
];

export const KEYBOARD_SHORTCUT_OPTIONS = getShortcutOptions();

export const getDefaultKeyboardShortcutMap = () => POS_KEYBOARD_SHORTCUTS.reduce((shortcuts, action) => ({
    ...shortcuts,
    [action.id]: action.defaultShortcut,
}), {});

export const getKeyboardShortcutMap = settings => ({
    ...getDefaultKeyboardShortcutMap(),
    ...(settings && settings.keyboard_shortcuts_map ? settings.keyboard_shortcuts_map : {}),
});

const isMac = () => typeof navigator !== 'undefined' && /Mac|iPhone|iPad|iPod/.test(navigator.platform || navigator.userAgent || '');

export const getKeyboardShortcutDisplay = shortcut => {
    if (!shortcut) {
        return __('Unassigned', 'devdiggers-multipos-for-fluentcart');
    }

    return shortcut
        .replace('Alt', isMac() ? 'Option' : 'Alt')
        .replace('Ctrl', isMac() ? 'Control' : 'Ctrl')
        .replace(/\+/g, ' + ');
};

const isEditableTarget = target => {
    if (!target) {
        return false;
    }

    const tagName = target.tagName ? target.tagName.toLowerCase() : '';

    return target.isContentEditable || ['input', 'select', 'textarea'].includes(tagName);
};

const getEventShortcut = event => {
    let modifier = '';

    if (event.altKey && !event.ctrlKey && !event.metaKey && !event.shiftKey) {
        modifier = 'Alt';
    } else if (event.ctrlKey && event.shiftKey && !event.altKey && !event.metaKey) {
        modifier = 'Ctrl+Shift';
    } else {
        return '';
    }

    if (event.code && event.code.startsWith('Digit')) {
        return `${modifier}+${event.code.replace('Digit', '')}`;
    }

    if (event.code && event.code.startsWith('Key')) {
        return `${modifier}+${event.code.replace('Key', '')}`;
    }

    return '';
};

export const getKeyboardShortcutRoute = (event, settings) => {
    if (isEditableTarget(event.target)) {
        return '';
    }

    const eventShortcut = getEventShortcut(event);
    const shortcutMap = getKeyboardShortcutMap(settings);
    const shortcut = POS_KEYBOARD_SHORTCUTS.find(item => shortcutMap[item.id] === eventShortcut);

    return shortcut ? shortcut.route() : '';
};
