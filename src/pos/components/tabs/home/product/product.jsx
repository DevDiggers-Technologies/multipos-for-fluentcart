import React, { Component } from 'react';
import { connect } from 'react-redux';
import { bindActionCreators } from 'redux';
import { addToCart } from '../../../../actions/cart';
import ReactHtmlParser from 'react-html-parser';
import { sprintf, __ } from '@wordpress/i18n';
import { getPosConfig } from '../../../../services/runtime';

const getProductLayoutClass = () => ('image_top' === getPosConfig().product_layout ? 'image-top' : 'image-left');
const getProductImageMarkup = product => product && product.image ? product.image : `<img width="150" height="150" src="${getPosConfig()['placeholder_image'] || ''}" class="attachment-thumbnail size-thumbnail" />`;

const getProductStockMarkup = product => {
    if (!product || !getPosConfig().show_product_stock_enabled) {
        return null;
    }

    if (product.stock_status === 'onbackorder') {
        return (
            <mark className="instock">
                {product.stock_quantity > 0
                    /* translators: %s: backordered stock quantity */
                    ? sprintf(__('On Backorder(%s)', 'devdiggers-multipos-for-fluentcart'), product.stock_quantity)
                    : __('On Backorder', 'devdiggers-multipos-for-fluentcart')}
            </mark>
        );
    }

    return (
        <mark className="instock">
            {product.stock_quantity > 0
                /* translators: %s: stock quantity */
                ? sprintf(__('In Stock(%s)', 'devdiggers-multipos-for-fluentcart'), product.stock_quantity)
                : __('In Stock', 'devdiggers-multipos-for-fluentcart')}
        </mark>
    );
};

class Product extends Component {
    handleProductClick = product => {
        if (!product || product.type !== 'simple') {
            return;
        }

        this.props.addToCart(product.product_id, 1);
    }

    createProductClickHandler = product => () => this.handleProductClick(product)

    render() {
        const product = this.props.product || {};
        const stockHTML = getProductStockMarkup(product);
        const productCardClass = getProductLayoutClass();

        return (
            <div className={'ddfcpos-product-card ddfcpos-product-' + productCardClass} onClick={this.createProductClickHandler(product)} >
                <div className="ddfcpos-product-thumbnail" dangerouslySetInnerHTML={{ __html: getProductImageMarkup(product) }}></div>
                <div className="ddfcpos-product-details">
                    <h2 title={ReactHtmlParser(product.title || '')}>{ReactHtmlParser(product.title || '')}</h2>
                    <p>{ReactHtmlParser(product.price_html || '')}</p>
                    {stockHTML}
                </div>
            </div>
        );
    }
}

const mapDispatchToProps = dispatch => bindActionCreators({
    addToCart,
}, dispatch);

export default connect(null, mapDispatchToProps)(Product);
