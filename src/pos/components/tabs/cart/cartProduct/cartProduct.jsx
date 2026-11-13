import React, { Component, Fragment } from 'react';
import { connect } from 'react-redux';
import { bindActionCreators } from 'redux';
import { updateCartProduct, removeCartProduct } from '../../../../actions/cart';
import ReactHtmlParser from 'react-html-parser';
import { formatPrice } from '../../../../utils/currency-format';
import { __ } from '@wordpress/i18n';
import { CheckOutlined, DeleteOutlined, DownOutlined, MinusOutlined, PlusOutlined, UpOutlined } from '@ant-design/icons';
import { notify } from '../../../../services/notifications';
import { getPosConfig } from '../../../../services/runtime';
import { parseWholeNumber } from '../../../../utils/value';
import { isEnterKey } from '../../../../utils/event';

const toPositiveQuantity = quantity => {
    const normalizedQuantity = parseWholeNumber(quantity);

    return Number.isNaN(normalizedQuantity) ? 1 : normalizedQuantity;
};

const getEditableQuantity = (stateQuantity, cartProduct) => {
    const enteredQuantity = toPositiveQuantity(stateQuantity ? stateQuantity : cartProduct.quantity);

    return enteredQuantity <= 1 ? 1 : enteredQuantity;
};
const getTaxLabel = () => getPosConfig().tax_label || '';
const getCartProduct = props => props.cartProduct || {};
// -1 is stock that is not tracked: no limit, as the cart's add-to-cart check treats it.
const getCartProductStock = cartProduct => (
    cartProduct.stock === undefined || cartProduct.stock === null || parseFloat(cartProduct.stock) === -1
        ? Number.MAX_SAFE_INTEGER
        : cartProduct.stock
);

const getBoundedQuantity = (enteredQuantity, cartProduct) => {
    const normalizedQuantity = enteredQuantity <= 1 ? 1 : enteredQuantity;
    const stock = getCartProductStock(cartProduct);

    return normalizedQuantity <= stock ? normalizedQuantity : stock;
};

class CartProduct extends Component {

    constructor(props) {
        super(props);

        this.state = {
            showEditCartProductDropdown: false,
            cartProductQuantity: null,
        }
    }

    handleRemove = (event, cartProduct) => {
        event.stopPropagation();
        this.props.removeCartProduct(cartProduct);
    }

    handleToggleEditCartProductDropdown = event => {
        if (event) {
            event.stopPropagation();
        }

        this.setState(prevState => ({
            showEditCartProductDropdown: !prevState.showEditCartProductDropdown
        }));
    }

    handleChangeCartProductQuantity = event => {
        if (isEnterKey(event)) {
            this.handleUpdateCartProduct();
        }

        const cartProduct = getCartProduct(this.props);
        const enteredQuantity = event.target.value;

        this.setState({
            cartProductQuantity: getBoundedQuantity(enteredQuantity, cartProduct)
        });
    }

    handleIncreaseDecreaseQuantity = change => {
        const cartProduct = getCartProduct(this.props);
        const enteredQuantity = toPositiveQuantity(this.state.cartProductQuantity ? this.state.cartProductQuantity : cartProduct.quantity) + change;

        this.setState({
            cartProductQuantity: getBoundedQuantity(enteredQuantity, cartProduct)
        });
    }

    handleEditCartProductFormSubmit = event => {
        event.preventDefault();
    }

    handleCartProductInputKeyDown = event => {
        if (isEnterKey(event)) {
            event.preventDefault();
            this.handleUpdateCartProduct();
        }
    }

    createQuantityChangeHandler = change => () => this.handleIncreaseDecreaseQuantity(change)
    handleProductQuantityInput = event => this.handleChangeCartProductQuantity(event)
    handleProductInputKeyDown = event => this.handleCartProductInputKeyDown(event)
    createRemoveHandler = cartProduct => event => this.handleRemove(event, cartProduct)
    handleCartProductUpdateClick = () => this.handleUpdateCartProduct()

    handleUpdateCartProduct = () => {
        const cartProduct = getCartProduct(this.props);
        const quantity = getEditableQuantity(this.state.cartProductQuantity, cartProduct);

        if (getCartProductStock(cartProduct) < quantity) {
            notify({
                title: __('Stock Error', 'devdiggers-multipos-for-fluentcart'),
                message: __('There is no more stocks available for this product.', 'devdiggers-multipos-for-fluentcart'),
                type: 'danger',
            });
        } else {
            this.props.updateCartProduct(cartProduct.product_id, quantity, cartProduct.name, cartProduct.key);
        }

        this.handleToggleEditCartProductDropdown();
    }

    render() {
        const cartProduct = getCartProduct(this.props);
        let editCartProductDropdownHTML = null;
        const editableQuantity = getEditableQuantity(this.state.cartProductQuantity, cartProduct);

        if (this.state.showEditCartProductDropdown) {
            editCartProductDropdownHTML = (
                <Fragment>
                    <form className="ddfcpos-edit-cart-product" onSubmit={this.handleEditCartProductFormSubmit}>
                        <div className="ddfcpos-edit-cart-product-column">
                            <label>{__('Quantity', 'devdiggers-multipos-for-fluentcart')}</label>
                            <div className="ddfcpos-edit-cart-product-quantity">
                                <div className="ddfcpos-icon-card" title={__('Decrease Quantity', 'devdiggers-multipos-for-fluentcart')} onClick={this.createQuantityChangeHandler(-1)}>
                                    <MinusOutlined />
                                </div>
                                <input type="number" min="1" max={getCartProductStock(cartProduct)} value={editableQuantity} onChange={this.handleProductQuantityInput} onKeyDown={this.handleProductInputKeyDown} />
                                <div className="ddfcpos-icon-card" title={__('Increase Quantity', 'devdiggers-multipos-for-fluentcart')} onClick={this.createQuantityChangeHandler(1)}>
                                    <PlusOutlined />
                                </div>
                            </div>
                        </div>
                        <div className="ddfcpos-edit-cart-product-column ddfcpos-edit-cart-product-action">
                            <button type="button" onClick={this.handleCartProductUpdateClick} title={__('Update', 'devdiggers-multipos-for-fluentcart')} aria-label={__('Update', 'devdiggers-multipos-for-fluentcart')}>
                                <CheckOutlined />
                            </button>
                        </div>
                    </form>
                </Fragment>
            );
        }

        return (
            <Fragment>
                <div className={'ddfcpos-cart-product product-id-' + cartProduct.product_id + (this.state.showEditCartProductDropdown ? ' ddfcpos-cart-product-open' : '')} onClick={this.handleToggleEditCartProductDropdown}>
                    <div className="ddfcpos-cart-product-action" onClick={this.handleToggleEditCartProductDropdown}>
                        {this.state.showEditCartProductDropdown ?
                            <UpOutlined />
                            :
                            <DownOutlined />
                        }
                    </div>
                    <div className="ddfcpos-cart-product-image" dangerouslySetInnerHTML={{ __html: cartProduct.image }}></div>
                    <div className="ddfcpos-cart-product-details">
                        <p title={ReactHtmlParser(cartProduct.name)}>{ReactHtmlParser(cartProduct.name)}</p>
                        <p className="ddfcpos-cart-product-unit-price">
                            <span>{formatPrice(cartProduct.unit_price)} x {cartProduct.quantity}</span>
                        </p>
                    </div>
                    <div className="ddfcpos-cart-product-price">
                        <p className="ddfcpos-cart-product-total-price">
                            <span>{formatPrice(cartProduct.line_total)}</span>
                            {ReactHtmlParser(getTaxLabel())}
                        </p>
                    </div>
                    <div className="ddfcpos-cart-product-action" onClick={this.createRemoveHandler(cartProduct)}>
                        <DeleteOutlined />
                    </div>
                </div>
                {editCartProductDropdownHTML}
            </Fragment>
        );
    }
}

const mapDispatchToProps = dispatch => bindActionCreators({ updateCartProduct, removeCartProduct }, dispatch);

export default connect(null, mapDispatchToProps)(CartProduct);
