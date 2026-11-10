/**
 * External dependencies
 */
import React from 'react';
import ReactDOM from 'react-dom';
import { Provider } from 'react-redux';
import { Route, Switch, Router } from 'react-router-dom';
import { createBrowserHistory } from 'history';

/**
 * Internal dependencies
 */
import store from './state/store';
import App from './App';
import Home from './components/tabs/home/home.jsx';
import Cart from './components/tabs/cart/cart.jsx';
import Customers from './components/tabs/customers/customers.jsx';
import Pay from './components/tabs/pay/pay.jsx';
import Orders from './components/tabs/orders/orders.jsx';
import Settings from './components/tabs/settings/settings.jsx';
import Statistics from './components/tabs/statistics/statistics.jsx';
import Tables from './components/tabs/tables/tables.jsx';
import { getRoutePath } from './services/routes';
import { getPosObject } from './services/runtime';

const browserHistory = createBrowserHistory();
const getSiteUrl = () => getPosObject().siteUrl || '';
const getRouteKey = page => `${getSiteUrl()}${page.path}`;
const createPageDefinition = ( name, path, component ) => ( { name, path, component } );
const getAppRoot = () => document.getElementById( 'app' );
const renderPageRoute = page => (
    <Route
        key={ getRouteKey( page ) }
        path={ getRouteKey( page ) }
        exact
        name={ page.name }
        render={ props => <App page={ page } { ...props } /> }
    />
);
const renderPosApplication = appRoot => {
    ReactDOM.render(
        <Provider store={store}>
            <Router history={ browserHistory }>
                <Switch>
                    { getPages().map( renderPageRoute ) }
                </Switch>
            </Router>
        </Provider>,
        appRoot
    );
};

export function getPages() {
    return [
        createPageDefinition( 'Home', getRoutePath(), Home ),
        createPageDefinition( 'Category', getRoutePath( '/category/:cid' ), Home ),
        createPageDefinition( 'Cart', getRoutePath( '/cart' ), Cart ),
        createPageDefinition( 'Customers', getRoutePath( '/customers' ), Customers ),
        createPageDefinition( 'Tables', getRoutePath( '/tables/:type' ), Tables ),
        createPageDefinition( 'Statistics', getRoutePath( '/statistics' ), Statistics ),
        createPageDefinition( 'Orders', getRoutePath( '/orders/:type' ), Orders ),
        createPageDefinition( 'Settings', getRoutePath( '/settings' ), Settings ),
        createPageDefinition( 'Pay', getRoutePath( '/pay' ), Pay ),
    ];
}

window.ddfcposStore = store;

const mountPosApplication = () => {
    const appRoot = getAppRoot();

    if ( ! appRoot ) {
        return;
    }

    renderPosApplication( appRoot );
};

if ( document.readyState === 'loading' ) {
    document.addEventListener( 'DOMContentLoaded', mountPosApplication );
} else {
    mountPosApplication();
}
