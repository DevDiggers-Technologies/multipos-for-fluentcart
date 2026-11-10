import database from './database';

const CACHE_GROUPS = {
    outletData: [
        'temp',
        'outlet',
        'customers',
        'cart',
        'transactions',
        'coupon',
        'products',
        'taxes',
        'categories',
        'countries_and_states',
        'tables',
        'settings',
    ],
    currentOutletData: [
        'temp',
        'customers',
        'cart',
        'transactions',
        'coupon',
        'products',
        'taxes',
        'categories',
        'countries_and_states',
        'tables',
        'settings',
    ],
};
const getCacheTables = group => CACHE_GROUPS[ group ] || CACHE_GROUPS.outletData;
const getUniqueTableNames = tables => Array.from( new Set( tables ) );

const clearTables = tables => Promise.all(
    getUniqueTableNames( tables ).map( tableName => database.table( tableName ).clear() )
);

const removeSyncedOrders = () => database.table( 'orders' ).where( 'order_type' ).equals( 'online' ).delete();

export const clearPosCache = ( group = 'outletData' ) => {
    return clearTables( getCacheTables( group ) ).then( () => removeSyncedOrders() );
};
