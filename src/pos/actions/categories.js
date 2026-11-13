import { __ } from '@wordpress/i18n';
import database from '../services/database';
import { fetchRequest } from '../services/request';
import { notify } from '../services/notifications';
import { getPosApi } from '../services/runtime';
import { normalizeRecordId } from '../utils/value';

import { CATEGORIES } from '../state/action-types';
export { CATEGORIES };
const CATEGORIES_TABLE = 'categories';
const getCategoriesTable = () => database.table(CATEGORIES_TABLE);
const normalizeCategoriesResponse = response => Array.isArray(response) ? response : [];
const buildCategoriesRequest = outletId => ({
    outlet_id: normalizeRecordId(outletId),
});

const notifyCategoriesLoading = () => notify({
    title: __('Loading Categories', 'devdiggers-multipos-for-fluentcart'),
    message: __('Loading Categories in the POS', 'devdiggers-multipos-for-fluentcart'),
    type: 'info',
});

const notifyCategoriesLoaded = () => notify({
    title: __('Categories Loaded', 'devdiggers-multipos-for-fluentcart'),
    message: __('All Categories are loaded successfully', 'devdiggers-multipos-for-fluentcart'),
    type: 'success',
});

export const setCategories = categories => {
    return {
        type: CATEGORIES,
        categories
    }
};

export const getCategories = outletId => dispatch => {
    return loadCategoriesFromStorage().then(records => {
        if (records && records.length) {
            dispatch(setCategories(records));
            return records;
        }

        notifyCategoriesLoading();

        return fetchRequest(getPosApi().GET_CATEGORIES_ENDPOINT, buildCategoriesRequest(outletId)).then(response => {
            const categories = normalizeCategoriesResponse(response);
            if (!categories.length) {
                return [];
            }

            return getCategoriesTable().bulkPut(categories).then(() => categories);
        }).then(categories => {
            if (!categories.length) {
                dispatch(setCategories([]));
                return [];
            }

            return getCategoriesFromStorage().then(dbres => {
                dispatch(setCategories(dbres));
                notifyCategoriesLoaded();

                return dbres;
            });
        });
    });
}

const getCategoriesFromStorage = () => getCategoriesTable().toArray();

const loadCategoriesFromStorage = () => getCategoriesFromStorage().then(data => data ? data : false);
