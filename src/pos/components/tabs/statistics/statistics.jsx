import React, { Component } from 'react';
import { __ } from '@wordpress/i18n';
import { connect } from 'react-redux';
import { bindActionCreators } from 'redux';
import { SearchOutlined } from '@ant-design/icons';
import { FixedSizeList as List } from "react-window";
import { getTransactions } from './../../../actions/transactions';
import { formatPrice } from '../../../utils/currency-format';
import { getOrders } from './../../../actions/orders';
import { getSettings } from './../../../actions/settings';
import { getCurrentPosDate, getPosConfig } from '../../../services/runtime';
import { getOutletId } from '../../../utils/value';
import EmptyState from '../../emptyState/emptyState';

const getTransactionMethodName = transactionMethod => {
    const paymentMethods = Object.values(getPosConfig().payment_method || {});
    const transactionPaymentMethod = paymentMethods.find(paymentMethod => paymentMethod.slug === transactionMethod);

    return transactionPaymentMethod ? transactionPaymentMethod.name : '-';
};

const getTransactionRowClassName = index => (
    index % 2 !== 0 ? 'ddfcpos-list-row ddfcpos-odd-list-row' : 'ddfcpos-list-row'
);

// Cash taken for sales today, net of any cash given back.
const getCashAmount = transactions => (Array.isArray(transactions) ? transactions : []).reduce((cashAmount, transaction) => (
    transaction.method === 'cash'
        ? cashAmount + (parseFloat(transaction.amount_in) || 0) - (parseFloat(transaction.amount_out) || 0)
        : cashAmount
), 0);

const matchesTransactionSearch = (transaction, search) => {
    const contains = value => String(value || '').toLowerCase().includes(search);

    return (
        contains(transaction.id) ||
        contains(transaction.order_id) ||
        contains(transaction.order_number) ||
        contains(transaction.method) ||
        contains(transaction.reference)
    );
};

const sortTransactionsDescending = transactions => (
    [...(Array.isArray(transactions) ? transactions : [])].sort((firstTransaction, secondTransaction) => (
        firstTransaction.id < secondTransaction.id ? 1 : (secondTransaction.id < firstTransaction.id ? -1 : 0)
    ))
);
const getFilteredTransactions = (transactions, search) => (
    search ? (Array.isArray(transactions) ? transactions : []).filter(transaction => matchesTransactionSearch(transaction, search)) : (Array.isArray(transactions) ? transactions : [])
);
const getTransactionResultLabel = transactions => `${transactions.length} ${__('Results', 'devdiggers-multipos-for-fluentcart')}`;
const getTransactionReferenceLabel = transaction => transaction.reference || '-';
const getOrdersList = ordersState => Array.isArray(ordersState && ordersState.list) ? ordersState.list : [];
// The invoice number the Orders tab and receipts show ( INV-7 ), not FluentCart's internal order ID.
const getTransactionOrderLabel = (transaction, context) => {
    if (!transaction.order_id) {
        return '-';
    }

    const order = getOrdersList(context.props.orders).find(item => String(item.id) === String(transaction.order_id));

    return `#${transaction.order_number || (order && order.order_id) || transaction.order_id}`;
};
const createTransactionRowRenderer = (transactions, context) => ({ index, style }) => {
    const transaction = transactions[index];
    const paymentMethodName = getTransactionMethodName(transaction.method);

    return (
        <div className={getTransactionRowClassName(index)} style={style}>
            <div className="ddfcpos-list-details">
                <p className="ddfcpos-transaction-id">#{transaction.id}</p>
                <p className="ddfcpos-transaction-order">{getTransactionOrderLabel(transaction, context)}</p>
                <p><mark className={`instock${parseFloat(transaction.amount_in) ? '' : ' ddfcpos-amount-zero'}`}>+ {formatPrice(transaction.amount_in)}</mark></p>
                <p><mark className={`required${parseFloat(transaction.amount_out) ? '' : ' ddfcpos-amount-zero'}`}>- {formatPrice(transaction.amount_out)}</mark></p>
                <p><span className="ddfcpos-transaction-method">{paymentMethodName}</span></p>
                <p className="ddfcpos-transaction-muted">{getTransactionReferenceLabel(transaction)}</p>
                <p className="ddfcpos-transaction-muted">{transaction.date}</p>
            </div>
        </div>
    );
};
// Both dates are in the store timezone, so compare the strings. new Date('Y-m-d') would read the day as UTC.
const getTodayOrders = orders => getOrdersList(orders).filter(order => String(order.order_created || '') >= String(getCurrentPosDate()));
const getTodaySalesTotal = orders => (Array.isArray(orders) ? orders : []).reduce((total, order) => total + (parseFloat(order.order_total) || 0), 0);

class Statistics extends Component {
    constructor(props) {
        super(props);

        this.state = {
            search: '',
        };
    }

    componentDidMount = () => {
        const outletId = getOutletId(this.props.outlet);

        this.props.getTransactions(outletId);
        this.props.getOrders(outletId);
        this.props.getSettings();
    }

    handleTransactionSearch = event => {
        this.setState({
            search: event.target.value,
        });
    }
    handleTransactionSearchInput = event => this.handleTransactionSearch(event)

    render() {
        let transactions = this.props.transactions;
        const cashAmount = getCashAmount(transactions);

        const search = this.state.search.toLowerCase();
        transactions = sortTransactionsDescending(getFilteredTransactions(transactions, search));

        const todayOrders = getTodayOrders(this.props.orders);
        const todayTotalSale = getTodaySalesTotal(todayOrders);
        const transactionRowRenderer = createTransactionRowRenderer(transactions, this);
        const rowHeight = 52;

        return (
            <div className="ddfcpos-statistics-tab-wrapper">
                <header className="ddfcpos-statistics-header">
                    <h1>{__('Statistics', 'devdiggers-multipos-for-fluentcart')}</h1>
                    <p>{__('Cash drawer and sales for this register today.', 'devdiggers-multipos-for-fluentcart')}</p>
                </header>
                <div className="ddfcpos-statistics-summary">
                    <div>
                        <h3>{__('Today\'s Cash Sale', 'devdiggers-multipos-for-fluentcart')}</h3>
                        <span>{formatPrice(cashAmount)}</span>
                    </div>
                    <div>
                        <h3>{__('Today\'s Total Sale', 'devdiggers-multipos-for-fluentcart')}</h3>
                        <span>{formatPrice(todayTotalSale)}</span>
                    </div>
                </div>

                <div className="ddfcpos-search-wrapper">
                    <h2>{__('Today\'s Transactions', 'devdiggers-multipos-for-fluentcart')}</h2>

                    <div className="ddfcpos-search-input-wrapper">
                        <div className="ddfcpos-search-input">
                            <SearchOutlined />
                            <input type="text" className="ddfcpos-form-control" value={this.state.search} placeholder={__('Search Transaction by ID or Order ID', 'devdiggers-multipos-for-fluentcart')} onChange={this.handleTransactionSearchInput} autoComplete="off" />
                        </div>
                        <span>{getTransactionResultLabel(transactions)}</span>
                    </div>
                </div>

                <div className="ddfcpos-transactions-list">
                    <div className="ddfcpos-list-heading-row">
                        <div className="ddfcpos-list-details">
                            <p>{__('Transaction ID', 'devdiggers-multipos-for-fluentcart')}</p>
                            <p>{__('Order ID', 'devdiggers-multipos-for-fluentcart')}</p>
                            <p>{__('In', 'devdiggers-multipos-for-fluentcart')}</p>
                            <p>{__('Out', 'devdiggers-multipos-for-fluentcart')}</p>
                            <p>{__('Method', 'devdiggers-multipos-for-fluentcart')}</p>
                            <p>{__('Reference', 'devdiggers-multipos-for-fluentcart')}</p>
                            <p>{__('Date', 'devdiggers-multipos-for-fluentcart')}</p>
                        </div>
                    </div>
                    {
                        transactions.length ?
                            <List
                                className="ddfcpos-list"
                                height={Math.min(800, transactions.length * rowHeight)}
                                itemCount={transactions.length}
                                itemSize={rowHeight}
                            >
                                {transactionRowRenderer}
                            </List>
                            :
                            <EmptyState
                                icon="receipt"
                                title={__('No Transactions Found', 'devdiggers-multipos-for-fluentcart')}
                                hint={__('Sales from today will appear here.', 'devdiggers-multipos-for-fluentcart')}
                            />
                    }
                </div>
            </div>
        );
    }
}

const mapStateToProps = state => ({
    transactions: state.transactions,
    orders: state.orders,
    settings: state.settings
});

const mapDispatchToProps = dispatch => bindActionCreators({ getTransactions, getOrders, getSettings }, dispatch);

export default connect(mapStateToProps, mapDispatchToProps)(Statistics);
