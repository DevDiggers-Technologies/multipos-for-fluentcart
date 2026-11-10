import { combineReducers } from 'redux';
import {
    CART,
    CATEGORIES,
    COUNTRY_AND_STATES,
    COUPON,
    CUSTOMERS,
    HOLD_CARTS,
    ORDERS,
    OUTLET,
    PRODUCTS,
    SETTINGS,
    TABLES,
    TAXES,
    TRANSACTIONS,
} from './action-types';

const createValueReducer = ( actionType, stateKey ) => (
    ( state = [], action ) => action.type === actionType ? action[ stateKey ] : state
);

const createCollectionState = listKey => ( { [ listKey ]: [] } );
const createSearchState = searchKey => ( { [ searchKey ]: '' } );
const createCountryState = () => ( {
    countries   : [],
    base_country: '',
    states      : [],
} );
const createCartState = () => ( {
    list : [],
    total: '',
} );
const createTableState = () => ( {
    list        : [],
    defaultTable: {},
} );
const createSearchableCollectionState = ( listKey, searchKey, searchResultsKey ) => ( {
    [ listKey ]         : [],
    ...createSearchState( searchKey ),
    [ searchResultsKey ]: [],
} );
const createDefaultStates = () => ( {
    outlet              : {},
    ...createCollectionState( 'categories' ),
    countries_and_states: createCountryState(),
    ...createCollectionState( 'taxes' ),
    products: {
        ...createSearchableCollectionState( 'list', 'searchTerm', 'searchedProducts' ),
        isFetching: 0,
        category  : '',
        categoryProducts : [],
    },
    customers: {
        ...createSearchableCollectionState( 'list', 'searchTerm', 'searchedCustomers' ),
        defaultCustomer: {},
    },
    orders: {
        ...createSearchableCollectionState( 'list', 'searchTerm', 'searchedOrders' ),
    },
    ...createCollectionState( 'coupon' ),
    cart: createCartState(),
    ...createCollectionState( 'holdCarts' ),
    ...createCollectionState( 'transactions' ),
    tables  : createTableState(),
    settings: { id: 0 },
} );

export const baseReducers = {
    outlet              : createValueReducer( OUTLET, 'outlet' ),
    categories          : createValueReducer( CATEGORIES, 'categories' ),
    countries_and_states: createValueReducer( COUNTRY_AND_STATES, 'countries_and_states' ),
    customers           : createValueReducer( CUSTOMERS, 'customers' ),
    products            : createValueReducer( PRODUCTS, 'products' ),
    taxes               : createValueReducer( TAXES, 'taxes' ),
    coupon              : createValueReducer( COUPON, 'coupon' ),
    cart                : createValueReducer( CART, 'cart' ),
    holdCarts           : createValueReducer( HOLD_CARTS, 'holdCarts' ),
    orders              : createValueReducer( ORDERS, 'orders' ),
    transactions        : createValueReducer( TRANSACTIONS, 'transactions' ),
    tables              : createValueReducer( TABLES, 'tables' ),
    settings            : createValueReducer( SETTINGS, 'settings' ),
};

export const getDefaultStates = () => createDefaultStates();

export const getReducers = () => baseReducers;

export const createRootReducer = () => combineReducers( getReducers() );

export default createRootReducer;
