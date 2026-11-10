import React, { Component } from 'react';
import Cart from './cart/cart';

const isDesktopViewport = () => window.screen.width >= 768;
const getTabsContainerStyle = shouldShowCart => (
    shouldShowCart ? {} : { gridTemplateColumns: 'unset' }
);
const getVisibleCartPages = props => [ 'Home', 'Category', 'Pay' ];
const getPage = props => props.page || {};
const getPageName = props => getPage( props ).name || '';
const getPageComponent = props => getPage( props ).component || null;

class Tabs extends Component {
    showCart = () => {
        const pageName = getPageName( this.props );
        const showCartPages = getVisibleCartPages( this.props );

        return isDesktopViewport() && Array.isArray( showCartPages ) && showCartPages.includes( pageName );
    }

    render() {
        const showCart = this.showCart();
        const style = getTabsContainerStyle( showCart );
        const PageComponent = getPageComponent( this.props );

        return (
            <div className="ddfcpos-tabs-container" style={style}>
                { PageComponent ? React.createElement( PageComponent, this.props ) : null }
                { showCart ?
                    <Cart {...this.props} />
                : null }
            </div>
        );
    }
}

export default Tabs;
