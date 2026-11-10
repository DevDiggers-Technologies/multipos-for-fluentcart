import { __ } from '@wordpress/i18n';
import database from '../services/database';
import { getCart } from './cart';
import { notify } from '../services/notifications';
import { getCartScopedRecords, getFirstCartScopedRecord, hasRecordId, resolveActiveCartId } from '../services/cart-session';

import { COUPON } from '../state/action-types';
export { COUPON };

export const setCoupon = coupon => {
    return {
        type: COUPON,
        coupon
    }
};

const loadCouponsFromStorage = () => database.table('coupon').toArray();
export const getCoupons = () => (dispatch, getState) => {
    return Promise.all([resolveActiveCartId(getState), loadCouponsFromStorage()]).then(([activeCartId, result]) => {
        const activeCoupons = hasRecordId(activeCartId) ? getCartScopedRecords(result, activeCartId) : [];
        dispatch(setCoupon(activeCoupons));

        return activeCoupons;
    });
};

const refreshCouponAndCartState = (dispatch, coupons) => {
    dispatch(setCoupon(coupons));
    dispatch(getCart());
};
const getCouponEntries = couponRecord => Array.isArray(couponRecord && couponRecord.coupon) ? couponRecord.coupon : [];
const createCouponRecord = (activeCartId, couponList) => ({
    id: activeCartId,
    cart_id: activeCartId,
    coupon: couponList,
});
const normalizeCouponRecord = (couponRecord, activeCartId, couponList) => ({
    ...(couponRecord || {}),
    ...createCouponRecord(activeCartId, couponList),
});

const notifyCouponApplied = () => notify({
    title: __('Coupon Applied', 'multipos-for-fluentcart'),
    message: __('Coupon is applied successfully in the cart.', 'multipos-for-fluentcart'),
    type: 'success'
});

const notifyCouponDuplicate = () => notify({
    title: __('Coupon Error', 'multipos-for-fluentcart'),
    message: __('Coupon is already applied in the cart.', 'multipos-for-fluentcart'),
    type: 'danger'
});

const notifyCouponRemoved = () => notify({
    title: __('Coupon Removed', 'multipos-for-fluentcart'),
    message: __('Coupon is successfully removed from the cart.', 'multipos-for-fluentcart'),
    type: 'success'
});

export const applyCoupon = coupon => (dispatch, getState) => {
    return resolveActiveCartId(getState).then(activeCartId => {
        if (!hasRecordId(activeCartId)) {
            return false;
        }

        return loadCouponsFromStorage().then(records => {
            const couponRecord = getFirstCartScopedRecord(records, activeCartId);
            if (couponRecord) {
                const appliedCoupon = getCouponEntries(couponRecord).filter(obj => coupon.code === obj.code);

                if (appliedCoupon.length === 0) {
                    const updatedCoupons = getCouponEntries(couponRecord).concat(coupon);
                    const nextCouponRecord = normalizeCouponRecord(couponRecord, activeCartId, updatedCoupons);

                    return database.table('coupon').put(nextCouponRecord).then(coData => {
                        if (coData !== undefined) {
                            notifyCouponApplied();
                            refreshCouponAndCartState(dispatch, [nextCouponRecord]);

                            return true;
                        }

                        return false;
                    });
                }

                notifyCouponDuplicate();

                return false;
            }

            const newCouponRecord = createCouponRecord(activeCartId, [coupon]);

            return database.table('coupon').put(newCouponRecord).then(() => {
                notifyCouponApplied();

                refreshCouponAndCartState(dispatch, [newCouponRecord]);

                return true;
            });
        });
    });
}

export const removeCoupon = couponcode => (dispatch, getState) => {
    return resolveActiveCartId(getState).then(activeCartId => {
        if (!hasRecordId(activeCartId)) {
            return false;
        }

        return loadCouponsFromStorage().then(records => {
            const couponRecord = getFirstCartScopedRecord(records, activeCartId);
            if (couponRecord) {
                const latestCoupon = getCouponEntries(couponRecord).filter(coup => coup.code !== couponcode);
                const nextCouponRecord = normalizeCouponRecord(couponRecord, activeCartId, latestCoupon);

                return database.table('coupon').put(nextCouponRecord).then(coData => {
                    if (coData !== undefined) {
                        refreshCouponAndCartState(dispatch, [nextCouponRecord]);
                        notifyCouponRemoved();

                        return true;
                    }

                    return false;
                });
            }

            return false;
        });
    });
}
