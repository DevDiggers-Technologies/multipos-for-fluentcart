import { __ } from '@wordpress/i18n';
import database from '../services/database';
import { fetchRequest } from '../services/request';
import { saveTransactionsToStorage, getTransactions } from './transactions';
import { isInternetConnected } from '../services/connectivity';
import { notify } from '../services/notifications';
import { getPosApi, getPosConfigValue } from '../services/runtime';
import { getActiveCartRecordFromState, getFirstCartScopedRecord, hasRecordId } from '../services/cart-session';
import { getOrderBilling } from '../utils/orders';
import { getOutletId, normalizeRecordId } from '../utils/value';

import { ORDERS } from '../state/action-types';
export { ORDERS };
const ORDERS_TABLE = 'orders';
const getOrdersTable = () => database.table(ORDERS_TABLE);
const getProductsTable = () => database.products;
const getCouponsTable = () => database.coupon;
const getCartTable = () => database.cart;
const getPrimaryCartRecord = getActiveCartRecordFromState;
const getFirstStateRecord = stateSlice => (
    Array.isArray(stateSlice) && stateSlice.length ? stateSlice[0] : null
);
const getScopedStateRecord = (stateSlice, activeCartId) => (
    hasRecordId(activeCartId)
        ? getFirstCartScopedRecord(stateSlice, activeCartId)
        : getFirstStateRecord(stateSlice)
);
const buildOrdersRequest = (outletId, currentPage = 0, perPage = -1) => ({
    outlet_id: normalizeRecordId(outletId),
    per_page: perPage,
    current_page: currentPage,
});
const normalizeOrderProducts = products => Array.isArray(products) ? products : [];
const getDefaultCustomerFromState = state => (state && state.customers && state.customers.defaultCustomer) || {};
const getActiveCartProducts = cartState => {
    const activeCartRecord = getPrimaryCartRecord({ cart: cartState });
    return activeCartRecord && Array.isArray(activeCartRecord.cart) ? activeCartRecord.cart : [];
};
const getStateDefaultTable = state => state && state.tables ? state.tables.defaultTable : {};
const getOrderActiveCartId = state => {
    const activeCartRecord = getPrimaryCartRecord(state);

    return activeCartRecord ? activeCartRecord.id : null;
};
const getOrderAdjustmentListFromState = (state, stateKey, propertyName) => {
    const adjustmentRecord = getScopedStateRecord(state && state[stateKey], getOrderActiveCartId(state));

    return adjustmentRecord && Array.isArray(adjustmentRecord[propertyName]) ? adjustmentRecord[propertyName] : [];
};
const getOrderCouponsFromState = state => getOrderAdjustmentListFromState(state, 'coupon', 'coupon');
const createSaleKey = () => (
    window.crypto && window.crypto.randomUUID
        ? window.crypto.randomUUID()
        : `${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 12)}`
).replace(/[^a-z0-9-]/gi, '').toLowerCase();
const notifyOrderFailed = message => notify({
    title: __('Order Not Saved', 'devdiggers-multipos-for-fluentcart'),
    message: message || __('The order could not be saved. Check the connection and try again.', 'devdiggers-multipos-for-fluentcart'),
    type: 'danger',
});
const getOrderBillingField = (billing, field) => billing && billing[field] ? billing[field] : '';
const getOrderProductQuantity = productData => parseFloat(productData && productData.quantity) || 0;
const toStockNumber = value => {
    const stockValue = parseFloat(value);

    return Number.isNaN(stockValue) ? 0 : stockValue;
};

export const setOrders = (orders) => {
    return {
        type: ORDERS,
        orders
    }
};

const createOrdersState = orders => ({
    list: orders,
    searchTerm: '',
    searchedOrders: orders,
});
const createSearchedOrdersState = (orders, searchTerm) => ({
    list: orders,
    searchTerm,
    searchedOrders: searchTerm ? orders.filter(order => isOrderMatchingSearch(order, searchTerm)) : orders,
});

export const getOrders = outletId => dispatch => {
    return loadOnlineOrdersFromStorage().then(records => {
        if (records && records.length) {
            return loadOrdersFromStorage().then(reslt => {
                dispatch(setOrders(createOrdersState(reslt)));
                return reslt;
            });
        }

        notify({
            title: __('Loading Orders', 'devdiggers-multipos-for-fluentcart'),
            message: __('Loading Orders in the POS', 'devdiggers-multipos-for-fluentcart'),
            type: 'info'
        });

        return getOrdersRecursive(buildOrdersRequest(outletId), dispatch);
    });
}

const getOrdersFromStorage = () => getOrdersTable().toArray();

const loadOrdersFromStorage = () => getOrdersFromStorage().then(data => data ? data : false);

const loadOnlineOrdersFromStorage = () => getOrdersTable().where('order_type').equals('online').toArray().then(data => data);

const getOrdersRecursive = (postData, dispatch, countOrders = '') => (
    fetchRequest(getPosApi().GET_ORDERS_ENDPOINT, postData).then(totalOrders => {
        if (typeof totalOrders === 'number') {
            postData = buildOrdersRequest(
                postData.outlet_id,
                postData.current_page + 1,
                getPosConfigValue('per_page', -1)
            );

            if (totalOrders > 0) {
                return getOrdersRecursive(postData, dispatch, totalOrders);
            }

            return loadOrdersFromStorage().then(records => {
                notify({
                    title: __('No Orders Found', 'devdiggers-multipos-for-fluentcart'),
                    message: __('No orders have been placed yet in this outlet.', 'devdiggers-multipos-for-fluentcart'),
                    type: 'danger'
                });

                dispatch(setOrders(createOrdersState(records)));
                return true;
            });
        }

        if (!Array.isArray(totalOrders) || !totalOrders.length) {
            return true;
        }

        return getOrdersTable().bulkPut(totalOrders).then(() => loadOrdersFromStorage()).then(records => {
            dispatch(setOrders(createOrdersState(records)));

            const remainingOrders = countOrders > 0 ? countOrders - postData.per_page : 0;

            if (remainingOrders > 0) {
                const nextRequest = buildOrdersRequest(postData.outlet_id, postData.current_page + 1, postData.per_page);
                return getOrdersRecursive(nextRequest, dispatch, remainingOrders);
            }

            notify({
                title: __('Orders Loaded', 'devdiggers-multipos-for-fluentcart'),
                message: __('All Orders are loaded successfully.', 'devdiggers-multipos-for-fluentcart'),
                type: 'success'
            });

            return true;
        });
    })
);

// One payment method per order: the amount handed over is the tender.
const getPaymentMethods = paymentMethods => (
    (Array.isArray(paymentMethods) ? paymentMethods : []).filter(paymentMethod => paymentMethod.amount).slice(0, 1)
);

const buildOrderPayload = (currentState, paymentMethods) => ({
    coupons: getOrderCouponsFromState(currentState),
    customer_id: getDefaultCustomerFromState(currentState).id,
    products: getActiveCartProducts(currentState.cart),
    payment_methods: paymentMethods,
    table: getStateDefaultTable(currentState),
});

export const createOrder = (state_data, payComponent) => (dispatch, getState) => {
    const currentState = getState();
    const postOrderData = buildOrderPayload(currentState, getPaymentMethods(state_data.paymentMethods));

    if (!isInternetConnected()) {
        notifyOrderFailed(__('The POS is offline. Reconnect and try again.', 'devdiggers-multipos-for-fluentcart'));
        return Promise.resolve(null);
    }

    // One key per sale attempt, kept until it succeeds: a retried submit (after a timeout or an
    // error) is matched on the server to the order it may already have made.
    if (payComponent) {
        payComponent.saleKey = payComponent.saleKey || createSaleKey();
    }

    const postData = {
        outlet_id: getOutletId(currentState.outlet),
        order_data: JSON.stringify({ ...postOrderData, sale_key: payComponent ? payComponent.saleKey : createSaleKey() }),
    };

    return fetchRequest(getPosApi().CREATE_ORDER_ENDPOINT, postData).then(order_data => {
        if (!order_data || !order_data.order_id) {
            notifyOrderFailed(order_data && order_data.message ? order_data.message : '');
            return null;
        }

        if (payComponent) {
            payComponent.saleKey = '';
        }

        return saveTransactionsToStorage(order_data.transactions).then(() => {
            dispatch(getTransactions());
            return saveOrderToStorage(order_data);
        }).then(() => order_data);
    });
}

export const saveOrderToStorage = data => {
    return getOrdersTable().put(data).then(() => data);
}

const updateStockQuantity = (stockQuantity, quantityDelta, guardPositiveStock) => {
    // No count means the store does not track this item's stock: leave it untracked.
    if (stockQuantity === null || stockQuantity === undefined || stockQuantity === '') {
        return stockQuantity;
    }

    const currentStockQuantity = toStockNumber(stockQuantity);

    if (guardPositiveStock && currentStockQuantity <= 0) {
        return stockQuantity;
    }

    return currentStockQuantity + quantityDelta;
};

const updateProductStock = (productData, quantityDelta, guardPositiveStock = true) => (
    getProductsTable().where('product_id').equals(productData.id).modify(product => {
        product.stock = toStockNumber(product.stock) + quantityDelta;
        product.stock_quantity = updateStockQuantity(product.stock_quantity, quantityDelta, guardPositiveStock);
    })
);

const clearOrderCartState = activeCartId => (
    getCouponsTable().where("cart_id").equals(normalizeRecordId(activeCartId)).delete()
);

export const removeProductsFromCart = (products, activeCartId) => {
    return getCartTable().where("id").equals(normalizeRecordId(activeCartId)).delete().then(deletedCount => {
        if (!deletedCount) {
            return false;
        }

        const stockUpdates = normalizeOrderProducts(products).map(productData => (
            updateProductStock(productData, -getOrderProductQuantity(productData), true)
        ));

        return Promise.all(stockUpdates).then(() => clearOrderCartState(activeCartId));
    });
}

const isOrderMatchingSearch = (order, searchTerm) => {
    const normalizedSearch = String(searchTerm);
    const contains = value => value && value.toString().includes(normalizedSearch);
    const billing = getOrderBilling(order);

    return (
        contains(order.order_id) ||
        contains(getOrderBillingField(billing, 'phone')) ||
        contains(order.email) ||
        contains(getOrderBillingField(billing, 'first_name')) ||
        contains(getOrderBillingField(billing, 'last_name'))
    );
};

export const loadSearchedOrders = (search, orders) => (dispatch) => {
    dispatch(setOrders(createSearchedOrdersState(orders, search)));
}
