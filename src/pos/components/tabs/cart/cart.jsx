import React, { Component, Fragment } from 'react';
import { connect } from 'react-redux';
import { bindActionCreators } from 'redux';
import { __ } from '@wordpress/i18n';
import { EditFilled, PauseOutlined, RedoOutlined, UserOutlined, WifiOutlined } from '@ant-design/icons';
import { Link } from 'react-router-dom';
import CartProduct from './cartProduct/cartProduct.jsx';
import CartTotal from './cartTotal/cartTotal.jsx';
import { getCart, resetActiveCartStorage } from '../../../actions/cart';
import { getCoupons } from '../../../actions/coupon';
import { getHoldCarts } from './../../../actions/hold-carts';
import { getOrders } from './../../../actions/orders';
import { updateDefaultTable } from '../../../actions/tables';
import { getTables } from './../../../actions/tables';
import Popup from '../../popup/popup.jsx';
import { isInternetConnected } from '../../../services/connectivity';
import { notify } from '../../../services/notifications';
import { getPosRoute } from '../../../services/routes';
import { getActiveCartRecordFromState } from '../../../services/cart-session';
import { pushRoute } from '../../../utils/navigation';
import { getOutletId, getOutletMode, hasEntries } from '../../../utils/value';
import TableServiceIcon from '../../icons/tableServiceIcon.jsx';
import EmptyState from '../../emptyState/emptyState';

const hasEntity = entity => hasEntries(entity);
const getSafeCartList = cart => Array.isArray(cart && cart.list) ? cart.list : [];
const getSafeHoldCarts = holdCarts => Array.isArray(holdCarts) ? holdCarts : [];
const getCustomersState = customers => customers || {};
const getTablesState = tables => tables || {};

const getDefaultCustomerName = defaultCustomer => {
    if (!hasEntries(defaultCustomer)) {
        return '';
    }

    const customerName = `${defaultCustomer.first_name} ${defaultCustomer.last_name}`;

    return customerName !== ' ' ? customerName : defaultCustomer.username;
};

const getCartHeaderStyle = outletMode => {
    let style = {};

    if (outletMode === 'restaurant') {
        style.gridTemplateColumns = 'max-content minmax(0, 1fr) minmax(0, 1fr)';
    }

    return style;
};

// Line glyph on the same 24px grid and stroke as the table icon, so both mode cards match.
const TakeAwayModeIcon = () => (
    <svg viewBox="0 0 24 24" width="1em" height="1em" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" style={{ fill: 'none' }} aria-hidden="true" focusable="false">
        <path d="M5.25 7.75h13.5l-.9 12.5H6.15z" />
        <path d="M9 7.75V6.5a3 3 0 0 1 6 0v1.25" />
    </svg>
);

class Cart extends Component {

    constructor(props) {
        super(props);

        this.state = {
            showSelectTablePopup: false,
            online: isInternetConnected()
        }
    }

    componentDidMount = () => {
        this.props.getCoupons();
        this.props.getCart();
        this.props.getHoldCarts();
        this.props.getOrders(getOutletId(this.props.outlet));
        this.props.getTables();

        window.addEventListener('online', this.handleBrowserOnline);
        window.addEventListener('offline', this.handleBrowserOffline);
    }

    componentWillUnmount = () => {
        window.removeEventListener('online', this.handleBrowserOnline);
        window.removeEventListener('offline', this.handleBrowserOffline);
    }

    handleBrowserOnline = () => this.setState({ online: true });

    handleBrowserOffline = () => this.setState({ online: false });

    handleResetCartButton = () => {
        const cartList = getSafeCartList(this.props.cart);
        if (cartList.length) {
            this.props.resetActiveCartStorage().then(() => {
                this.props.getCoupons();
                this.props.getCart();
            });
        } else {
            notify({
                title: __('Empty Cart', 'devdiggers-multipos-for-fluentcart'),
                message: __('Cart is already empty.', 'devdiggers-multipos-for-fluentcart'),
                type: 'info',
            });
        }
    }

    handleToggleShowSelectTablePopup = () => {
        this.setState(prevState => ({
            showSelectTablePopup: !prevState.showSelectTablePopup
        }));
    }

    handleSelectMode = mode => {
        this.handleToggleShowSelectTablePopup();

        if (mode === 'dine_in') {
            pushRoute(this.props.history, getPosRoute('/tables/all'));
        } else if (mode === 'take_away') {
            this.props.updateDefaultTable();
        }
    }

    handleSelectDineInMode = () => this.handleSelectMode('dine_in')
    handleSelectTakeAwayMode = () => this.handleSelectMode('take_away')

    render() {
        const activeCartRecord = getActiveCartRecordFromState({ cart: this.props.cart });
        const defaultCustomer = getCustomersState(this.props.customers).defaultCustomer || {};
        const defaultTable = getTablesState(this.props.tables).defaultTable || {};
        const holdCartLength = getSafeHoldCarts(this.props.holdCarts).length;
        let cartProducts = [];

        if (activeCartRecord && Array.isArray(activeCartRecord.cart)) {
            cartProducts = activeCartRecord.cart.map(cartProduct => {
                const cartProductKey = cartProduct.key || `${cartProduct.product_id}-${cartProduct.name}`;

                return <CartProduct key={cartProductKey} cartProduct={cartProduct} {...this.props}></CartProduct>;
            });
        }

        const wifiButtonStyle = {
            color: this.state.online ? 'green' : 'red'
        };

        const defaultCustomerName = getDefaultCustomerName(defaultCustomer);
        const outletMode = getOutletMode(this.props.outlet);
        const style = getCartHeaderStyle(outletMode);

        let selectTablePopupHTML = null;

        if (this.state.showSelectTablePopup) {
            const popupContent = (
                <Fragment>
                    <h2>{__('Select Mode', 'devdiggers-multipos-for-fluentcart')}</h2>
                    <div className="ddfcpos-mode-options">
                        <span onClick={this.handleSelectDineInMode}>
                            <TableServiceIcon />
                            <p>{__('Dine in', 'devdiggers-multipos-for-fluentcart')}</p>
                        </span>
                        <span onClick={this.handleSelectTakeAwayMode}>
                            <TakeAwayModeIcon />
                            <p>{__('Take Away', 'devdiggers-multipos-for-fluentcart')}</p>
                        </span>
                    </div>
                </Fragment>
            );

            const selectTablePopupProps = {
                handleOverlay: this.handleToggleShowSelectTablePopup,
                popupContent: popupContent,
                hideActions: true,
            };

            selectTablePopupHTML = <Popup {...selectTablePopupProps} />
        }

        return (
            <div className="ddfcpos-cart-wrapper">
                <div className="ddfcpos-cart-header" style={style}>
                    <div className="ddfcpos-icon-card" style={wifiButtonStyle} title={this.state.online ? __('Online', 'devdiggers-multipos-for-fluentcart') : __('Offline', 'devdiggers-multipos-for-fluentcart')}>
                        <WifiOutlined />
                    </div>
                    <Link className="ddfcpos-button" to={getPosRoute('/customers')}>
                        {hasEntity(defaultCustomer) ?
                            <Fragment>
                                {defaultCustomer.avatar_url ? <img src={defaultCustomer.avatar_url} alt={defaultCustomer.username || defaultCustomerName} width="30" height="30" /> : null}
                                <p>{defaultCustomerName}</p>
                                <EditFilled />
                            </Fragment>
                            :
                            <Fragment>
                                <UserOutlined />
                                <p>{__('Select Customer', 'devdiggers-multipos-for-fluentcart')}</p>
                            </Fragment>
                        }
                    </Link>
                    {outletMode === 'restaurant' ?
                        <button className="ddfcpos-button ddfcpos-button-green" onClick={this.handleToggleShowSelectTablePopup}>
                            {hasEntity(defaultTable) ?
                                <Fragment>
                                    <span className="ddfcpos-table-icon" role="img">
                                        <TableServiceIcon />
                                    </span>
                                    <p>{defaultTable.name}</p>
                                    <EditFilled />
                                </Fragment>
                                :
                                <Fragment>
                                    <span className="ddfcpos-table-icon" role="img">
                                        <TableServiceIcon />
                                    </span>
                                    <p>{__('Select Table', 'devdiggers-multipos-for-fluentcart')}</p>
                                </Fragment>
                            }
                        </button>
                        : null}
                </div>
                <div className="ddfcpos-cart-products-list">
                    <div className="ddfcpos-cart-header">
                        <h2>
                            <span className="anticon ddfcpos-cart-title-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" width="1em" height="1em" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" focusable="false">
                                    <path d="M5.25 7.75h13.5l-.9 12.5H6.15z" />
                                    <path d="M9 7.75V6.5a3 3 0 0 1 6 0v1.25" />
                                </svg>
                            </span>
                            {__('Cart Items', 'devdiggers-multipos-for-fluentcart')}
                        </h2>
                        <Fragment>
                            <Link to={getPosRoute('/orders/hold')}>
                                <div className="ddfcpos-icon-card" title={__('Check Hold Orders', 'devdiggers-multipos-for-fluentcart')}>
                                    {holdCartLength ?
                                        <span className="ddfcpos-card-count">{holdCartLength}</span>
                                        : null}
                                    <PauseOutlined />
                                </div>
                            </Link>
                            <div className="ddfcpos-icon-card" onClick={this.handleResetCartButton} title={__('Reset Cart', 'devdiggers-multipos-for-fluentcart')}>
                                <RedoOutlined />
                            </div>
                        </Fragment>
                    </div>
                    <div className="ddfcpos-cart-products">
                        {cartProducts.length > 0 ? cartProducts : (
                            <EmptyState icon="cart" title={__('Your cart is empty', 'devdiggers-multipos-for-fluentcart')} hint={__('Tap a product or scan a barcode to start a sale.', 'devdiggers-multipos-for-fluentcart')} />
                        )}
                    </div>
                </div>
                <CartTotal {...this.props} />
                {selectTablePopupHTML}
            </div>
        );
    }
}

const mapStateToProps = state => ({
    cart: state.cart,
    holdCarts: state.holdCarts,
    coupon: state.coupon,
    customers: state.customers,
    orders: state.orders,
    tables: state.tables
});

const mapDispatchToProps = dispatch => bindActionCreators({
    getCart,
    resetActiveCartStorage,
    getCoupons,
    getHoldCarts,
    getOrders,
    updateDefaultTable,
    getTables
}, dispatch);

export default connect(mapStateToProps, mapDispatchToProps)(Cart);
