import React, { Component, Fragment } from 'react';
import Select from '../../select/select';
import EmptyState from '../../emptyState/emptyState';
import { connect } from 'react-redux';
import { bindActionCreators } from 'redux';
import { __, sprintf } from '@wordpress/i18n';
import { ArrowLeftOutlined, PrinterOutlined } from '@ant-design/icons';
import { getCart, addNewCart } from './../../../actions/cart';
import { formatPrice, toFixed } from '../../../utils/currency-format';
import { getPosRoute } from '../../../services/routes';
import { createOrder, getOrders, removeProductsFromCart } from './../../../actions/orders';
import { getProducts } from '../../../actions/products';
import { getCoupons } from './../../../actions/coupon';
import { getCustomers } from './../../../actions/customers';
import { printInvoice } from './../../../actions/invoice';
import Popup from './../../popup/popup.jsx';
import { getActiveCartRecordFromState } from '../../../services/cart-session';
import { getPosConfig, getPosObject } from '../../../services/runtime';
import { pushRoute } from '../../../utils/navigation';
import { getOutletId } from '../../../utils/value';

const getFirstEnabledPaymentMethod = () => {
    const enabledMethods = getEnabledPaymentMethodOptions();

    return enabledMethods.find(paymentMethod => paymentMethod.status === 'enabled') || {};
};

const createPaymentMethodState = () => {
    const paymentMethod = getFirstEnabledPaymentMethod();

    return {
        amount: '',
        slug: paymentMethod.slug,
        name: paymentMethod.name,
        active: 1,
    };
};

const getSuggestedAmounts = totalPayLeft => {
    const suggestions = [totalPayLeft];
    let currentSuggestion = Math.ceil(totalPayLeft);
    let increase = 5;

    while (currentSuggestion === suggestions[suggestions.length - 1] && totalPayLeft > 0) {
        currentSuggestion = Math.ceil(totalPayLeft / increase) * increase;
        increase += 5;
    }

    suggestions.push(currentSuggestion);

    while (suggestions.length < 4) {
        increase = 5;
        currentSuggestion = suggestions[suggestions.length - 1];

        while (currentSuggestion === suggestions[suggestions.length - 1] && totalPayLeft > 0) {
            currentSuggestion = Math.ceil(suggestions[suggestions.length - 1] / increase) * increase;
            increase += 5;
        }

        suggestions.push(currentSuggestion);
    }

    return suggestions;
};

const getEnabledPaymentMethodOptions = () => (
    Object.values(getPosConfig().payment_method || {})
        .filter(method => method.status === 'enabled')
);
const getPaymentMethods = paymentMethods => Array.isArray(paymentMethods) ? paymentMethods : [];
const getCartTotalAmount = cartState => parseFloat(cartState && cartState.total ? cartState.total.cart_total : 0);
const getCurrencyDecimalCount = () => getPosObject().currency_format_num_decimals || 2;

const summarizePaymentMethods = (paymentMethods, totalAmount) => {
    let totalPayingAmount = 0;
    let totalPayLeft = totalAmount;
    const paymentMethodList = getPaymentMethods(paymentMethods);

    paymentMethodList.forEach(paymentMethod => {
        const paymentAmount = parseFloat(paymentMethod.amount) || 0;

        totalPayingAmount += paymentAmount;

        if (!paymentMethod.active) {
            totalPayLeft -= paymentAmount;
        }
    });

    return {
        totalPayingAmount,
        totalPayLeft: totalPayLeft > 0 ? totalPayLeft : 0,
    };
};

const getPaymentSummaryAmounts = (totalAmount, totalPayingAmount) => ({
    payLeftAmount: parseFloat(totalAmount >= totalPayingAmount ? totalAmount - totalPayingAmount : 0).toFixed(2),
    changeAmount: parseFloat(totalPayingAmount >= totalAmount ? totalPayingAmount - totalAmount : 0).toFixed(2),
});

const getPaymentMethodRowClassName = isActive => (
    isActive ? 'ddfcpos-method-row ddfcpos-method-active' : 'ddfcpos-method-row'
);

const isDigitInput = key => /^[0-9]$/.test(key);
const isPayInputKey = key => isDigitInput(key) || key === 'Backspace' || key === '.';

const createTenderSuggestionHandler = (component, amount) => () => (
    component.handleInput(toFixed(amount, getCurrencyDecimalCount()), true)
);

const createCalculatorInputHandler = (component, input) => () => component.handleInput(input);
const updatePaymentMethods = (paymentMethods, updater) => (
    getPaymentMethods(paymentMethods).map((paymentMethod, paymentMethodKey) => updater(paymentMethod, paymentMethodKey))
);
const buildPaymentMethodOptions = () => getEnabledPaymentMethodOptions().map(method => (
    <option key={method.slug} value={method.slug}>{method.name}</option>
));
const getInvoicePopupState = order => ({
    showinvoicePopup: true,
    order,
});
const getEmptyInvoicePopupState = () => ({
    showinvoicePopup: false,
});
const getPaymentMethodsState = paymentMethods => ({
    paymentMethods,
});
const getPayButton = () => document.querySelector('.ddfcpos-pay-button');
const canPlaceOrder = (totalPayingAmount, totalAmount) => totalPayingAmount >= totalAmount;
const isPayButtonDisabled = element => !element || element.getAttribute('disabled');
const hasDisabledPayButton = () => isPayButtonDisabled(getPayButton());
const getActiveCartRecord = cartState => getActiveCartRecordFromState({ cart: cartState });
const hasCartProducts = activeCartRecord => activeCartRecord && Array.isArray(activeCartRecord.cart) && activeCartRecord.cart.length;
const getPaymentAmountValue = paymentMethod => String(paymentMethod && paymentMethod.amount !== undefined ? paymentMethod.amount : '');
const updatePaymentMethodSelection = (paymentMethods, key, value, label) => (
    updatePaymentMethods(getPaymentMethods(paymentMethods), (paymentMethod, paymentMethodKey) => (
        paymentMethodKey === key ? { ...paymentMethod, slug: value, name: label } : paymentMethod
    ))
);
const getPaymentMethodRows = (component, enabledPaymentMethods) => getPaymentMethods(component.state.paymentMethods).map((paymentMethod, key) => (
    <div key={key} className={getPaymentMethodRowClassName(true)}>
        <input type="text" value={getPaymentAmountValue(paymentMethod)} readOnly />
        <Select onChange={component.createPaymentMethodChangeHandler(key)} value={paymentMethod.slug}>
            {enabledPaymentMethods}
        </Select>
    </div>
));
const getPayButtonDisabledState = (totalPayingAmount, totalAmount) => totalPayingAmount < totalAmount;

class Pay extends Component {
    constructor(props) {
        super(props);

        this.state = {
            showinvoicePopup: false,
            order: {},
            paymentMethods: [createPaymentMethodState()],
        };
    }

    componentDidMount = () => {
        const outletId = getOutletId(this.props.outlet);

        this.props.getCustomers(outletId);
        this.props.getCart();
        this.props.getProducts(outletId);
        document.addEventListener('keydown', this.handleKeyDown, false);
    }

    componentWillUnmount = () => {
        document.removeEventListener('keydown', this.handleKeyDown, false);
    }

    handleChangePaymentMethod = (event, key) => {
        const { value, options, selectedIndex } = event.target;
        const selectedLabel = options[selectedIndex].text;

        this.setState(({ paymentMethods }) => getPaymentMethodsState(
            updatePaymentMethodSelection(paymentMethods, key, value, selectedLabel)
        ));
    }

    handleKeyDown = event => {
        if (event.target && !event.target.closest('.ddfcpos-popup-content')) {
            if (event.which === 13) {
                this.handlePayClick();
            }

            if (isPayInputKey(event.key)) {
                this.handleInput(event.key);
            }
        }
    }

    handleInput = (input, replace = false) => {
        if (input === 'cancel') {
            this.handleVisitHome();
        } else {
            this.setState(({ paymentMethods }) => getPaymentMethodsState(
                updatePaymentMethods(paymentMethods, paymentMethod => {
                    if (!paymentMethod.active) {
                        return paymentMethod;
                    }

                    if (replace) {
                        return { ...paymentMethod, amount: input.toString() };
                    }

                    if (input === 'Backspace') {
                        return { ...paymentMethod, amount: getPaymentAmountValue(paymentMethod).slice(0, -1) };
                    }

                    if (input === 'clear') {
                        return { ...paymentMethod, amount: '' };
                    }

                    return { ...paymentMethod, amount: getPaymentAmountValue(paymentMethod) + input };
                })
            ));
        }
    }

    createPaymentMethodChangeHandler = key => event => this.handleChangePaymentMethod(event, key)

    handleOrderCreated = order => {
        const activeCartRecord = getActiveCartRecord(this.props.cart);
        const outletId = getOutletId(this.props.outlet);

        this.props.getOrders(outletId);
        this.props.getCoupons();
        this.props.getCart();

        if (activeCartRecord) {
            removeProductsFromCart(order.products, activeCartRecord.id).then(() => {
                this.props.getCart();
                this.props.getCoupons();

                this.setState(getInvoicePopupState(order));
            });
        }
    }

    renderInvoicePopup = () => {
        if (!this.state.showinvoicePopup) {
            return null;
        }

        const popupContent = (
            <Fragment>
                <h2>{__('Print Receipt', 'devdiggers-multipos-for-fluentcart')}</h2>
                {/* translators: %1$s: order number, %2$s: order status */}
                <p>{sprintf(__('Order #%1$s is %2$s.', 'devdiggers-multipos-for-fluentcart'), this.state.order.order_id || '', this.state.order.order_status_label || '')}</p>
            </Fragment>
        );

        return (
            <Popup
                handleOverlay={this.handleToggleInvoicePopup}
                popupContent={popupContent}
                notDisabled={true}
                handleSuccess={this.handleInvoiceSubmit}
                handleCancel={this.handleVisitHome}
                successButtonText={<Fragment><PrinterOutlined />{__('Print', 'devdiggers-multipos-for-fluentcart')}</Fragment>}
            />
        );
    }

    handlePayClick = () => {
        if (hasDisabledPayButton()) {
            return;
        }

        const payButtonElement = getPayButton();
        if (!payButtonElement) {
            return;
        }

        const totalAmount = getCartTotalAmount(this.props.cart);
        const { totalPayingAmount } = summarizePaymentMethods(this.state.paymentMethods, totalAmount);

        if (!isPayButtonDisabled(payButtonElement) && canPlaceOrder(totalPayingAmount, totalAmount)) {
            payButtonElement.setAttribute('disabled', 'disabled');

            this.props.createOrder(this.state, this).then(order => {
                payButtonElement.removeAttribute('disabled');

                if (order) {
                    this.handleOrderCreated(order);
                }
            }).catch(() => {
                // Never leave the Pay button stuck after a failed or refused sale.
                payButtonElement.removeAttribute('disabled');
            });
        }
    }

    handleToggleInvoicePopup = () => {
        this.setState(prevState => (prevState.showinvoicePopup ? getEmptyInvoicePopupState() : getInvoicePopupState(this.state.order)));
    }

    handleInvoiceSubmit = () => {
        this.props.printInvoice(this.state.order);
        this.handleVisitHome();
    }

    handleVisitHome = () => {
        this.props.addNewCart();
        pushRoute(this.props.history, getPosRoute())
    }

    render() {
        const totalAmount = getCartTotalAmount(this.props.cart);
        const activeCartRecord = getActiveCartRecord(this.props.cart);

        const invoicePopupHTML = this.renderInvoicePopup();

        if (this.state.showinvoicePopup) {
            return (
                <div className="ddfcpos-pay-tab-wrapper">
                    {invoicePopupHTML}
                </div>
            );
        }

        if (!hasCartProducts(activeCartRecord)) {
            return (
                <div className="ddfcpos-pay-tab-wrapper">
                    <EmptyState
                        icon="cart"
                        title={__('Nothing to pay for yet', 'devdiggers-multipos-for-fluentcart')}
                        hint={__('Please add products in the cart to place the order', 'devdiggers-multipos-for-fluentcart')}
                        action={<button type="button" onClick={() => pushRoute(this.props.history, getPosRoute())}><ArrowLeftOutlined /> {__('Back to Products', 'devdiggers-multipos-for-fluentcart')}</button>}
                    />
                </div>
            );
        }

        const { totalPayingAmount, totalPayLeft } = summarizePaymentMethods(this.state.paymentMethods, totalAmount);
        const { payLeftAmount, changeAmount } = getPaymentSummaryAmounts(totalAmount, totalPayingAmount);
        const enabledPaymentMethods = buildPaymentMethodOptions();

        const paymentMethodsHTML = getPaymentMethodRows(this, enabledPaymentMethods);

        const [firstSuggestion, secondSuggestion, thirdSuggestion, forthSuggestion] = getSuggestedAmounts(totalPayLeft);

        return (
            <div className="ddfcpos-pay-tab-wrapper">
                <div className="ddfcpos-pay-summary">
                    <div>
                        <h3>{__('Total Due', 'devdiggers-multipos-for-fluentcart')}</h3>
                        <span>{formatPrice(totalAmount)}</span>
                    </div>
                    <div>
                        <h3>{__('Total Paying', 'devdiggers-multipos-for-fluentcart')}</h3>
                        <span>{formatPrice(totalPayingAmount)}</span>
                    </div>
                    <div>
                        <h3>{__('Pay Left', 'devdiggers-multipos-for-fluentcart')}</h3>
                        <span>{formatPrice(payLeftAmount)}</span>
                    </div>
                    <div>
                        <h3>{__('Change', 'devdiggers-multipos-for-fluentcart')}</h3>
                        <span>{formatPrice(changeAmount)}</span>
                    </div>
                </div>
                <div className="ddfcpos-pay-method-container">
                    <div className="ddfcpos-method-row">
                        <h4>{__('Amount', 'devdiggers-multipos-for-fluentcart')}</h4>
                        <h4>{__('Method', 'devdiggers-multipos-for-fluentcart')}</h4>
                    </div>
                    {paymentMethodsHTML}
                </div>
                <div className="ddfcpos-numeric-pay-container">
                    <div className="ddfcpos-tendered-suggestions">
                        <span onClick={createTenderSuggestionHandler(this, firstSuggestion)}>{formatPrice(firstSuggestion)}</span>
                        <span onClick={createTenderSuggestionHandler(this, secondSuggestion)}>{formatPrice(secondSuggestion)}</span>
                        <span onClick={createTenderSuggestionHandler(this, thirdSuggestion)}>{formatPrice(thirdSuggestion)}</span>
                        <span onClick={createTenderSuggestionHandler(this, forthSuggestion)}>{formatPrice(forthSuggestion)}</span>
                    </div>
                    <div className="ddfcpos-pay-calculator">
                        <span onClick={createCalculatorInputHandler(this, '1')}>1</span>
                        <span onClick={createCalculatorInputHandler(this, '2')}>2</span>
                        <span onClick={createCalculatorInputHandler(this, '3')}>3</span>
                        <span onClick={createCalculatorInputHandler(this, 'clear')}>{__('clear', 'devdiggers-multipos-for-fluentcart')}</span>
                        <span onClick={createCalculatorInputHandler(this, '4')}>4</span>
                        <span onClick={createCalculatorInputHandler(this, '5')}>5</span>
                        <span onClick={createCalculatorInputHandler(this, '6')}>6</span>
                        <span onClick={createCalculatorInputHandler(this, 'Backspace')}><ArrowLeftOutlined /></span>
                        <span onClick={createCalculatorInputHandler(this, '7')}>7</span>
                        <span onClick={createCalculatorInputHandler(this, '8')}>8</span>
                        <span onClick={createCalculatorInputHandler(this, '9')}>9</span>
                        <span onClick={this.handlePayClick} className="ddfcpos-pay-button" disabled={getPayButtonDisabledState(totalPayingAmount, totalAmount)}>{__('Pay', 'devdiggers-multipos-for-fluentcart')}</span>
                        <span onClick={createCalculatorInputHandler(this, '0')}>0</span>
                        <span onClick={createCalculatorInputHandler(this, '.')}>.</span>
                        <span onClick={createCalculatorInputHandler(this, '00')}>00</span>
                        <span onClick={createCalculatorInputHandler(this, 'cancel')} className="ddfcpos-cancel-button">{__('Cancel', 'devdiggers-multipos-for-fluentcart')}</span>
                    </div>
                </div>
            </div>
        );
    }
}

const mapStateToProps = state => ({
    cart: state.cart,
});

const mapDispatchToProps = dispatch => bindActionCreators({
    getCart,
    getCustomers,
    createOrder,
    getProducts,
    getCoupons,
    getOrders,
    addNewCart,
    printInvoice
}, dispatch);

export default connect(mapStateToProps, mapDispatchToProps)(Pay);
