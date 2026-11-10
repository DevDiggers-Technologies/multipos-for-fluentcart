import { formatPrice } from '../utils/currency-format';
import { __ } from '@wordpress/i18n';
import { getCurrentPosUser, getPosConfig, getPosLogoUrl } from '../services/runtime';
import { getOrderBilling, getOrderCurrencySymbol, getOrderProducts } from '../utils/orders';
import { hasEntries, parseWholeNumber } from '../utils/value';

const getLanguageAttributes = () => getPosConfig().language_attributes || '';
const isMobilePrintDevice = () => /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test( navigator.userAgent );
// One static receipt template, rendered by the server into the POS config.
const getInvoiceTemplate = (pageWidth, pageHeight, pageMargin) => (
    `<style>@page { size: ${pageWidth} ${pageHeight}; margin: ${pageMargin}; } ${getPosConfig().invoice_css || ''}</style>${getPosConfig().invoice_html || ''}`
);

const hasRecord = hasEntries;
const getInvoiceTableName = order => order.table && hasRecord(order.table) ? order.table.name : 'N/A';
const getOrderTaxLines = order => order && Array.isArray(order.tax_lines) ? order.tax_lines : [];
const getOrderCoupons = order => order && Array.isArray(order.coupons) ? order.coupons : [];
const getOrderPaymentMethods = order => order && Array.isArray(order.payment_methods) ? order.payment_methods : [];
const getBillingField = (billing, primaryKey, fallbackKey = primaryKey) => billing[primaryKey] || billing[fallbackKey] || '';
const createEmptyInvoiceTokens = () => ( {
    logo_invoice           : '${logo_invoice}',
    outlet_name            : '${outlet_name}',
    order_id               : '${order_id}',
    order_date             : '${order_date}',
    customer_first_name         : '${customer_first_name}',
    customer_last_name         : '${customer_last_name}',
    customer_phone         : '${customer_phone}',
    outlet_address1        : '${outlet_address1}',
    outlet_address2        : '${outlet_address2}',
    outlet_city            : '${outlet_city}',
    outlet_state           : '${outlet_state}',
    outlet_country         : '${outlet_country}',
    outlet_postcode        : '${outlet_postcode}',
    outlet_phone           : '${outlet_phone}',
    outlet_email           : '${outlet_email}',
    pro_name               : '${pro_name}',
    pro_quantity           : '${pro_quantity}',
    pro_unit_price         : '${pro_unit_price}',
    pro_total              : '${pro_total}',
    product_row            : '${product_row}',
    sub_total              : '${sub_total}',
    tax_label              : '${tax_label}',
    order_tax              : '${order_tax}',
    coupon_name            : '${coupon_name}',
    coupon_amount          : '${coupon_amount}',
    order_discount         : '${order_discount}',
    order_total            : '${order_total}',
    order_refunded         : '${order_refunded}',
    tendered_total         : '${tendered_total}',
    order_change           : '${order_change}',
    tendered_payment_name  : '${tendered_payment_name}',
    tendered_payment_amount: '${tendered_payment_amount}',
    table_name             : '${table_name}',
    cashier_name           : '${cashier_name}',
    total_quantity         : '${total_quantity}',
    store_name             : '${store_name}',
    store_url              : '${store_url}',
    outlet_full_address    : '${outlet_full_address}',
    customer_email         : '${customer_email}',
    customer_address       : '${customer_address}',
    payment_method         : '${payment_method}',
    print_date             : '${print_date}',
} );
const getTenderedPaymentMarkup = order => {
    const paymentMethods = getOrderPaymentMethods(order);

    if (paymentMethods.length) {
        return paymentMethods.reduce((paymentMarkup, paymentMethod) => ({
            names: `${paymentMarkup.names}<p>${escapeInvoiceText(paymentMethod.name)}</p>`,
            amounts: `${paymentMarkup.amounts}<p>${formatPrice(paymentMethod.amount, order.order_currency_symbol)}</p>`,
        }), { names: '', amounts: '' });
    }

    return {
        names: `<p>${escapeInvoiceText(order.payment_method_title)}</p>`,
        amounts: `<p>${formatPrice(order.tendered, order.order_currency_symbol)}</p>`,
    };
};

const getInvoiceProductRows = order => {
    let totalQuantity = 0;
    const orderProducts = getOrderProducts(order);

    const productRow = orderProducts.reduce((rowsMarkup, product) => {
        totalQuantity += parseWholeNumber(product.quantity);

        const quantity = escapeInvoiceText(product.quantity);

        return `${rowsMarkup}<tr>
                <td><p>${escapeInvoiceText(product.name)}</p></td>
                <td><p>${formatPrice(product.unit_price, order.order_currency_symbol)}</p></td>
                <td><p>${quantity}</p></td>
                <td><p>${formatPrice(product.line_total, order.order_currency_symbol)}</p></td>
            </tr>`;
    }, '<table><tbody>');

    return {
        productRow: `${productRow}</tbody></table>`,
        totalQuantity,
    };
};
const getOnlineOrderTaxMarkup = order => getOrderTaxLines(order).reduce( ( markup, tax ) => ( {
    tax_label: `${markup.tax_label}<p>${__('Tax', 'multipos-for-fluentcart')} (${escapeInvoiceText(tax.label)})</p>`,
    order_tax: `${markup.order_tax}<p>${formatPrice(tax.total, order.order_currency_symbol)}</p>`,
} ), { tax_label: '', order_tax: '' } );
const getOnlineCouponMarkup = order => getOrderCoupons(order).reduce( ( markup, coupon ) => ( {
    coupon_name  : `${markup.coupon_name}<p>${__('Coupon', 'multipos-for-fluentcart')} (${escapeInvoiceText(coupon.code)})</p>`,
    coupon_amount: `${markup.coupon_amount}<p>${formatPrice(-coupon.amount, getOrderCurrencySymbol(order))}</p>`,
} ), { coupon_name: '', coupon_amount: '' } );
const getOrderCustomerDetails = order => {
    const billing = getOrderBilling(order);

    return {
        customer_first_name: escapeInvoiceText(getBillingField(billing, 'first_name')),
        customer_last_name: escapeInvoiceText(getBillingField(billing, 'last_name')),
        customer_phone: escapeInvoiceText(getBillingField(billing, 'phone')),
    };
};
// Every data value (customer, product, outlet, tax...) is escaped before it enters
// the receipt HTML. Only the admin-authored template and our own markup stay as HTML.
const escapeInvoiceText = value => String( value === undefined || value === null ? '' : value ).replace( /[&<>"']/g, character => ( {
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#39;',
} )[ character ] );
const joinParts = ( parts, separator ) => parts.map( part => String( part || '' ).trim() ).filter( Boolean ).join( separator );
const joinAddress = parts => joinParts( parts, ', ' );
const getOutletFullAddress = outlet => joinAddress( [
    outlet.address1,
    outlet.address2,
    outlet.city,
    joinParts( [ outlet.state, outlet.postcode ], ' ' ),
    outlet.country,
] );
const getCustomerAddress = billing => joinAddress( [
    getBillingField( billing, 'address_1' ),
    getBillingField( billing, 'address_2' ),
    getBillingField( billing, 'city' ),
    joinParts( [ getBillingField( billing, 'state' ), getBillingField( billing, 'postcode' ) ], ' ' ),
    getBillingField( billing, 'country' ),
] );
const getPaymentMethodNames = order => {
    const names = getOrderPaymentMethods( order ).map( paymentMethod => paymentMethod.name ).filter( Boolean );

    return names.length ? names.join( ', ' ) : ( order.payment_method_title || order.payment_title || '' );
};
const getBaseInvoiceDetails = (outlet, order) => {
    const baseDetails = {
        logo_invoice   : escapeInvoiceText( getPosLogoUrl() ),
        outlet_name    : escapeInvoiceText( outlet.name ),
        outlet_address1: escapeInvoiceText( outlet.address1 ),
        outlet_address2: escapeInvoiceText( outlet.address2 ),
        outlet_city    : escapeInvoiceText( outlet.city ),
        outlet_state   : escapeInvoiceText( outlet.state ),
        outlet_country : escapeInvoiceText( outlet.country ),
        outlet_postcode: escapeInvoiceText( outlet.postcode ),
        outlet_phone   : escapeInvoiceText( outlet.phone ),
        outlet_email   : escapeInvoiceText( outlet.email ),
        cashier_name   : escapeInvoiceText( getCurrentPosUser().display_name ),
        total_quantity : 0,
        store_name     : escapeInvoiceText( getPosConfig().store_name ),
        store_url      : escapeInvoiceText( getPosConfig().store_url ),
        outlet_full_address: escapeInvoiceText( getOutletFullAddress( outlet ) ),
        print_date     : escapeInvoiceText( new Date().toLocaleString() ),
        customer_email : '',
        customer_address: '',
        payment_method : '',
    };

    if ( ! order ) {
        return {
            invoiceDetails: baseDetails,
            totalQuantity : 0,
        };
    }

    const tenderedMarkup = getTenderedPaymentMarkup(order);
    const invoiceProductRows = getInvoiceProductRows(order);

    return {
        totalQuantity: invoiceProductRows.totalQuantity,
        invoiceDetails: {
            ...baseDetails,
            table_name             : escapeInvoiceText( getInvoiceTableName(order) ),
            tendered_payment_name  : tenderedMarkup.names,
            tendered_payment_amount: tenderedMarkup.amounts,
            order_id               : `#${escapeInvoiceText( order.order_id )}`,
            order_date             : escapeInvoiceText( order.order_date ),
            sub_total              : formatPrice(order.order_subtotal, order.order_currency_symbol),
            order_total            : formatPrice(order.order_total, order.order_currency_symbol),
            order_refunded         : formatPrice(order.order_refunded, order.order_currency_symbol),
            order_change           : formatPrice(order.change, order.order_currency_symbol),
            tendered_total         : formatPrice(order.tendered, order.order_currency_symbol),
            product_row            : invoiceProductRows.productRow,
            order_discount         : formatPrice(order.discount, order.order_currency_symbol),
            total_quantity         : invoiceProductRows.totalQuantity,
            customer_email         : escapeInvoiceText( order.email || getOrderBilling( order ).email ),
            customer_address       : escapeInvoiceText( getCustomerAddress( getOrderBilling( order ) ) ),
            payment_method         : escapeInvoiceText( getPaymentMethodNames( order ) ),
            ...getOrderCustomerDetails(order),
            ...getOnlineOrderTaxMarkup(order),
            ...getOnlineCouponMarkup(order),
        },
    };
};

// Swaps ${token} for its value. No eval: the template is admin markup, the values are
// already-escaped data, so nothing a customer types can ever run as code.
const fillInvoiceTokens = (invoiceData, values) => invoiceData.replace(
    /\$\{\s*([A-Za-z_][A-Za-z0-9_]*)\s*\}/g,
    (token, key) => Object.prototype.hasOwnProperty.call(values, key) ? String(values[key]) : token
);

export const printInvoice = (order = '', print = true) => (dispatch, getState) => {
    const currentState = getState();
    const outlet = currentState.outlet || {};
    const settings = currentState.settings || {};
    const { invoiceDetails } = getBaseInvoiceDetails(outlet, order);

    const pageWidth = settings.printer_width;
    const pageHeight = settings.printer_height;
    const pageMargin = settings.printer_margin;

    const invoiceData = fillInvoiceTokens(getInvoiceTemplate(pageWidth, pageHeight, pageMargin), { ...createEmptyInvoiceTokens(), ...invoiceDetails });

    if (print) {
        openPrintWindow(invoiceData);
    }

    return invoiceData;
}

const openPrintWindow = (printContents, style = '') => {
    if (isMobilePrintDevice()) {
        const printWindow = window.open();

        if (!printWindow) {
            return;
        }

        printWindow.document.open();
        printWindow.document.clear();

        printWindow.document.writeln(`<html ${getLanguageAttributes()}><head><title></title>${style}`);
        printWindow.document.writeln('</head><body>');
        printWindow.document.writeln(printContents);
        printWindow.document.writeln('</body></html>');
        printWindow.document.close(); // necessary for IE >= 10

        printWindow.addEventListener('load', function () {
            setTimeout(() => {
                printWindow.focus(); // necessary for IE >= 10*-/
                printWindow.print();
            }, 500);
        }, true);
    } else {
        const iframeElement = document.createElement('iframe');

        iframeElement.name = 'frame1';
        document.body.appendChild(iframeElement);

        const iframeDoc = iframeElement.contentWindow ? iframeElement.contentWindow : iframeElement.contentDocument && iframeElement.contentDocument.document ? iframeElement.contentDocument.document : iframeElement.contentDocument;

        if (!iframeDoc || !iframeDoc.document) {
            document.body.removeChild(iframeElement);
            return;
        }

        iframeDoc.document.open();
        iframeDoc.document.write(`<html ${getLanguageAttributes()}><head><title></title>${style}`);
        iframeDoc.document.write('</head><body>');
        iframeDoc.document.write(printContents);
        iframeDoc.document.write('</body></html>');
        iframeDoc.document.close();

        setTimeout(() => {
            if (window.frames['frame1']) {
                window.frames['frame1'].focus();
                window.frames['frame1'].print();
            }
            document.body.removeChild(iframeElement);
        }, 500);
    }
}
