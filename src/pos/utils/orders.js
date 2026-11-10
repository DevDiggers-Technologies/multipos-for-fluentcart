export const getHoldOrderRecord = order => (
    order && Array.isArray( order.list ) ? order.list.find( Boolean ) || null : null
);

export const getOrderProducts = order => (
    order && Array.isArray( order.products ) ? order.products : []
);

export const getOrderCurrencySymbol = order => (
    order && order.order_currency_symbol ? order.order_currency_symbol : ''
);

export const getOrderBilling = order => (
    order && order.billing ? order.billing : {}
);
