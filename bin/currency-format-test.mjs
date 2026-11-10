/**
 * Self check for src/pos/utils/currency-format.js.
 *
 * Run: node bin/currency-format-test.mjs
 *
 * Guards the euro style store (decimal ",", thousands ".") where REST amounts
 * still arrive with a "." decimal. Line totals reach the POS as strings and
 * order totals as numbers, so both paths have to agree.
 */
import assert from 'node:assert';
import { readFileSync } from 'node:fs';

const source = readFileSync( new URL( '../src/pos/utils/currency-format.js', import.meta.url ), 'utf8' )
    .replace( "import { getPosObject } from '../services/runtime';", 'const getPosObject = () => globalThis.__ddfcposObject;' );

globalThis.__ddfcposObject = {
    currency_format_decimal_sep  : ',',
    currency_format_thousand_sep : '.',
    currency_format_num_decimals : '2',
    currency_format_symbol       : '€',
    currency_format              : '%v %s',
};

const { formatPrice } = await import( 'data:text/javascript;base64,' + Buffer.from( source ).toString( 'base64' ) );

const cases = [
    [ 0.01, '0,01 €' ],          // order totals arrive as numbers
    [ '0.01', '0,01 €' ],        // line totals arrive as strings
    [ '0.00813', '0,01 €' ],     // tax exclusive line subtotal, used to render 813,00
    [ '1234.56', '1.234,56 €' ],
    [ '1.234,56', '1.234,56 €' ],
    [ '-1234.5', '-1.234,50 €' ],
    [ '', '0,00 €' ],
    [ null, '0,00 €' ],
];

cases.forEach( ( [ input, expected ] ) => {
    const actual = formatPrice( input );

    assert.strictEqual( actual, expected, `formatPrice( ${ JSON.stringify( input ) } ) === ${ JSON.stringify( actual ) }, expected ${ JSON.stringify( expected ) }` );
} );

console.log( 'OK', cases.length, 'currency cases' );
