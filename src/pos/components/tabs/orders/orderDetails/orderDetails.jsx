import React, { Component } from 'react';
import { connect } from 'react-redux';
import { bindActionCreators } from 'redux';
import { __, sprintf } from '@wordpress/i18n';
import { PrinterOutlined, UserOutlined, ShoppingCartOutlined, DeleteOutlined } from '@ant-design/icons';
import ReactHtmlParser from 'react-html-parser';
import { formatPrice } from '../../../../utils/currency-format';
import { addHoldCartToCurrentCart, deleteHoldCart } from './../../../../actions/hold-carts';
import { updateDefaultCustomer } from './../../../../actions/customers';
import { updateDefaultTable } from './../../../../actions/tables';
import { printInvoice } from './../../../../actions/invoice';
import { getPosRoute } from '../../../../services/routes';
import { notify } from '../../../../services/notifications';
import { getPosConfig } from '../../../../services/runtime';
import { getActiveCartRecordFromState } from '../../../../services/cart-session';
import { getOrderDetailsPanel, isMobileViewport, pushRoute } from '../../../../utils/navigation';
import { getHoldOrderRecord, getOrderCurrencySymbol } from '../../../../utils/orders';
import EmptyState from '../../../emptyState/emptyState';
import TableServiceIcon from '../../../icons/tableServiceIcon.jsx';

const getFallbackOrderImage = () => `<img width="150" height="150" src="${getPosConfig()['placeholder_image'] || ''}" class="attachment-thumbnail size-thumbnail" />`;

const getOrderProductImage = product => product && product.image ? product.image : getFallbackOrderImage();

const getOrderProductMeta = product => (
    product && Array.isArray(product.meta_data) && product.meta_data.length
        ? product.meta_data.map(meta => (
            <p key={meta.key}><strong>{meta.key}: </strong>{meta.value}</p>
        ))
        : []
);
const getEmptyOrderDetailsMarkup = isVisible => (
    <div className="ddfcpos-order-details-wrapper" style={getOrderDetailsWrapperStyle(isVisible)}>
        <EmptyState icon="receipt" title={__('No Order Details Found', 'multipos-for-fluentcart')} hint={__('Select an order from the list to see its details.', 'multipos-for-fluentcart')} />
    </div>
);

const getOrderCouponsMarkup = order => {
    const orderCoupons = Array.isArray(order && order.coupons) ? order.coupons : [];
    const orderCurrencySymbol = order && order.order_currency_symbol ? order.order_currency_symbol : '';

    return orderCoupons.map((coupon, key) => (
        <span className="ddfcpos-order-applied-coupons" key={key}>
            {coupon.code}
            {` (${formatPrice(coupon.amount, orderCurrencySymbol)})`}
        </span>
    ));
};


const getTenderedMarkup = order => {
    if (Array.isArray(order.payment_methods) && order.payment_methods.length) {
        return order.payment_methods.map((paymentMethod, key) => (
            <div key={key}>
                <p>{paymentMethod.name}</p>
                <strong>{formatPrice(paymentMethod.amount, order.order_currency_symbol)}</strong>
            </div>
        ));
    }

    return (
        <div>
            <p>{order.payment_method_title}</p>
            <strong>{formatPrice(order.tendered, order.order_currency_symbol)}</strong>
        </div>
    );
};

const getProductKey = product => product.item_id || product.id || product.product_id || product.name;

const getOrderDetailsWrapperStyle = isVisible => (isVisible ? {} : { display: 'none' });
const hasHoldOrderShape = order => Boolean(order) && Array.isArray(order.list) && order.list.some(Boolean);
const hasSalesOrderShape = order => Boolean(order) && Array.isArray(order.products);

const canAssignTable = table => table && table.slug;
const getSalesOrderProducts = order => hasSalesOrderShape(order) ? order.products : [];
const getSalesOrderTaxLines = order => Array.isArray(order.tax_lines) ? order.tax_lines : [];
const getHoldCartProducts = cart => cart && Array.isArray(cart.cart) ? cart.cart : [];
const getOrderEmail = order => order && order.email ? order.email : '';
const getActiveCartRecord = cartState => getActiveCartRecordFromState({ cart: cartState });
const hideMobileOrderDetailsPanel = () => {
    if (!isMobileViewport()) {
        return;
    }

    const detailsWrapper = getOrderDetailsPanel();

    if (detailsWrapper) {
        detailsWrapper.style.position = 'initial';
        detailsWrapper.style.display = 'none';
    }
};

const getOrderTaxMarkup = order => {
    const orderCurrencySymbol = getOrderCurrencySymbol(order);

    return getSalesOrderTaxLines(order).map(tax => (
        <div key={tax.label}>
            <p>{tax.label}</p>
            <strong>{formatPrice(tax.total, orderCurrencySymbol)}</strong>
        </div>
    ));
};
const getHoldOrderCustomerEmail = cart => (cart && cart.customer && cart.customer.email) || '';
const getHoldOrderTableName = cart => (cart && cart.table && cart.table.name) || '';

class OrderDetails extends Component {
    moveHoldCartToCart = holdCart => {
        const holdCartId = holdCart && holdCart.id;
        const customer = holdCart && holdCart.customer ? holdCart.customer : {};
        const table = holdCart && holdCart.table ? holdCart.table : {};

        this.props.addHoldCartToCurrentCart(holdCartId);
        if (customer.id) {
            this.props.updateDefaultCustomer(customer.id);
        }

        if (canAssignTable(table)) {
            this.props.updateDefaultTable(table.slug);
        }

        pushRoute(this.props.history, getPosRoute());
    }

    handleAddHoldCart = holdCart => {
        const activeCartRecord = getActiveCartRecord(this.props.cart);

        if (activeCartRecord && Array.isArray(activeCartRecord.cart) && activeCartRecord.cart.length) {
            if (confirm(__('There are items present in the cart, proceeding will replace these items. Please confirm', 'multipos-for-fluentcart'))) {
                this.moveHoldCartToCart(holdCart);
            }
        } else {
            this.moveHoldCartToCart(holdCart);
        }
    }

    handleDeleteHoldCart = holdCartId => {
        this.props.deleteHoldCart(holdCartId);
        notify({
            title: __('Delete Success', 'multipos-for-fluentcart'),
            message: __('Hold order is deleted successfully.', 'multipos-for-fluentcart'),
            type: 'success',
        });
        if (this.props.handleToggleTableOrderPopup) {
            this.props.handleToggleTableOrderPopup();
        }

        hideMobileOrderDetailsPanel();
    }

    handleBackOrderDetailsMobile = () => {
        hideMobileOrderDetailsPanel();
    }

    createAddHoldCartHandler = cart => () => this.handleAddHoldCart(cart)
    createPrintInvoiceHandler = order => () => this.props.printInvoice(order)
    createDeleteHoldCartHandler = cartId => () => this.handleDeleteHoldCart(cartId)

    render() {
        const orderType = this.props.orderType;
        const order = this.props.order;

        if (!order) {
            return getEmptyOrderDetailsMarkup(!isMobileViewport());
        } else {
            if (orderType === 'hold') {
                if (!hasHoldOrderShape(order)) {
                    return getEmptyOrderDetailsMarkup(this.props.tableView || !isMobileViewport());
                }

                const cart = getHoldOrderRecord(order);
                const total = order.total || {};
                const holdCartProducts = getHoldCartProducts(cart);

                if (!cart) {
                    return getEmptyOrderDetailsMarkup(this.props.tableView || !isMobileViewport());
                }

                const productsList = holdCartProducts.map(product => {
                    return (
                        <div className="ddfcpos-order-product" key={getProductKey(product)}>
                            <div className="ddfcpos-order-product-info">
                                <div className="ddfcpos-order-product-image" dangerouslySetInnerHTML={{ __html: getOrderProductImage(product) }}></div>
                                <div className="ddfcpos-order-product-details">
                                    <h4 title={ReactHtmlParser(product.name)}>{ReactHtmlParser(product.name)}</h4>
                                    <p>{formatPrice(product.unit_price)} x {product.quantity}</p>
                                </div>
                                <div className="ddfcpos-order-product-price">
                                    <p>
                                        {formatPrice(product.line_total)}
                                    </p>
                                </div>
                            </div>
                        </div>
                    );
                });

                return (
                    <div className="ddfcpos-order-details-wrapper" style={getOrderDetailsWrapperStyle(this.props.tableView || !isMobileViewport())}>
                        <div className="ddfcpos-order-details-header">
                            <h2>{this.props.tableView ? __('Table Order', 'multipos-for-fluentcart') : __('Cart Details', 'multipos-for-fluentcart')}</h2>
                            <div>
                                <p>
                                    <UserOutlined />
                                    {getHoldOrderCustomerEmail(cart)}
                                </p>
                                {getHoldOrderTableName(cart) ?
                                    <p>
                                        <span className="ddfcpos-table-icon" role="img"><TableServiceIcon /></span>
                                        {getHoldOrderTableName(cart)}
                                    </p>
                                    : null}
                            </div>
                            {!this.props.tableView && isMobileViewport() ?
                                <button className="ddfcpos-button ddfcpos-button-secondary" onClick={this.handleBackOrderDetailsMobile}>{__('X', 'multipos-for-fluentcart')}</button>
                                : null}
                        </div>
                        <div className="ddfcpos-order-products">
                            {productsList}
                        </div>
                        <div className="ddfcpos-order-totals">
                            <div>
                                <p>{__('Subtotal', 'multipos-for-fluentcart')}</p>
                                <strong>{formatPrice(total.cart_subtotal, order.order_currency_symbol)}</strong>
                            </div>
                            <div>
                                <p>{__('Tax', 'multipos-for-fluentcart')}</p>
                                <strong>{formatPrice(total.tax_total)}</strong>
                            </div>
                            <div className="ddfcpos-order-total">
                                <p>{__('Total', 'multipos-for-fluentcart')}</p>
                                <strong>{formatPrice(total.cart_total)}</strong>
                            </div>
                            <div className="ddfcpos-hold-order-info">
                                {/* translators: %s: note added to the held cart */}
                                <p>{sprintf(__('Info: %s', 'multipos-for-fluentcart'), cart.info)}</p>
                            </div>
                            <div className="ddfcpos-hold-order-actions">
                                <button className="ddfcpos-button" onClick={this.createAddHoldCartHandler(cart)}>
                                    <ShoppingCartOutlined />
                                    {__('Add to Cart', 'multipos-for-fluentcart')}
                                </button>
                                <button className="ddfcpos-button" onClick={this.createDeleteHoldCartHandler(cart.id)}>
                                    <DeleteOutlined />
                                    {__('Delete', 'multipos-for-fluentcart')}
                                </button>
                            </div>
                        </div>
                    </div>
                );
            } else {
                if (!hasSalesOrderShape(order)) {
                    return getEmptyOrderDetailsMarkup(!isMobileViewport());
                }

                const productsList = getSalesOrderProducts(order).map(product => {
                    const orderCurrencySymbol = getOrderCurrencySymbol(order);
                    const metaData = getOrderProductMeta(product);

                    return (
                        <div className="ddfcpos-order-product" key={getProductKey(product)}>
                            <div className="ddfcpos-order-product-info">
                                <div className="ddfcpos-order-product-image" dangerouslySetInnerHTML={{ __html: getOrderProductImage(product) }}></div>
                                <div className="ddfcpos-order-product-details">
                                    <h4 title={ReactHtmlParser(product.name)}>{ReactHtmlParser(product.name)}</h4>
                                    <p>
                                        {formatPrice(product.unit_price, orderCurrencySymbol)} x {product.quantity}
                                    </p>
                                </div>
                                <div className="ddfcpos-order-product-price">
                                    <p>
                                        {formatPrice(product.line_total, orderCurrencySymbol)}
                                    </p>
                                </div>
                            </div>
                            {metaData.length ? <div className="ddfcpos-order-product-meta-info">{metaData}</div> : null}
                        </div>
                    );
                });

                const taxHTML = getOrderTaxMarkup(order);
                const tenderedHTML = getTenderedMarkup(order);
                const couponHTML = getOrderCouponsMarkup(order);
                const orderCurrencySymbol = getOrderCurrencySymbol(order);

                return (
                    <div className="ddfcpos-order-details-wrapper" style={getOrderDetailsWrapperStyle(!isMobileViewport())}>
                        <div className="ddfcpos-order-details-header">
                            {/* translators: %s: order number */}
                            <h2>{sprintf(__('Order #%s', 'multipos-for-fluentcart'), order.order_id)}</h2>
                            <p>
                                <UserOutlined />
                                {getOrderEmail(order)}
                            </p>
                            {isMobileViewport() ?
                                <button className="ddfcpos-button ddfcpos-button-secondary" onClick={this.handleBackOrderDetailsMobile}>{__('X', 'multipos-for-fluentcart')}</button>
                                : null}
                        </div>
                        <div className="ddfcpos-order-products">
                            {productsList}
                        </div>
                        <div className="ddfcpos-order-totals">
                            <div>
                                <p>{__('Subtotal', 'multipos-for-fluentcart')}</p>
                                <strong>{formatPrice(order.order_subtotal, orderCurrencySymbol)}</strong>
                            </div>
                            {taxHTML}
                            <div>
                                <p>{__('Applied Coupon(s)', 'multipos-for-fluentcart')}</p>
                                <strong>{couponHTML.length ? couponHTML : __('N/A', 'multipos-for-fluentcart')}</strong>
                            </div>
                            <div className="ddfcpos-order-total">
                                <p>{__('Total', 'multipos-for-fluentcart')}</p>
                                <strong>{formatPrice(order.order_total, orderCurrencySymbol)}</strong>
                            </div>
                            {tenderedHTML}
                            <div>
                                <p>{__('Change', 'multipos-for-fluentcart')}</p>
                                <strong>{formatPrice(order.change, orderCurrencySymbol)}</strong>
                            </div>
                            <button className="ddfcpos-button" onClick={this.createPrintInvoiceHandler(order)}>
                                <PrinterOutlined />
                                {__('Print Invoice', 'multipos-for-fluentcart')}
                            </button>
                        </div>
                    </div>
                );
            }
        }
    }
}

const mapStateToProps = state => ({
    orders: state.orders,
});

const mapDispatchToProps = dispatch => bindActionCreators({
    addHoldCartToCurrentCart,
    deleteHoldCart,
    updateDefaultCustomer,
    updateDefaultTable,
    printInvoice,
}, dispatch);

export default connect(mapStateToProps, mapDispatchToProps)(OrderDetails);
