import React, { Component, Fragment } from 'react';
import { __ } from '@wordpress/i18n';
import { Link } from 'react-router-dom';
import { DoubleRightOutlined, DollarOutlined, HomeOutlined, ShoppingCartOutlined, LogoutOutlined, SettingOutlined, ShoppingOutlined, TeamOutlined } from '@ant-design/icons';
import { connect } from 'react-redux';
import { clearPosCache } from '../../services/cache';
import { getPosRoute } from '../../services/routes';
import { getPosLogoUrl, getPosObject } from '../../services/runtime';
import { isMobileViewport } from '../../utils/navigation';
import { getOutletMode } from '../../utils/value';
import TableServiceIcon from '../icons/tableServiceIcon.jsx';

const isMenuOpen = menuContainer => menuContainer && menuContainer.classList.contains('ddfcpos-menu-open');
const getPopupOverlay = () => document.querySelector('.ddfcpos-popup-overlay');
const getMenuContainer = () => document.querySelector('.ddfcpos-menu-container');
const getLogoutUrl = () => getPosObject().logout_url || '#';
const getPageName = page => page && page.name !== undefined ? page.name : '';
const getMenuDomState = () => ({
    menuContainer: getMenuContainer(),
    popupOverlay: getPopupOverlay(),
});
class Menu extends Component {
    getActiveMenuId = () => {
        const activeMenuMap = {
            Home: 'home',
            Category: 'home',
            Cart: 'cart',
            Customers: 'customers',
            Statistics: 'statistics',
            Orders: 'orders',
            Tables: 'tables',
            Settings: 'settings',
        };

        return activeMenuMap[getPageName(this.props.page)] || '';
    };

    getMenuClassName = menuId => `ddfcpos-menu-card${this.getActiveMenuId() === menuId ? ' ddfcpos-menu-active' : ''}`;

    shouldDisplayMenu = (menu, isRestaurantMode) => {
        const outletMode = getOutletMode(this.props.outlet) || 'grocery';

        if (!isRestaurantMode && (outletMode !== 'grocery' || menu.mode !== 'grocery')) {
            return false;
        }

        return menu.id !== 'cart' || isMobileViewport();
    };

    getMenus() {
        const menus = [
            {
                id: 'home',
                to: getPosRoute(),
                classname: this.getMenuClassName('home'),
                icon_component: HomeOutlined,
                mode: 'grocery',
                text: __('Home', 'multipos-for-fluentcart')
            },
            {
                id: 'cart',
                to: getPosRoute('/cart'),
                classname: this.getMenuClassName('cart'),
                icon_component: ShoppingCartOutlined,
                mode: 'grocery',
                text: __('Cart', 'multipos-for-fluentcart')
            },
            {
                id: 'customers',
                to: getPosRoute('/customers'),
                classname: this.getMenuClassName('customers'),
                icon_component: TeamOutlined,
                mode: 'grocery',
                text: __('Customers', 'multipos-for-fluentcart')
            },
            {
                id: 'tables',
                to: getPosRoute('/tables/all'),
                classname: this.getMenuClassName('tables'),
                icon_component_jsx: true,
                icon_component: <span className="ddfcpos-table-icon" role="img"><TableServiceIcon /></span>,
                mode: 'restaurant',
                text: __('Tables', 'multipos-for-fluentcart')
            },
            {
                id: 'orders',
                to: getPosRoute('/orders/online'),
                classname: this.getMenuClassName('orders'),
                icon_component: ShoppingOutlined,
                mode: 'grocery',
                text: __('Orders', 'multipos-for-fluentcart')
            },
            {
                id: 'statistics',
                to: getPosRoute('/statistics'),
                classname: this.getMenuClassName('statistics'),
                icon_component: DollarOutlined,
                mode: 'grocery',
                text: __('Statistics', 'multipos-for-fluentcart')
            },
            {
                id: 'settings',
                to: getPosRoute('/settings'),
                classname: this.getMenuClassName('settings'),
                icon_component: SettingOutlined,
                mode: 'grocery',
                text: __('Settings', 'multipos-for-fluentcart')
            },
        ];

        return menus;
    }

    handleLogout = event => {
        if (confirm(__('Do you want to delete the outlet data from the browser? It will load the latest one when you login.', 'multipos-for-fluentcart'))) {
            clearPosCache('outletData');
        }
    }

    handleMenuCollapse = () => {
        const { menuContainer, popupOverlay } = getMenuDomState();
        if (!menuContainer || !popupOverlay) {
            return;
        }

        if (isMenuOpen(menuContainer)) {
            menuContainer.classList.remove('ddfcpos-menu-open');
            popupOverlay.classList.add('ddfcpos-hide');
        } else {
            menuContainer.classList.add('ddfcpos-menu-open');
            popupOverlay.classList.remove('ddfcpos-hide');
        }
    }
    handleLogoutClick = event => this.handleLogout(event)
    render() {
        const isRestaurantMode = (getOutletMode(this.props.outlet) || 'grocery') === 'restaurant';
        const menusListHTML = this.getMenus()
            .filter(menu => this.shouldDisplayMenu(menu, isRestaurantMode))
            .map(menu => (
                <Link className={menu.classname} key={menu.to} to={menu.to}>
                    {menu.icon_component_jsx ? menu.icon_component : React.createElement(menu.icon_component)}
                    {menu.text}
                </Link>
            ));

        return (
            <Fragment>
                {isMobileViewport() ?
                    <Fragment>
                        <div className="ddfcpos-collapse-icon-wrapper">
                            <span className="ddfcpos-collapse-icon" onClick={this.handleMenuCollapse}>
                                <DoubleRightOutlined />
                            </span>
                        </div>
                    </Fragment>
                    : null}

                <div className="ddfcpos-popup-overlay ddfcpos-hide" onClick={this.handleMenuCollapse}></div>
                <div className="ddfcpos-menu-container">
                    <nav>
                        <div className="ddfcpos-menu-logo">
                            <img alt={__('POS Logo', 'multipos-for-fluentcart')} src={getPosLogoUrl()} width="80" height="80" />
                        </div>
                        {menusListHTML}
                    </nav>
                    <nav>
                        <a className="ddfcpos-menu-card ddfcpos-logout-menu" href={getLogoutUrl()} onClick={this.handleLogoutClick}>
                            <LogoutOutlined />
                            {__('Logout', 'multipos-for-fluentcart')}
                        </a>
                    </nav>
                </div>
            </Fragment>
        );
    }
}

const mapStateToProps = state => ({
    orders: state.orders,
});

export default connect(mapStateToProps, null)(Menu);
