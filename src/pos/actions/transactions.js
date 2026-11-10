import { __ } from '@wordpress/i18n';
import database from '../services/database';
import { fetchRequest } from '../services/request';
import { notify } from '../services/notifications';
import { getCurrentPosDate, getPosApi } from '../services/runtime';

import { TRANSACTIONS } from '../state/action-types';
export { TRANSACTIONS };
const TRANSACTIONS_TABLE = 'transactions';
const getTransactionsStore = () => database.table(TRANSACTIONS_TABLE);
const normalizeTransactions = response => Array.isArray(response) ? response : [];
const getTransactionsForDate = transactionDate => (
    getTransactionsStore().where('date').startsWithIgnoreCase(transactionDate).toArray()
);
const setEmptyTransactions = dispatch => {
    dispatch(setTransactions([]));
    return [];
};
const fetchRemoteTransactions = (outletId, transactionDate) => (
    fetchRequest(getPosApi().GET_TRANSACTIONS_ENDPOINT, { outlet_id: outletId, date: transactionDate })
);

const notifyTransactionsLoading = () => notify({
    title: __('Loading Transactions', 'multipos-for-fluentcart'),
    message: __('Loading Transactions in the POS', 'multipos-for-fluentcart'),
    type: 'info',
});

const notifyTransactionsLoaded = () => notify({
    title: __('Transactions Loaded', 'multipos-for-fluentcart'),
    message: __('All Transactions are loaded successfully.', 'multipos-for-fluentcart'),
    type: 'success',
});

const notifyTransactionsEmpty = () => notify({
    title: __('No Transaction Found', 'multipos-for-fluentcart'),
    message: __('No transaction has been generated for today yet in this outlet.', 'multipos-for-fluentcart'),
    type: 'danger',
});

export const setTransactions = (transactions) => {
    return {
        type: TRANSACTIONS,
        transactions
    }
};

const setTransactionsAndNotifyLoaded = (dispatch, transactions) => {
    dispatch(setTransactions(transactions));
    notifyTransactionsLoaded();
    return transactions;
};
const getTransactionDateFilter = () => getCurrentPosDate();
const hasTransactions = response => normalizeTransactions(response).length > 0;

export const getTransactions = (outletId = '') => dispatch => {
    const transactionDate = getTransactionDateFilter();

    return getTransactionsForDate(transactionDate).then(result => {
        if (result.length <= 0) {
            notifyTransactionsLoading();

            return fetchRemoteTransactions(outletId, transactionDate).then(response => {
                const transactions = normalizeTransactions(response);

                if (hasTransactions(transactions)) {
                    return saveTransactionsToStorage(transactions).then(() => setTransactionsAndNotifyLoaded(dispatch, transactions));
                }

                notifyTransactionsEmpty();
                return setEmptyTransactions(dispatch);
            });
        }

        dispatch(setTransactions(result));
        return result;
    });
}

export const saveTransactionsToStorage = data => {
    const transactions = normalizeTransactions(data);

    if (!transactions.length) {
        return Promise.resolve([]);
    }

    return getTransactionsStore().bulkPut(transactions).then(() => transactions);
}
