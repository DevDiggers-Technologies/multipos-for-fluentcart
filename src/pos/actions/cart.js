import { __ } from '@wordpress/i18n';
import { fetchRequest } from '../services/request';
import database from '../services/database';
import { notify } from '../services/notifications';
import { getPosApi, getPosObject } from '../services/runtime';
import { hasRecordId } from '../services/cart-session';
import { getCartTotal, getEmptyCartTotal } from './cart-totals';

import { CART } from '../state/action-types';
export { CART };
const getCartTable = () => database.table('cart');
const getProductsTable = () => database.table('products');
const getCouponTable = () => database.table('coupon');
const getPluginUrl = () => getPosObject().plugin_url || '';

export const setCart = (cart) => {
    return {
        type: CART,
        cart
    }
};

const notifyStockError = () => notify({
    title: __('Stock Error', 'multipos-for-fluentcart'),
    message: __('No stock available for this product.', 'multipos-for-fluentcart'),
    type: 'danger',
});

const shouldPlaySounds = state => {
    const settings = state.settings;

    return !settings.sounds || settings.sounds === 'enabled';
};

const playPosSound = name => {
    const audio = new Audio(`${getPluginUrl()}/assets/audio/${name}.mp3`);

    audio.play();
};

const buildCartState = (dbcart, state, dispatch) => {
    if (dbcart && dbcart.length) {
        return {
            list: dbcart,
            total: getCartTotal(dbcart, state, dispatch),
        };
    }

    return {
        list: [],
        total: getEmptyCartTotal(),
    };
};
const getPrimaryCartRecord = cartRecords => Array.isArray(cartRecords) && cartRecords.length ? cartRecords[0] : null;
const getActiveCartRecordId = cartRecords => {
    const activeCartRecord = getPrimaryCartRecord(cartRecords);

    return activeCartRecord ? activeCartRecord.id : 0;
};

const refreshCart = (dispatch, getState) => (
    loadActiveCartProducts().then(dbcart => {
        const cart = buildCartState(dbcart, getState(), dispatch);

        dispatch(setCart(cart));

        return cart;
    })
);

export const validateProductStock = cart_list => (dispatch) => {
    const postData = {
        cart_data: JSON.stringify(cart_list)
    };

    return fetchRequest(getPosApi().CHECK_CENTRALIZED_STOCK_ENDPOINT, postData).then(json => (
        json.out_of_stock_products !== undefined ? json : false
    ));
};

export const deleteNotValidProductsFromCart = cartData => (dispatch, getState) => {
    return updateActiveCart(cartData).then(updated => {
        if (updated) {
            return refreshCart(dispatch, getState);
        }
        return false;
    })
}

export const resetActiveCartStorage = () => (dispatch, getState) => {
    const cart = getState().cart;
    const activeCartRecord = getPrimaryCartRecord(cart.list);

    if (!activeCartRecord) {
        return Promise.resolve(false);
    }

    const activeCartId = activeCartRecord.id;
    return Promise.all([
        getCartTable().where('id').equals(activeCartId).delete(),
        getCouponTable().where('cart_id').equals(activeCartId).delete(),
    ]).then(() => true);
}

export const getCart = () => (dispatch, getState) => {
    return refreshCart(dispatch, getState);
};

export const getProductViaBarcode = barcode => (dispatch, getState) => {
    const isScannable = product => product.type === 'simple';

    return getProductsTable().where('barcode').equals(barcode).toArray().then(response => {
        const matches = response.filter(isScannable);

        if (matches.length) {
            return matches[0];
        }

        // Fall back to the SKU whatever the default barcode setting is.
        return getProductsTable().toArray().then(products => products.find(product => isScannable(product) && product.sku && product.sku === barcode));
    }).then(match => {
        if (match) {
            return match;
        }

        if (shouldPlaySounds(getState())) {
            playPosSound('error-sound');
        }

        return {};
    });
}

const getProductIdentifiers = product => (
    [product.product_id, product.id]
        .filter(value => value !== undefined && value !== null && value !== '')
        .map(value => String(value))
);
const isSellableProduct = product => (
    product &&
    product.type === 'simple' &&
    (!product.stock_status || product.stock_status === 'instock' || product.stock_status === 'onbackorder') &&
    (
        product.stock === undefined && product.stock_quantity === undefined ||
        parseFloat(product.stock !== undefined ? product.stock : product.stock_quantity) > 0 ||
        parseFloat(product.stock !== undefined ? product.stock : product.stock_quantity) === -1
    )
);
const findSellableProduct = productId => (
    getProductsTable().where("product_id").equals(productId).and(isSellableProduct).toArray().then(dbproducts => {
        if (dbproducts.length) {
            return dbproducts[0];
        }

        return getProductsTable().toArray().then(products => (
            products.find(product => isSellableProduct(product) && getProductIdentifiers(product).includes(String(productId))) || false
        ));
    })
);

const dispatchAddedCartProduct = ({ cart, dispatch, getState }) => {
    dispatch(setCart(cart));

    if (shouldPlaySounds(getState())) {
        playPosSound('add-to-cart');
    }
};

export const addToCart = (product_id, quantity = 1) => (dispatch, getState) => {
    return findSellableProduct(product_id).then(product => {
        if (!product) {
            notifyStockError();

            return false;
        }

        return loadActiveCartProducts().then(data => {
            const cartData = data.length ? data : [];

            return addProductToCartStorage(cartData, product, quantity);
        }).then(productData => {
            if (!productData) {
                return false;
            }

            return loadActiveCartProducts().then(cdata => {
                const cart = buildCartState(cdata || [], getState(), dispatch);

                dispatchAddedCartProduct({ cart, dispatch, getState });

                return productData;
            });
        });
    });
};

const findMatchingCartProduct = (cartProducts, product) => (
    cartProducts.find(cartProduct => (
        cartProduct['name'] === product.title &&
        String(cartProduct['product_id']) === String(product.product_id)
    ))
);

const hasInsufficientStock = (stock, quantity) => stock < quantity && stock !== -1;
const updateExistingCartProduct = (cartProduct, quantity) => {
    cartProduct['tax'] = cartProduct['tax'] / cartProduct['quantity'];
    cartProduct['quantity'] += quantity;
    cartProduct['line_total'] = parseFloat(cartProduct['quantity'] * cartProduct['unit_price']);
    cartProduct['tax'] = cartProduct['quantity'] * cartProduct['tax'];
    cartProduct['total'] = cartProduct['line_total'];

    return cartProduct;
};

const createCartProductEntry = ({ product, quantity, stock }) => {
    const unitPrice = product.price;
    const productTotal = parseFloat(quantity * unitPrice);

    return {
        // Unique per line, so two lines of one product never share a key.
        key: `${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 10)}`,
        id: product.product_id,
        sku: product.sku,
        slug: product.slug,
        categories: product.categories,
        image: product.image,
        product_id: product.product_id,
        parent_id: product.parent_id,
        name: product.title,
        stock: stock,
        original_price: unitPrice,
        quantity: quantity,
        price: unitPrice,
        on_sale: product.on_sale,
        unit_tax: product.tax,
        tax: product.tax,
        tax_label: product.tax_label,
        total: productTotal,
        unit_price: unitPrice,
        line_total: productTotal,
        type: product.type,
    };
};

const persistCartRecord = cartData => (
    getCartTable().put(cartData).then(() => cartData).catch('DataError', error => {
        return false;
    }).catch(Error, error => {
        return false;
    }).catch(error => {
        return false;
    })
);

function addProductToCartStorage(products, product, quantity) {
    const stock = product.stock;
    const activeCartId = getActiveCartRecordId(products);
    const productData = products.length ? [...products[0].cart] : [];
    const matchingCartProduct = findMatchingCartProduct(productData, product);

    if (matchingCartProduct) {
        if (hasInsufficientStock(stock, matchingCartProduct['quantity'] + quantity)) {
            notifyStockError();

            return Promise.resolve(false);
        }

        const productRecord = updateExistingCartProduct(matchingCartProduct, quantity);

        return persistCartRecord({
            id: activeCartId,
            active_cart: 1,
            cart: productData,
        }).then(stored => stored ? productRecord : false);
    }

    if (hasInsufficientStock(stock, quantity)) {
        notifyStockError();

        return Promise.resolve(false);
    }

    const productRecord = createCartProductEntry({ product, quantity, stock });

    return persistCartRecord({
        id: activeCartId,
        active_cart: 1,
        cart: productData.concat(productRecord),
    }).then(stored => stored ? productRecord : false);
}

const loadActiveCartProducts = () => {
    return getCartTable().where("active_cart").equals(1).toArray();
}

const isSameCartProduct = (candidate, cartProduct) => (
    String(candidate.product_id) === String(cartProduct.product_id) &&
    cartProduct.name === candidate.name &&
    (!hasRecordId(cartProduct.key) || String(cartProduct.key) === String(candidate.key))
);

export const removeCartProduct = cartProduct => (dispatch, getState) => {
    return loadActiveCartProducts().then(response => {
        const activeCartRecord = getPrimaryCartRecord(response);
        if (activeCartRecord && activeCartRecord.cart !== undefined) {
            const remainingProducts = activeCartRecord.cart.filter(item => !isSameCartProduct(item, cartProduct));

            return updateActiveCart(remainingProducts).then(updated => {
                if (!updated) {
                    return false;
                }

                return loadActiveCartProducts().then(dbcart => {
                    const state = getState();
                    const refreshedActiveCartRecord = getPrimaryCartRecord(dbcart);
                    const cart = refreshedActiveCartRecord && refreshedActiveCartRecord.cart.length
                        ? buildCartState(dbcart, state, dispatch)
                        : buildCartState([], state, dispatch);

                    dispatch(setCart(cart));

                    return true;
                });
            });
        }

        return false;
    });
};

const updateActiveCart = cartData => {
    return getCartTable().where("active_cart").equals(1).modify({
        cart: cartData
    });
}

export const updateCartProduct = (product_id, modifiedQuantity, product_name = '', cartProductKey = '') => (dispatch, getState) => {
    return loadActiveCartProducts().then(data => {
        const cartData = data.length ? data : [];

        return updateCartProductInStorage(cartData, product_id, modifiedQuantity, product_name, cartProductKey);
    }).then(updatedCartRecord => {
        if (!updatedCartRecord) {
            return false;
        }

        const cart = buildCartState([updatedCartRecord], getState(), dispatch);

        dispatch(setCart(cart));

        return updatedCartRecord;
    });
};

function updateCartProductInStorage(cartData, product_id, modifiedQuantity, product_name, cartProductKey) {
    const activeCartRecord = getPrimaryCartRecord(cartData);

    if (!activeCartRecord) {
        return Promise.resolve(false);
    }

    const product_data = [...activeCartRecord.cart];
    const cartRecord = {
        id: activeCartRecord.id,
        active_cart: 1,
        cart: product_data
    };
    const productRecord = product_data.find(record => (
        String(product_id) === String(record.product_id) &&
        product_name === record.name &&
        (!hasRecordId(cartProductKey) || String(cartProductKey) === String(record.key))
    ));

    if (productRecord) {
        const singleTax = parseFloat(productRecord.unit_tax);

        productRecord.quantity = modifiedQuantity;
        productRecord.tax = productRecord.quantity * singleTax;
        productRecord.line_total = parseFloat(productRecord.quantity * productRecord.unit_price);
        productRecord.total = productRecord.line_total;
    }

    return persistCartRecord(cartRecord);
}
const getNextCartId = cart => {
    const lastCartId = Number(cart && cart.id);

    return cart && !Number.isNaN(lastCartId) ? lastCartId + 1 : 0;
};

export const addNewCart = () => (dispatch, getState) => {
    return getCartTable().orderBy('id').last().then(cart => {
        const newCartId = getNextCartId(cart);
        const cart_obj = {
            id: newCartId,
            active_cart: 1,
            cart: []
        };
        // Reload from storage so the new cart's own ID is the active one. An empty list read as
        // cart 0, which showed a held cart's fees and coupons on the new, empty cart.
        return getCartTable().add(cart_obj).then(addResult => (
            addResult !== undefined ? refreshCart(dispatch, getState).then(() => addResult) : addResult
        ));
    });
}
