import database from './database';

export const hasRecordId = id => id !== null && id !== undefined;
const getCartTable = () => database.table( 'cart' );
const getCartListFromState = state => (
    state && state.cart && Array.isArray( state.cart.list ) ? state.cart.list : []
);
const matchesCartRecordId = ( record, activeCartId ) => (
    record && String( record.cart_id ) === String( activeCartId )
);
const getFirstRecord = records => ( Array.isArray( records ) && records.length ? records[0] : null );

export const getActiveCartRecordFromState = state => {
    const cartList = getCartListFromState( state );

    return getFirstRecord( cartList );
};

export const getActiveCartIdFromState = state => {
    const activeCartRecord = getActiveCartRecordFromState( state );

    return activeCartRecord ? activeCartRecord.id : null;
};

export const getActiveCartRecordIdFromStorage = () => (
    getCartTable().where( 'active_cart' ).equals( 1 ).first().then( cartRecord => (
        cartRecord && cartRecord.id !== undefined ? cartRecord.id : null
    ) )
);

export const resolveActiveCartId = getState => {
    const activeCartIdFromState = getActiveCartIdFromState( getState() );

    if ( hasRecordId( activeCartIdFromState ) ) {
        return Promise.resolve( activeCartIdFromState );
    }

    return getActiveCartRecordIdFromStorage();
};

export const getCartScopedRecords = ( records, activeCartId ) => (
    Array.isArray( records )
        ? records.filter( record => matchesCartRecordId( record, activeCartId ) )
        : []
);

export const getFirstCartScopedRecord = ( records, activeCartId ) => {
    const scopedRecords = getCartScopedRecords( records, activeCartId );

    return getFirstRecord( scopedRecords );
};
