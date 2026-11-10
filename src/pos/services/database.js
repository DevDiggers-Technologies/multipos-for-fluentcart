import Dexie from 'dexie';

const database = new Dexie( 'ddfcpos' );
const getTempTable = () => database.temp;

const tables = {
    temp                : 'id',
    outlet              : 'id',
    customers           : 'id, first_name, last_name, username, email, phone, display_name',
    orders              : 'id, order_type',
    cart                : 'id, active_cart',
    transactions        : 'id, date',
    discount            : 'id, cart_id',
    coupon              : 'id, cart_id',
    fees                : 'id, cart_id',
    products            : 'product_id, type, barcode',
    taxes               : 'id',
    categories          : 'id',
    countries_and_states: 'base_country',
    tables              : 'slug',
    settings            : 'id',
}

// Dexie 2 needs every earlier version declared, so a browser that already holds the
// version 3 store (MultiPOS Pro) opens it. Version 5 drops the tables the free POS does
// not use, and the catalogue and carts cached by another build, which are loaded again.
database.version(1).stores( { ...tables, products: 'product_id, type, barcode_init' } );
database.version(2).stores( tables );
database.version(3).stores( tables );
database.version(4).stores( { discount: null, fees: null } );
database.version(5).stores( {} ).upgrade( transaction => Promise.all( [
    transaction.products.clear(),
    transaction.categories.clear(),
    transaction.cart.clear(),
    transaction.temp.clear(),
] ) );
// MultiPOS for FluentCart Pro adds the discount and fee tables back in version 6. Declaring
// the same version keeps one schema for both builds, so a till opens after switching either
// way. Keep the version list in step with Pro's database.js.
database.version(6).stores( { discount: 'id, cart_id', fees: 'id, cart_id' } );

const ensureTempBootstrapRecord = () => (
    getTempTable().get( 0 ).then( currentCartRecord => {
        if ( currentCartRecord ) {
            return currentCartRecord;
        }

        return getTempTable().put( { id: 0 } ).then( () => getTempTable().get( 0 ) );
    } )
);

ensureTempBootstrapRecord().catch( () => null );

export default database;
