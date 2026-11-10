import { createStore, applyMiddleware } from 'redux';
import thunk from 'redux-thunk';
import { composeWithDevTools } from 'redux-devtools-extension';
import { createRootReducer, getDefaultStates } from './reducers';

import { createStateSyncMiddleware } from 'redux-state-sync';

const defaultStates = getDefaultStates();
const rootReducer = createRootReducer();
// Tabs of this POS share state over their own channel; the library default is shared with every
// other app on the site that uses it (MultiPOS for WooCommerce, for one).
const stateSyncConfig = { channel: 'ddfcpos_state_sync' };
const middlewares = [ createStateSyncMiddleware( stateSyncConfig ) ];
const createStoreEnhancer = () => composeWithDevTools( applyMiddleware( thunk, ...middlewares ) );

const store = createStore(
    rootReducer,
    defaultStates,
    createStoreEnhancer()
);

export default store;
