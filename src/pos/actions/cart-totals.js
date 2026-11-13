import { __ } from '@wordpress/i18n';
import database from '../services/database';
import { notify } from '../services/notifications';
import { getPosObject, getTaxType, isTaxEnabled } from '../services/runtime';
import { getFirstCartScopedRecord } from '../services/cart-session';
import { normalizeRecordId, toDecimal } from '../utils/value';
import { COUPON } from '../state/action-types';

const getCouponTable = () => database.table('coupon');
const getPriceDecimalCount = () => getPosObject().price_num_decimals || 0;
const getTaxRoundAtSubtotal = () => getPosObject().tax_round_at_subtotal || '';
const convertRound = (number, decimalPlaces = getPriceDecimalCount()) => {
    number = parseFloat(number);
    const factor = Math.pow(10, decimalPlaces);
    const epsilon = 1 / (factor * 1e9);

    return Math.round((number + epsilon) * factor) / factor;
};
const getPrimaryCartRecord = cartRecords => Array.isArray(cartRecords) && cartRecords.length ? cartRecords[0] : null;
const getPrimaryCouponList = couponRecords => {
    const couponRecord = Array.isArray(couponRecords) && couponRecords.length ? couponRecords[0] : null;

    return couponRecord && Array.isArray(couponRecord.coupon) ? couponRecord.coupon : [];
};
const getActiveCartProducts = cartRecords => {
    const activeCartRecord = getPrimaryCartRecord(cartRecords);

    return activeCartRecord && Array.isArray(activeCartRecord.cart) ? activeCartRecord.cart : [];
};
const getCartScopedCoupons = (coupons, activeCartId) => {
    const couponRecord = getFirstCartScopedRecord(coupons, activeCartId);

    return couponRecord ? [couponRecord] : [];
};
const getStateCollections = state => ({
    taxes: Array.isArray(state.taxes) ? state.taxes : [],
    coupons: Array.isArray(state.coupon) ? state.coupon : [],
    products: state.products && Array.isArray(state.products.list) ? state.products.list : [],
});
const getStateDefaultCustomer = state => (state && state.customers && state.customers.defaultCustomer) ? state.customers.defaultCustomer : {};
const getStateCustomerEmail = state => getStateDefaultCustomer(state).email || '';
const resolveCartSourceProducts = (dbcart, state) => (
    Array.isArray(dbcart) && dbcart.length ? dbcart : (state && state.cart && Array.isArray(state.cart.list) ? state.cart.list : [])
);
const calculateCartSubtotal = cartProducts => cartProducts.reduce((sum, product) => (
    toDecimal(sum) + toDecimal(product.line_total)
), 0);
const calculateCartProductTaxTotal = cartProducts => cartProducts.reduce((sum, product) => {
    if (getTaxRoundAtSubtotal() === 'yes') {
        return toDecimal(sum) + toDecimal(product.tax);
    }

    return convertRound(sum) + convertRound(product.tax);
}, 0);
const buildCartTotals = (cartSubtotal, taxTotal, cartTotal) => ({
    cart_subtotal: convertRound(cartSubtotal),
    tax_total: convertRound(taxTotal),
    cart_total: convertRound(cartTotal),
});
const calcExclusiveTax = (price, rates) => {
    let taxes = 0;

    if (rates.length) {
        rates.forEach(rate => {
            if ('yes' !== rate.compound) {
                taxes += price * (rate.rate / 100);
            }
        });

        let preCompoundTotal = taxes;

        rates.forEach(rate => {
            if ('no' !== rate.compound) {
                const priceWithTax = price + preCompoundTotal;
                taxes += priceWithTax * (rate.rate / 100);
                preCompoundTotal = taxes;
            }
        });
    }

    return taxes;
};
const calculateExclusiveTax = (amount, taxes) => (
    !isTaxEnabled() || getTaxType() === 'yes' ? 0 : calcExclusiveTax(amount, taxes)
);
const calculateTax = (taxes, cartSubtotal, posCart, couponAmount) => {
    const productTotal = Math.max(cartSubtotal - couponAmount, 0);
    let totalTax;

    if (getTaxRoundAtSubtotal() === 'yes' || !posCart.length) {
        totalTax = calculateExclusiveTax(productTotal, taxes);
    } else {
        // FluentCart rounds tax per line, so the coupon is spread over the lines the way the server
        // does it ( whole cents, proportional floor shares, the remainder on the last line ) and each
        // line's tax is rounded on its own. Taxing the discounted total once can be a cent off the
        // order, and the server then records the sale as short paid.
        const lineCents = posCart.map(product => Math.max(0, Math.round(toDecimal(product.line_total) * 100)));
        const baseCents = lineCents.reduce((sum, cents) => sum + cents, 0);
        const couponCents = Math.min(Math.round(couponAmount * 100), baseCents);
        const lastIndex = lineCents.reduce((last, cents, index) => (cents > 0 ? index : last), -1);
        let given = 0;

        totalTax = posCart.reduce((sum, product, index) => {
            if (lineCents[index] <= 0) {
                return sum;
            }

            const share = index === lastIndex ? couponCents - given : Math.floor(couponCents * lineCents[index] / baseCents);
            given += share;

            // A line the outlet does not tax ( its own tax is zero ) still takes its coupon share.
            if (toDecimal(product.tax) <= 0) {
                return sum;
            }

            const lineTax = calculateExclusiveTax((lineCents[index] - share) / 100, taxes);

            return sum + Math.round(lineTax * 100) / 100;
        }, 0);
    }

    return {
        totalTax: totalTax < 0 ? 0 : totalTax,
        product_total: productTotal < 0 ? 0 : productTotal,
    };
};
const toRestrictionAmount = amount => {
    const numericAmount = parseFloat(amount);

    return Number.isNaN(numericAmount) ? 0 : numericAmount;
};
// Same match as the server's coupon check ( DDFCPOS_Coupon_Helper ): case-insensitive, `*` is a wildcard.
const emailMatchesRestriction = (email, pattern) => {
    const regex = new RegExp('^' + String(pattern).replace(/[.+?^${}()|[\]\\]/g, '\\$&').replace(/\*/g, '.*') + '$', 'i');

    return regex.test(String(email || ''));
};
const isCouponAllowedForCustomerAndAmount = (restrictions, state, cartSubtotal) => {
    const emailRestrictions = Array.isArray(restrictions.email_restrictions) ? restrictions.email_restrictions : [];
    const minimumAmount = toRestrictionAmount(restrictions.min_purchase_amount);
    const maximumAmount = toRestrictionAmount(restrictions.max_purchase_amount);

    if (emailRestrictions.length > 0 && !emailRestrictions.some(pattern => emailMatchesRestriction(getStateCustomerEmail(state), pattern))) {
        return false;
    }

    if (minimumAmount > 0 && minimumAmount > cartSubtotal) {
        return false;
    }

    if (maximumAmount > 0 && maximumAmount < cartSubtotal) {
        return false;
    }

    return true;
};
const getCartProductCategories = (productId, products) => {
    const matchedProduct = products.find(product => String(product.product_id) === String(productId));

    return matchedProduct && Array.isArray(matchedProduct.categories) ? matchedProduct.categories : [];
};
const couponHasPrice = coupon => coupon.value !== undefined;
const getCouponRestrictionLists = restrictions => ({
    includedProducts: (Array.isArray(restrictions.included_products) ? restrictions.included_products : []).map(String),
    excludedProducts: (Array.isArray(restrictions.excluded_products) ? restrictions.excluded_products : []).map(String),
    includedCategories: (Array.isArray(restrictions.included_categories) ? restrictions.included_categories : []).map(String),
    excludedCategories: (Array.isArray(restrictions.excluded_categories) ? restrictions.excluded_categories : []).map(String),
});
const getCartLineAmount = cartProduct => {
    const lineTotal = parseFloat(cartProduct.line_total);

    return Number.isFinite(lineTotal) ? lineTotal : (parseFloat(cartProduct.unit_price) || 0) * (parseFloat(cartProduct.quantity) || 0);
};
// The same rule the server uses when it books the coupon: a coupon limited to products or
// categories reaches only those catalog lines; an unrestricted one reaches every line.
const isCartLineEligibleForCoupon = (cartProduct, lists, catalogProducts) => {
    const productId = String(cartProduct.product_id);

    if (lists.excludedProducts.includes(productId)) {
        return false;
    }

    if (lists.includedProducts.length && !lists.includedProducts.includes(productId)) {
        return false;
    }

    if (lists.includedCategories.length || lists.excludedCategories.length) {
        const categories = getCartProductCategories(cartProduct.product_id, catalogProducts).map(String);

        if (lists.includedCategories.length && !lists.includedCategories.some(category => categories.includes(category))) {
            return false;
        }

        if (lists.excludedCategories.some(category => categories.includes(category))) {
            return false;
        }
    }

    return true;
};
const removeRestrictedCoupons = activeCartId => (
    getCouponTable().where('cart_id').equals(normalizeRecordId(activeCartId)).delete()
);
const getCouponAmount = (coupons, posCart, activeCartId, cartSubtotal, posProducts, dispatch, state) => {
    let couponAmount = 0;
    const couponRecord = Array.isArray(coupons) && coupons.length ? coupons[0] : null;

    if (!couponRecord || String(couponRecord.cart_id) !== String(activeCartId)) {
        return couponAmount;
    }

    const posCoupon = getPrimaryCouponList(coupons);
    // What is left of each line after the coupons before this one ( the server does the same ).
    const remaining = posCart.map(getCartLineAmount);

    posCoupon.forEach(value => {
        if (!value.restrictions || !couponHasPrice(value)) {
            return;
        }

        let allowed = isCouponAllowedForCustomerAndAmount(value.restrictions, state, cartSubtotal);

        if (value.restrictions.stackable === false && posCoupon.length > 1) {
            allowed = false;
        }

        const lists = getCouponRestrictionLists(value.restrictions);
        const eligible = posCart.map(cartProduct => allowed && isCartLineEligibleForCoupon(cartProduct, lists, posProducts));
        const base = remaining.reduce((sum, amount, index) => (eligible[index] ? sum + Math.max(amount, 0) : sum), 0);

        if (!allowed || base <= 0) {
            removeRestrictedCoupons(activeCartId);
            dispatch({ type: COUPON, coupon: [] });
            notify({
                title: __('Coupon Error', 'devdiggers-multipos-for-fluentcart'),
                message: __('This coupon is not applicable as per restrictions.', 'devdiggers-multipos-for-fluentcart'),
                type: 'danger',
            });

            return;
        }

        let thisCoupon = 'percentage' === value.type ? convertRound((parseFloat(value.value) * base) / 100) : parseFloat(value.value);
        const maximumDiscount = toRestrictionAmount(value.restrictions.max_discount_amount);

        // FluentCart coupons can cap the discount they give.
        if (maximumDiscount > 0) {
            thisCoupon = Math.min(thisCoupon, maximumDiscount);
        }

        thisCoupon = Math.min(thisCoupon, base);

        // Spread it over the lines it reached, so a second coupon works on what is left.
        remaining.forEach((amount, index) => {
            if (eligible[index]) {
                remaining[index] = amount - thisCoupon * Math.max(amount, 0) / base;
            }
        });

        couponAmount += thisCoupon;
    });

    return couponAmount;
};

export const getEmptyCartTotal = () => ({
    cart_subtotal: 0,
    tax_total: 0,
    cart_total: 0,
});

export const getCartTotal = (dbcart = '', state, dispatch) => {
    const cartProducts = resolveCartSourceProducts(dbcart, state);
    const {
        taxes: stateTax,
        coupons: stateCoupon,
        products: stateProducts,
    } = getStateCollections(state);
    const activeCartRecord = getPrimaryCartRecord(cartProducts);

    if (!activeCartRecord || !Array.isArray(activeCartRecord.cart) || !activeCartRecord.cart.length) {
        return getEmptyCartTotal();
    }

    const activeCartId = activeCartRecord.id;
    const cartCoupons = getCartScopedCoupons(stateCoupon, activeCartId);
    const posCart = getActiveCartProducts(cartProducts);
    const cartSubtotal = calculateCartSubtotal(posCart);
    const taxAmount = calculateCartProductTaxTotal(posCart);
    let couponAmount = 0;

    if (cartCoupons.length > 0) {
        couponAmount = getCouponAmount(cartCoupons, posCart, activeCartId, cartSubtotal, stateProducts, dispatch, state);
    }

    let finalAmount = couponAmount > 0 && cartSubtotal > 0 ? cartSubtotal - couponAmount : cartSubtotal;
    let taxTotal = parseFloat(taxAmount);

    if (couponAmount && getPrimaryCouponList(cartCoupons).length) {
        const result = calculateTax(stateTax, cartSubtotal, posCart, couponAmount);
        taxTotal = result.totalTax;
        finalAmount = result.product_total;
    }

    let cartTotal = finalAmount + taxTotal;

    if (finalAmount < 0) {
        cartTotal = 0;
        taxTotal = parseFloat(taxAmount);
    }

    return buildCartTotals(cartSubtotal, taxTotal, cartTotal);
};

export { convertRound };
