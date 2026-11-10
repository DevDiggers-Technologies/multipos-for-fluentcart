import { getPosObject } from '../services/runtime';

const getCurrencyDecimalSep = () => getPosObject().currency_format_decimal_sep || '.';
const getCurrencyNumDecimals = () => getPosObject().currency_format_num_decimals || 0;
const getCurrencyFormat = () => getPosObject().currency_format || '%s%v';
const getCurrencySymbol = () => getPosObject().currency_format_symbol || '';
const getCurrencyThousandsSep = () => getPosObject().currency_format_thousand_sep || ',';

const toNumber = value => {
    if ( Array.isArray( value ) ) {
        return value.map( toNumber );
    }

    if ( typeof value === 'number' ) {
        return Number.isFinite( value ) ? value : 0;
    }

    if ( value === null || value === undefined ) {
        return 0;
    }

    // Amounts arrive as REST values, which always use "." for decimals, and as
    // strings already formatted with the store separators. Parsing with the store
    // decimal separator alone dropped the "." out of REST values, so "0.00813"
    // became 813. Whichever separator comes last is the decimal one; every
    // separator before it is grouping.
    const cleaned = String( value )
        .replace( /\((.*)\)/, '-$1' )
        .replace( /[^0-9\-.,]/g, '' );

    const decimalPosition = Math.max( cleaned.lastIndexOf( '.' ), cleaned.lastIndexOf( ',' ) );
    const normalized = -1 === decimalPosition
        ? cleaned
        : `${ cleaned.slice( 0, decimalPosition ).replace( /[.,]/g, '' ) }.${ cleaned.slice( decimalPosition + 1 ) }`;

    const parsed = parseFloat( normalized );

    return Number.isNaN( parsed ) ? 0 : parsed;
};

const normalizePrecision = precision => {
    const parsed = Math.abs( Number( precision ) );

    return Number.isNaN( parsed ) ? 0 : parsed;
};

export const toFixed = ( value, precision = getCurrencyNumDecimals() ) => {
    const decimals = normalizePrecision( precision );
    const scale = Math.pow( 10, decimals );

    return ( Math.round( toNumber( value ) * scale ) / scale ).toFixed( decimals );
};

const withThousands = ( value, thousandSeparator, decimalSeparator, precision ) => {
    const fixed = toFixed( Math.abs( value ), precision );
    const parts = fixed.split( '.' );
    const whole = parts[0].replace( /\B(?=(\d{3})+(?!\d))/g, thousandSeparator );

    return parts[1] ? `${whole}${decimalSeparator}${parts[1]}` : whole;
};

const resolveFormat = value => {
    const format = getCurrencyFormat();

    if ( value > 0 ) {
        return format;
    }

    if ( value < 0 ) {
        return `-${format}`;
    }

    return format;
};
const replaceCurrencyTokens = ( format, value, currencySymbol = getCurrencySymbol() ) => format
    .replace( '%s', currencySymbol )
    .replace( '%v', value );

export const formatPrice = ( price, currencySymbol = getCurrencySymbol() ) => {
    if ( Array.isArray( price ) ) {
        return price.map( value => formatPrice( value, currencySymbol ) );
    }

    const amount = toNumber( price );
    const precision = getCurrencyNumDecimals();
    const formatted = withThousands(
        amount,
        getCurrencyThousandsSep(),
        getCurrencyDecimalSep(),
        precision
    );

    return replaceCurrencyTokens( resolveFormat( amount ), formatted, currencySymbol );
};
