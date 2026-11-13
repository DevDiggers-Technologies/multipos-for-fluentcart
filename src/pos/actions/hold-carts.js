import { __ } from '@wordpress/i18n';
import database from '../services/database';
import { getCart } from './cart';
import { getCartTotal } from './cart-totals';
import { getTables } from './tables';
import { notify } from '../services/notifications';
import { getActiveCartIdFromState, getActiveCartRecordFromState, hasRecordId } from '../services/cart-session';
import { hasEntries, normalizeRecordId } from '../utils/value';
import { HOLD_CARTS } from '../state/action-types';
export { HOLD_CARTS };
const CART_TABLE = 'cart';
const TABLES_TABLE = 'tables';
const getCartTable = () => database.table(CART_TABLE);
const getTablesTable = () => database.table(TABLES_TABLE);
const getPrimaryCartRecord = currentState => getActiveCartRecordFromState(currentState);
const getActiveCartId = currentState => getActiveCartIdFromState(currentState);
const loadHoldCartRecord = holdCartId => getCartTable().where('id').equals(normalizeRecordId(holdCartId)).first();
const buildHoldCartsState = (holdCartRecords, getState) => holdCartRecords.map(holdCart => ({
    list: [holdCart],
    total: getCartTotal([holdCart], getState()),
}));
const getHoldCartTableSlug = holdCartRecord => (
    holdCartRecord && holdCartRecord.table && holdCartRecord.table.slug ? holdCartRecord.table.slug : ''
);
const getCurrentDateLabel = () => {
    const currentDate = new Date();

    return `${currentDate.toDateString()} ${currentDate.toLocaleTimeString()}`;
};
const markProductsAsHoldOrder = cartProducts => cartProducts.map(product => ({ ...product, holdOrder: 'added' }));

const notifyHoldOrderSaved = () => notify({
    title: __('Hold Order Success', 'devdiggers-multipos-for-fluentcart'),
    message: __('Your current cart is put to hold succesfully.', 'devdiggers-multipos-for-fluentcart'),
    type: 'success',
});

const buildHoldCartRecord = (currentState, info) => {
    const defaultTable = currentState.tables && currentState.tables.defaultTable ? currentState.tables.defaultTable : {};
    const activeCart = getPrimaryCartRecord(currentState);
    const currentCart = activeCart ? activeCart.cart : [];

    return {
        active_cart: 0,
        info,
        date: getCurrentDateLabel(),
        customer: currentState.customers && currentState.customers.defaultCustomer ? currentState.customers.defaultCustomer : {},
        table: defaultTable,
        cart: markProductsAsHoldOrder(currentCart),
    };
};


const updateTableAsOccupied = (defaultTable, dispatch) => {
    if (!hasEntries(defaultTable)) {
        return Promise.resolve();
    }

    return getTablesTable().where('slug').equals(defaultTable.slug).modify({ tableType: 'occupied', default: 0 }).then(() => {
        dispatch(getTables());
    });
};

export const setHoldCarts = holdCarts => {
    return {
        type: HOLD_CARTS,
        holdCarts
    }
};

const loadHoldCartProducts = () => {
    return getCartTable().where('active_cart').equals(0).toArray();
}

export const getHoldCarts = () => (dispatch, getState) => {
    return loadHoldCartProducts().then(holdCarts => {
        const holdCartObj = holdCarts.length ? buildHoldCartsState(holdCarts, getState) : [];
        dispatch(setHoldCarts(holdCartObj));
        return holdCartObj;
    });
};

export const addToHold = info => (dispatch, getState) => {
    const currentState = getState();
    const defaultTable = currentState.tables && currentState.tables.defaultTable ? currentState.tables.defaultTable : {};
    const currentCartId = getActiveCartId(currentState);
    const holdCartRecord = buildHoldCartRecord(currentState, info);

    if (!hasRecordId(currentCartId)) {
        return Promise.resolve(false);
    }

    return updateTableAsOccupied(defaultTable, dispatch).then(() => getCartTable().where('id').equals(normalizeRecordId(currentCartId)).modify(holdCartRecord)).then(() => {
        notifyHoldOrderSaved();
        dispatch(getHoldCarts());

        return true;
    });
};

export const addHoldCartToCurrentCart = holdCartId => (dispatch, getState) => {
    const currentState = getState();
    const activeCartId = hasRecordId(getActiveCartId(currentState)) ? getActiveCartId(currentState) : 99999999999;
    return getCartTable().where('id').equals(normalizeRecordId(activeCartId)).delete().then(() => {
        return getCartTable().where('id').equals(normalizeRecordId(holdCartId)).modify({ active_cart: 1 }).then(() => {
            dispatch(getHoldCarts());
            dispatch(getCart());

            return true;
        });
    });
}

export const deleteHoldCart = holdCartId => (dispatch, getState) => {
    return loadHoldCartRecord(holdCartId).then(holdCartRecord => {
        if (!holdCartRecord) {
            return false;
        }

        const tableSlug = getHoldCartTableSlug(holdCartRecord);
        const releaseTableRequest = tableSlug
            ? getTablesTable().where('slug').equals(tableSlug).modify({ tableType: 'vacant' })
            : Promise.resolve();

        return releaseTableRequest.then(() => (
            getCartTable().where('id').equals(normalizeRecordId(holdCartId)).delete()
        )).then(() => {
            dispatch(getHoldCarts());
            dispatch(getCart());
            dispatch(getTables());

            return true;
        });
    });
}
