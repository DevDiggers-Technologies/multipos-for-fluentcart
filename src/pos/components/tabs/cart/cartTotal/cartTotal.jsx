import React, { Component, Fragment } from 'react';
import { bindActionCreators } from 'redux';
import { Link } from 'react-router-dom';
import { connect } from 'react-redux';
import { addToHold } from '../../../../actions/hold-carts';
import { addNewCart, validateProductStock, deleteNotValidProductsFromCart } from '../../../../actions/cart';
import { applyCoupon, removeCoupon, getCoupons } from '../../../../actions/coupon';
import database from '../../../../services/database';
import { fetchRequest } from '../../../../services/request';
import { __ } from '@wordpress/i18n';
import { formatPrice } from '../../../../utils/currency-format';
import { DoubleRightOutlined, PauseOutlined, TagFilled, DeleteFilled } from '@ant-design/icons';
import Popup from './../../../popup/popup';
import { isInternetConnected } from '../../../../services/connectivity';
import { getPosRoute } from '../../../../services/routes';
import { notify } from '../../../../services/notifications';
import { getPosApi } from '../../../../services/runtime';
import { getActiveCartRecordFromState, getFirstCartScopedRecord, hasRecordId } from '../../../../services/cart-session';
import { pushRoute } from '../../../../utils/navigation';
import { hasEntries, normalizeRecordId, parseWholeNumber } from '../../../../utils/value';
import { isEnterKey } from '../../../../utils/event';


const hasCartProducts = cart => {
    const activeCartRecord = getActiveCartRecordFromState({ cart });

    return Boolean(activeCartRecord && Array.isArray(activeCartRecord.cart) && activeCartRecord.cart.length);
};

const clearCartAdjustments = (props, activeCartId) => {
    if (!hasRecordId(activeCartId)) {
        return Promise.resolve(false);
    }

    const normalizedCartId = normalizeRecordId(activeCartId);

    return database.table('coupon').where('cart_id').equals(normalizedCartId).delete().then(() => props.getCoupons());
};

const getAppliedCouponLabel = coupon => ` (${coupon.type === 'percentage' ? coupon.value + '%' : formatPrice(coupon.value)})`;

const getPopupNode = (isVisible, popupProps) => isVisible ? <Popup {...popupProps} /> : null;
const getCartItemsCount = cartItems => cartItems.reduce((total, item) => total + parseWholeNumber(item.quantity), 0);
const getCartTotalItemsLabel = itemsCount => (
    `${itemsCount} ${__(itemsCount > 1 ? 'Items' : 'Item', 'devdiggers-multipos-for-fluentcart')}`
);
const getAppliedCoupons = (couponState, activeCartId) => {
    const couponRecord = getFirstCartScopedRecord(couponState, activeCartId);
    return couponRecord && Array.isArray(couponRecord.coupon) ? couponRecord.coupon : [];
};
const getCartSummaryTotal = cartTotal => parseFloat(cartTotal);
const getActiveCartRecord = cart => getActiveCartRecordFromState({ cart });
const getActiveCartId = cart => {
    const activeCartRecord = getActiveCartRecord(cart);
    return activeCartRecord ? activeCartRecord.id : 0;
};
const getTogglePopupState = key => previousState => ({
    [key]: !previousState[key],
});
const getInputValueState = (key, value) => ({
    [key]: value,
});
const getCouponPopupNode = (component, popupContent) => getPopupNode(component.state.showCouponPopup, {
    handleOverlay: component.handleToggleCouponPopup,
    popupContent,
    notDisabled: component.state.couponCode,
    handleSuccess: component.handleApplyCoupon,
    successButtonText: __('Apply Coupon', 'devdiggers-multipos-for-fluentcart'),
    handleCancel: component.handleToggleCouponPopup,
});
const getHoldOrderPopupNode = (component, popupContent) => getPopupNode(component.state.showHoldOrderPopup, {
    handleOverlay: component.handleToggleHoldOrderPopup,
    popupContent,
    notDisabled: true,
    handleSuccess: component.handleSaveHoldOrder,
    successButtonText: __('Hold Order', 'devdiggers-multipos-for-fluentcart'),
    handleCancel: component.handleToggleHoldOrderPopup,
});
const createRemoveCouponHandler = (component, couponCode) => () => component.props.removeCoupon(couponCode);
const createKeyUpHandler = (component, handler) => event => component[handler](event);

class CartTotal extends Component {

    constructor(props) {
        super(props);

        this.state = {
            showCouponPopup: false,
            showHoldOrderPopup: false,
            couponCode: '',
            holdOrderInfo: '',
        };
    }

    handleToggleCouponPopup = () => {
        this.setState(getTogglePopupState('showCouponPopup'));
    }

    handleToggleHoldOrderPopup = () => {
        const cart = this.props.cart;

        if (hasCartProducts(cart)) {
            this.setState(getTogglePopupState('showHoldOrderPopup'));
        } else {
            notify({
                title: __('Empty Cart', 'devdiggers-multipos-for-fluentcart'),
                message: __('Please add products in the cart first to hold it.', 'devdiggers-multipos-for-fluentcart'),
                type: 'danger',
            });
        }
    }

    handleCouponInput = event => {
        if (isEnterKey(event)) {
            this.handleApplyCoupon();
        } else {
            this.setState(getInputValueState('couponCode', event.target.value));
        }
    }

    handleHoldOrderInfoInput = event => {
        if (isEnterKey(event)) {
            this.handleSaveHoldOrder();
        } else {
            this.setState(getInputValueState('holdOrderInfo', event.target.value));
        }
    }

    handleApplyCoupon = () => {
        if (!isInternetConnected()) {
            notify({
                title: __('System is Offline', 'devdiggers-multipos-for-fluentcart'),
                message: __('Coupon cannot be applied in offline mode, please apply after getting online.', 'devdiggers-multipos-for-fluentcart'),
                type: 'danger',
            });
            return;
        }

        const cart = this.props.cart;

        if (hasCartProducts(cart)) {
            this.handleToggleCouponPopup();

            const couponCode = this.state.couponCode;

            if (couponCode) {
                const customers = this.props.customers || {};

                const postData = {
                    coupon_code: couponCode,
                    customer: customers.defaultCustomer || {}
                };

                fetchRequest(getPosApi().CHECK_COUPON_ENDPOINT, postData).then(response => {
                    if (response.success && response.coupon) {
                        this.props.applyCoupon(response.coupon);
                    } else {
                        notify({
                            title: __('Coupon Error', 'devdiggers-multipos-for-fluentcart'),
                            message: response.message,
                            type: 'danger',
                        });
                    }
                });
            } else {
                notify({
                    title: __('Empty Field', 'devdiggers-multipos-for-fluentcart'),
                    message: __('Please enter a coupon code first.', 'devdiggers-multipos-for-fluentcart'),
                    type: 'danger',
                });
            }
        } else {
            notify({
                title: __('Empty Cart', 'devdiggers-multipos-for-fluentcart'),
                message: __('Please add products in the cart to apply coupon.', 'devdiggers-multipos-for-fluentcart'),
                type: 'danger',
            });
        }
    }

    getActiveCartProducts = () => {
        const activeCartRecord = getActiveCartRecordFromState({ cart: this.props.cart });

        return activeCartRecord && Array.isArray(activeCartRecord.cart) ? activeCartRecord.cart : [];
    }

    getOutOfStockProducts = cartList => this.props.validateProductStock(cartList).then(notValidProducts => {
        if (!notValidProducts || !notValidProducts.out_of_stock_products) {
            return [];
        }

        const outOfStockProducts = Array.isArray(notValidProducts.out_of_stock_products) ? notValidProducts.out_of_stock_products : [];

        return (Array.isArray(cartList) ? cartList : []).filter(cartData => outOfStockProducts.includes(cartData.product_id));
    })

    getStockWarningMessage = notValidProductsList => {
        const names = (Array.isArray(notValidProductsList) ? notValidProductsList : []).map(product => product.name);
        let namesString = names.join(', ');

        if (names.length > 1) {
            return `${namesString} ${__('are out of stock now, please remove them from cart', 'devdiggers-multipos-for-fluentcart')}`;
        }

        return `${namesString} ${__('is out of stock now, kindly remove them from cart', 'devdiggers-multipos-for-fluentcart')}`;
    }

    notifyEmptyCartOnPay = () => notify({
        title: __('Empty Cart', 'devdiggers-multipos-for-fluentcart'),
        message: __('Cart is empty right now, kindly add products to checkout.', 'devdiggers-multipos-for-fluentcart'),
        type: 'danger',
    })

    notifySelectCustomerOnPay = () => notify({
        title: __('Select Customer First', 'devdiggers-multipos-for-fluentcart'),
        message: __('Customer is not selected, kindly select the customer to checkout.', 'devdiggers-multipos-for-fluentcart'),
        type: 'danger',
    })

    notifyOfflineError = () => notify({
        title: __('Error', 'devdiggers-multipos-for-fluentcart'),
        message: __('Sorry, orders cannot be processed in offline mode.', 'devdiggers-multipos-for-fluentcart'),
        type: 'danger',
    })

    proceedToPay = () => {
        pushRoute(this.props.history, getPosRoute('/pay'));
    }

    handleStockCheckedPay = async (event, cartList) => {
        event.preventDefault();

        if (!isInternetConnected()) {
            this.notifyOfflineError();
            return;
        }

        const activeCartRecord = getActiveCartRecordFromState({ cart: this.props.cart });
        const activeCartId = activeCartRecord ? activeCartRecord.id : null;
        const notValidProductsList = await this.getOutOfStockProducts(cartList);

        if (!notValidProductsList.length) {
            this.proceedToPay();
            return;
        }

        const validProductsList = cartList.filter(cartData => !notValidProductsList.includes(cartData));
        const stockWarning = this.getStockWarningMessage(notValidProductsList);

        if (confirm(stockWarning)) {
            this.props.deleteNotValidProductsFromCart(validProductsList, activeCartId);
        }
    }

    handlePayClick = async event => {
        const customers = this.props.customers || {};
        const cartList = this.getActiveCartProducts();

        if (!hasEntries(customers.defaultCustomer)) {
            event.preventDefault();
            this.notifySelectCustomerOnPay();
            return;
        }

        if (!cartList.length) {
            event.preventDefault();
            this.notifyEmptyCartOnPay();
            return;
        }

        await this.handleStockCheckedPay(event, cartList);
    }

    handleSaveHoldOrder = () => {
        const activeCartId = getActiveCartId(this.props.cart);

        this.handleToggleHoldOrderPopup();

        this.props.addToHold(this.state.holdOrderInfo).then(holdSaved => {
            if (holdSaved) {
                clearCartAdjustments(this.props, activeCartId).then(() => {
                    this.props.addNewCart();
                });
            }
        });
    }

    render() {
        const cart = this.props.cart || { list: [], total: {} };
        let totalItems = 0;
        const activeCartRecord = getActiveCartRecord(cart);

        if (activeCartRecord && Array.isArray(activeCartRecord.cart) && activeCartRecord.cart.length) {
            totalItems = getCartItemsCount(activeCartRecord.cart);
            totalItems = getCartTotalItemsLabel(totalItems);
        } else {
            totalItems = getCartTotalItemsLabel(0);
        }

        const activeCartId = getActiveCartId(cart);
        const coupon = getAppliedCoupons(this.props.coupon, activeCartId);
        const total = cart.total || {};

        const couponHTML = coupon.map((val, index) => (
            <span className="ddfcpos-cart-applied-coupons" key={val.code || index}>
                <DeleteFilled className="ddfcpos-remove-discount-icon" onClick={createRemoveCouponHandler(this, val.code)} />
                {val.code}
                {getAppliedCouponLabel(val)}
            </span>
        ));

        const holdOrderPopupHTML = getHoldOrderPopupNode(this, (
            <Fragment>
                <h2>{__('Hold Order', 'devdiggers-multipos-for-fluentcart')}</h2>
                <p className="ddfcpos-popup-hint">{__('Add a short note so you can find this order again.', 'devdiggers-multipos-for-fluentcart')}</p>
                <label className="ddfcpos-popup-field">
                    <span>{__('Order Info', 'devdiggers-multipos-for-fluentcart')}</span>
                    <input type="text" onKeyUp={createKeyUpHandler(this, 'handleHoldOrderInfoInput')} placeholder={__('Enter Order Info', 'devdiggers-multipos-for-fluentcart')} autoFocus />
                </label>
            </Fragment>
        ));

        const couponPopupHTML = getCouponPopupNode(this, (
            <Fragment>
                <h2>{__('Apply Coupon', 'devdiggers-multipos-for-fluentcart')}</h2>
                <label className="ddfcpos-popup-field">
                    <span>{__('Coupon Code', 'devdiggers-multipos-for-fluentcart')}</span>
                    <input type="text" onKeyUp={createKeyUpHandler(this, 'handleCouponInput')} placeholder={__('Enter Coupon Code', 'devdiggers-multipos-for-fluentcart')} autoCapitalize="characters" autoComplete="off" autoFocus />
                </label>
            </Fragment>
        ));

        return (
            <Fragment>
                <div className="ddfcpos-cart-totals">
                    <div>
                        <p>{__('Subtotal', 'devdiggers-multipos-for-fluentcart')}</p>
                        <strong>{formatPrice(total.cart_subtotal)}</strong>
                    </div>
                    <div>
                        <p>{__('Tax', 'devdiggers-multipos-for-fluentcart')}</p>
                        <strong>{formatPrice(total.tax_total)}</strong>
                    </div>
                    <div>
                        <p>{__('Applied Coupon(s)', 'devdiggers-multipos-for-fluentcart')}</p>
                        <strong>
                            {couponHTML.length ? couponHTML : __('N/A', 'devdiggers-multipos-for-fluentcart')}
                        </strong>
                    </div>
                    <div className="ddfcpos-cart-actions">
                        <span onClick={this.handleToggleCouponPopup}>
                            <TagFilled />
                            {__('Coupon', 'devdiggers-multipos-for-fluentcart')}
                        </span>
                        <span onClick={this.handleToggleHoldOrderPopup}>
                            <PauseOutlined />
                            {__('Hold Order', 'devdiggers-multipos-for-fluentcart')}
                        </span>
                    </div>
                    <Link className="ddfcpos-cart-pay" to={getPosRoute('/pay')} onClick={event => this.handlePayClick(event)}>
                        <p>
                            {__('Proceed to Pay', 'devdiggers-multipos-for-fluentcart')}
                            <br />
                            <i>{totalItems}</i>
                        </p>
                        <p>{formatPrice(getCartSummaryTotal(total.cart_total))}</p>
                        <DoubleRightOutlined />
                    </Link>
                </div>
                {holdOrderPopupHTML}
                {couponPopupHTML}
            </Fragment>
        );
    }
}

const mapDispatchToProps = dispatch => bindActionCreators({
    applyCoupon,
    removeCoupon,
    validateProductStock,
    deleteNotValidProductsFromCart,
    getCoupons,
    addToHold,
    addNewCart,
}, dispatch);

export default connect(null, mapDispatchToProps)(CartTotal);
