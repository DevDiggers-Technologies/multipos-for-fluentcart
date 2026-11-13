import { __ } from '@wordpress/i18n';
import database from '../services/database';
import { fetchRequest } from '../services/request';
import { notify } from '../services/notifications';
import { getPosApi, getPosConfigValue } from '../services/runtime';
import { normalizeRecordId } from '../utils/value';

import { CUSTOMERS } from '../state/action-types';
export { CUSTOMERS };
const CUSTOMERS_TABLE = 'customers';

const getDefaultCustomer = customersList => getValidCustomersList(customersList).find(customer => customer.default) || {};
const getValidCustomersList = customersList => Array.isArray(customersList) ? customersList : [];
const getCustomersTable = () => database.table(CUSTOMERS_TABLE);
const buildCustomersRequest = (outletId, currentPage = 0, perPage = -1) => ({
    outlet_id: normalizeRecordId(outletId),
    per_page: perPage,
    current_page: currentPage,
});

const buildCustomersState = customersList => {
    const validCustomers = getValidCustomersList(customersList);

    return {
        list: validCustomers,
        defaultCustomer: getDefaultCustomer(validCustomers),
        searchTerm: '',
        searchedCustomers: validCustomers,
    };
};
const getSafeCustomersResponse = response => Array.isArray(response) ? response : [];
const getCustomerMessage = response => response && response.message ? response.message : __('Request failed.', 'devdiggers-multipos-for-fluentcart');

const notifyCustomersLoading = () => notify({
    title: __('Loading Customers', 'devdiggers-multipos-for-fluentcart'),
    message: __('Loading Customers in the POS', 'devdiggers-multipos-for-fluentcart'),
    type: 'info',
});

const notifyCustomersLoaded = () => notify({
    title: __('Customers Loaded', 'devdiggers-multipos-for-fluentcart'),
    message: __('All Customers are loaded successfully', 'devdiggers-multipos-for-fluentcart'),
    type: 'success',
});

const notifyCustomerSet = () => notify({
    title: __('Customer Set', 'devdiggers-multipos-for-fluentcart'),
    message: __('Customer is set successfully for the order.', 'devdiggers-multipos-for-fluentcart'),
    type: 'success',
});

export const setCustomers = customers => {
    return {
        type: CUSTOMERS,
        customers
    }
};

export const getCustomers = outletId => dispatch => {
    return loadCustomersFromStorage().then(records => {
        if (records && records.length) {
            dispatch(setCustomers(buildCustomersState(records)));
            return records;
        }

        notifyCustomersLoading();

        return fetchCustomersRecursive(buildCustomersRequest(outletId), dispatch);
    });
}

const getCustomersFromStorage = () => getCustomersTable().toArray();

const loadCustomersFromStorage = () => getCustomersFromStorage().then(data => data ? data : false);
const getCurrentOutletId = getState => {
    const state = getState();

    return state && state.outlet ? state.outlet.id : '';
};
const refreshCustomersForCurrentOutlet = (dispatch, getState) => dispatch(getCustomers(getCurrentOutletId(getState)));

const refreshCustomersFromStorage = dispatch => loadCustomersFromStorage().then(dbres => {
    dispatch(setCustomers(buildCustomersState(dbres)));
    return dbres;
});
const normalizeCustomerRecordId = customerId => normalizeRecordId(customerId);

const resolveCustomerBatchRequest = (postData, dispatch, remainingCustomers = '') => {
    return fetchRequest(getPosApi().GET_CUSTOMERS_ENDPOINT, postData).then(customerBatch => {
        if (typeof customerBatch === 'number') {
            const paginatedRequest = buildCustomersRequest(
                postData.outlet_id,
                postData.current_page + 1,
                getPosConfigValue('per_page', -1)
            );

            if (customerBatch > 0) {
                return fetchCustomersRecursive(paginatedRequest, dispatch, customerBatch);
            }

            return refreshCustomersFromStorage(dispatch).then(() => true);
        }

        const customersBatch = getSafeCustomersResponse(customerBatch);

        if (customersBatch.length > 0) {
            return getCustomersTable().bulkPut(customersBatch).then(() => {
                return refreshCustomersFromStorage(dispatch).then(() => {
                    const remainingCount = remainingCustomers > 0 ? remainingCustomers - postData.per_page : 0;

                    if (remainingCount > 0) {
                        const nextRequest = buildCustomersRequest(
                            postData.outlet_id,
                            postData.current_page + 1,
                            postData.per_page
                        );

                        return fetchCustomersRecursive(nextRequest, dispatch, remainingCount);
                    }

                    notifyCustomersLoaded();
                    return true;
                });
            });
        }

        return false;
    });
};

const fetchCustomersRecursive = (postData, dispatch, remainingCustomers = '') => {
    return resolveCustomerBatchRequest(postData, dispatch, remainingCustomers);
};
const findCustomersBySearch = search => (
    getCustomersTable()
        .where('first_name').startsWithIgnoreCase(search)
        .or('last_name').startsWithIgnoreCase(search)
        .or('email').startsWithIgnoreCase(search)
        .or('phone').startsWithIgnoreCase(search)
        .or('username').startsWithIgnoreCase(search)
        .or('display_name').startsWithIgnoreCase(search)
        .toArray()
);

export const loadSearchCustomers = (search, allCustomers, defaultCustomer) => (dispatch) => {
    const customersState = allCustomers || {};
    const customersList = getValidCustomersList(customersState.list);

    if (!search) {
        dispatch(setCustomers({
            ...buildCustomersState(customersList),
            defaultCustomer,
            searchTerm: '',
            searchedCustomers: customersList
        }));

        return Promise.resolve(customersList);
    }

    return findCustomersBySearch(search).then(customerData => {
        dispatch(setCustomers({
            ...buildCustomersState(customersList),
            defaultCustomer,
            searchTerm: search,
            searchedCustomers: customerData
        }));

        return customerData;
    });
}

export const updateDefaultCustomer = customerId => (dispatch, getState) => {
    const normalizedCustomerId = normalizeCustomerRecordId(customerId);

    return getCustomersTable().toCollection().modify(function (obj) {
        obj.default = 0;
    }).then(() => getCustomersTable().update(normalizedCustomerId, {
        default: 1
    })).then(updatedCount => {
        if (!updatedCount) {
            return false;
        }

        refreshCustomersForCurrentOutlet(dispatch, getState);
        notifyCustomerSet();

        return true;
    });
}

export const saveCustomer = (customerData, manageCustomerComponent = '') => (dispatch, getState) => {
    const postData = {
        customer_data: customerData,
        outlet_id: normalizeRecordId(getCurrentOutletId(getState)),
    };

    return fetchRequest(getPosApi().MANAGE_CUSTOMER_ENDPOINT, postData).then(response => {
        if (!response.success) {
            notify({
                title: __('Error', 'devdiggers-multipos-for-fluentcart'),
                message: getCustomerMessage(response),
                type: 'danger',
            });

            return false;
        }

        return getCustomersTable().put(response.data).then(customerRecordId => {
            if (customerRecordId === null || customerRecordId === undefined) {
                return false;
            }

            if (manageCustomerComponent) {
                manageCustomerComponent.props.handleResetEditCustomer();
            }

            notify({
                title: __('Success', 'devdiggers-multipos-for-fluentcart'),
                message: getCustomerMessage(response),
                type: 'success',
            });

            refreshCustomersForCurrentOutlet(dispatch, getState);

            return response.data;
        });
    });
}

export const deleteCustomer = customerId => (dispatch, getState) => {
    return fetchRequest(getPosApi().DELETE_CUSTOMER_ENDPOINT, { customer_id: customerId }).then(response => {
        if (!response || String(response) !== String(customerId)) {
            return false;
        }

        return getCustomersTable().where('id').equals(normalizeCustomerRecordId(customerId)).delete().then(deletedCount => {
            notify({
                title: __('Success', 'devdiggers-multipos-for-fluentcart'),
                message: __('Customer deleted successfully.', 'devdiggers-multipos-for-fluentcart'),
                type: 'success',
            });

            refreshCustomersForCurrentOutlet(dispatch, getState);

            return deletedCount;
        });
    });
}
