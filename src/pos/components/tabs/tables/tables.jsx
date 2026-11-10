import React, { Component } from 'react';
import { __, _n, sprintf } from '@wordpress/i18n';
import { Link } from 'react-router-dom';
import { bindActionCreators } from 'redux';
import { connect } from 'react-redux';
import OrderDetails from './../orders/orderDetails/orderDetails.jsx';
import { getTables, updateDefaultTable } from './../../../actions/tables';
import { getCart } from './../../../actions/cart';
import { getHoldCarts } from './../../../actions/hold-carts';
import { CheckCircleOutlined, CheckOutlined, ClockCircleOutlined, SearchOutlined, ShoppingCartOutlined, TeamOutlined } from '@ant-design/icons';
import Popup from '../../popup/popup.jsx';
import { getPosRoute } from '../../../services/routes';
import { getRouteType } from '../../../utils/navigation';
import { getHoldOrderRecord } from '../../../utils/orders';
import { isSameId, parseWholeNumber } from '../../../utils/value';

const getTableFilterClass = (currentType, filterType) => (
    currentType === filterType ? 'ddfcpos-active' : ''
);
const getMatchingTableOrder = (holdCarts, table) => (
    (Array.isArray(holdCarts) ? holdCarts : []).find(holdCart => {
        const holdCartRecord = getHoldOrderRecord(holdCart);

        return Boolean(
            holdCartRecord &&
            holdCartRecord.table &&
            holdCartRecord.table.name &&
            isSameId(holdCartRecord.table.slug, table.slug)
        );
    }) || null
);

const matchesTableFilter = (tableType, status) => tableType === 'all' || status === tableType;

const matchesSeatSearch = (table, search) => (
    !search || parseWholeNumber(table.seats) >= parseWholeNumber(search)
);
const getTablesResultsLabel = tables => `${tables.length} ${__('Results', 'multipos-for-fluentcart')}`;
const getTableCountByType = (tables, getTableStatus, type) => tables.filter(table => getTableStatus(table) === type).length;
const getTableDisplayNumber = (table, index) => {
    const name = table && table.name ? String(table.name) : '';
    const numberMatch = name.match(/\d+/);
    const number = numberMatch ? parseWholeNumber(numberMatch[0]) : index + 1;

    return String(number).padStart(2, '0');
};

const getTablePopupStyle = () => (window.screen.width < 768 ? {} : { width: '50%' });
const getFilteredTables = (tables, tableType, search, getTableStatus) => (Array.isArray(tables) ? tables : []).filter(table => {
    const type = getTableStatus(table);

    return matchesTableFilter(tableType, type) && matchesSeatSearch(table, search);
});
const getTablesState = tables => tables || {};

class Tables extends Component {
    constructor(props) {
        super(props);

        this.state = {
            search: '',
            order: null,
            showTableOrderPopup: false,
        }
    }

    componentDidMount = () => {
        this.props.getTables();
        this.props.getCart();
        this.props.getHoldCarts();
    }

    handleToggleTableOrderPopup = () => {
        this.setState(prevState => ({
            showTableOrderPopup: !prevState.showTableOrderPopup,
            order: null,
        }));
    }

    handleTableSearch = event => {
        this.setState({
            search: event.target.value
        });
    }
    handleTableSearchInput = event => this.handleTableSearch(event)

    handleSetTable = tableSlug => {
        this.props.updateDefaultTable(tableSlug);
    }

    handleViewOrder = order => {
        this.setState(prevState => ({
            order: order,
            showTableOrderPopup: !prevState.showTableOrderPopup,
        }));
    }

    getTableStatus = table => table.tableType ? table.tableType : 'vacant'

    getTableOrder = table => getMatchingTableOrder(this.props.holdCarts, table)
    createSetTableHandler = tableSlug => () => this.handleSetTable(tableSlug)
    createViewOrderHandler = order => () => this.handleViewOrder(order)

    renderTableAction = (type, isCurrent, table, order) => {
        if (isCurrent) {
            return (
                <button type="button" className="ddfcpos-table-btn ddfcpos-table-btn-current" disabled>
                    <CheckOutlined />
                    <span>{__('Current Table', 'multipos-for-fluentcart')}</span>
                </button>
            );
        }
        if (type === 'occupied' && order) {
            return (
                <button type="button" className="ddfcpos-table-btn ddfcpos-table-btn-view" onClick={this.createViewOrderHandler(order)}>
                    <ShoppingCartOutlined />
                    <span>{__('View Order', 'multipos-for-fluentcart')}</span>
                </button>
            );
        }
        if (type === 'vacant') {
            return (
                <button type="button" className="ddfcpos-table-btn ddfcpos-table-btn-set" onClick={this.createSetTableHandler(table.slug)}>
                    <CheckOutlined />
                    <span>{__('Set Table', 'multipos-for-fluentcart')}</span>
                </button>
            );
        }
        return <span className="ddfcpos-table-btn ddfcpos-table-btn-placeholder" aria-hidden="true"></span>;
    }

    renderTableCard = ( table, index ) => {
        const defaultTable = getTablesState( this.props.tables ).defaultTable || {};
        const type         = this.getTableStatus( table );
        const order        = this.getTableOrder( table );
        const isCurrent    = defaultTable && isSameId( defaultTable.slug, table.slug );
        const statusIcon   = type === 'occupied' ? <ClockCircleOutlined /> : <CheckCircleOutlined />;
        const tableNumber  = getTableDisplayNumber( table, index );
        const bottomLabel  = type === 'vacant' ? __( 'Vacant', 'multipos-for-fluentcart' ) : __( 'Occupied', 'multipos-for-fluentcart' );
        const wrapperClass = `ddfcpos-table-card-wrapper ddfcpos-table-card-wrapper-${type}${isCurrent ? ' ddfcpos-table-card-wrapper-current' : ''}`;
        const blockClass = `ddfcpos-table-block ddfcpos-${type}${isCurrent ? ' ddfcpos-current' : ''}`;

        return (
            <div className={wrapperClass}>
                <div className="ddfcpos-table-card">
                    <div className={blockClass}>
                        <i className="ddfcpos-chair ddfcpos-chair-top" aria-hidden="true"></i>
                        <i className="ddfcpos-chair ddfcpos-chair-right" aria-hidden="true"></i>
                        <i className="ddfcpos-chair ddfcpos-chair-bottom" aria-hidden="true"></i>
                        <i className="ddfcpos-chair ddfcpos-chair-left" aria-hidden="true"></i>
                        <div className="ddfcpos-table-block-meta">
                            <span className="ddfcpos-table-seats"><TeamOutlined /> {sprintf(
                                /* translators: %s: number of seats at the table */
                                _n( '%s seat', '%s seats', parseInt( table.seats, 10 ) || 0, 'multipos-for-fluentcart' ),
                                table.seats
                            )}</span>
                            <span className={`ddfcpos-table-statusicon ddfcpos-table-statusicon-${type}`}>{statusIcon}</span>
                        </div>
                        <div className="ddfcpos-table-number">
                            <small>{__( 'Table', 'multipos-for-fluentcart' )}</small>
                            <h3>{tableNumber}</h3>
                            <p className={`ddfcpos-table-bottom ddfcpos-table-bottom-${type}`}>{bottomLabel}</p>
                        </div>
                    </div>
                </div>
                {this.renderTableAction(type, isCurrent, table, order)}
            </div>
        );
    }

    render() {
        const tableType = getRouteType(this.props, 'all');

        const search = this.state.search;
        const tableState = getTablesState( this.props.tables );
        const tables = getFilteredTables( tableState.list, tableType, search, this.getTableStatus );
        const allTables = Array.isArray( tableState.list ) ? tableState.list : [];
        const occupiedCount = getTableCountByType( allTables, this.getTableStatus, 'occupied' );
        const vacantCount = getTableCountByType( allTables, this.getTableStatus, 'vacant' );
        const hasCurrentTable = Boolean( tableState.defaultTable && tableState.defaultTable.name );
        const currentTable = hasCurrentTable ? tableState.defaultTable.name : __( 'Not Selected', 'multipos-for-fluentcart' );

        const tablesHTML = tables.map((table, index) => this.renderTableCard(table, index));

        let tableOrderPopupHTML = null;

        if (this.state.showTableOrderPopup) {
            const popupContent = (
                <OrderDetails order={this.state.order} {...this.props} orderType="hold" tableView={true} handleToggleTableOrderPopup={this.handleToggleTableOrderPopup} />
            );

            const popupProps = {
                popupStyle: getTablePopupStyle(),
                handleOverlay: this.handleToggleTableOrderPopup,
                popupContent: popupContent,
                hideSuccessButton: true,
                hideCancelButton: true,
                handleCancel: this.handleToggleTableOrderPopup,
            };

            tableOrderPopupHTML = <Popup {...popupProps} />
        }

        return (
            <div className="ddfcpos-tables-tab-wrapper">
                <div className="ddfcpos-tables-header">
                    <div>
                        <h2>{__('Floor Plan', 'multipos-for-fluentcart')}</h2>
                        {/* translators: %s: number of occupied tables */}
                        <p>{sprintf(__('Main Dining Room • %s Active Tables', 'multipos-for-fluentcart'), occupiedCount)}</p>
                    </div>
                    <div className="ddfcpos-tables-summary">
                        {hasCurrentTable ? (
                            <span className="ddfcpos-current-table-chip" title={__( 'Current Table', 'multipos-for-fluentcart' )}>
                                <CheckOutlined />
                                {/* translators: %s: name of the table the cart is assigned to */}
                                {sprintf( __( 'Current: %s', 'multipos-for-fluentcart' ), currentTable )}
                            </span>
                        ) : (
                            <span className="ddfcpos-current-table-chip is-empty">{__( 'No table selected', 'multipos-for-fluentcart' )}</span>
                        )}
                    </div>
                </div>
                <div className="ddfcpos-table-toolbar">
                    <div className="ddfcpos-search-wrapper">
                        <div className="ddfcpos-search-input-wrapper">
                            <div className="ddfcpos-search-input">
                                <SearchOutlined />
                                <input type="text" className="ddfcpos-form-control" value={this.state.search} placeholder={__('Search by seats (e.g. 4, 6, 8)...', 'multipos-for-fluentcart')} onChange={this.handleTableSearchInput} autoComplete="off" />
                            </div>
                            <span>{getTablesResultsLabel(tablesHTML)}</span>
                        </div>
                    </div>
                    <div className="ddfcpos-tab-changer">
                        {/* translators: %s: number of tables */}
                        <Link className={getTableFilterClass(tableType, 'all')} to={getPosRoute('/tables/all')}>{sprintf(__('All Tables (%s)', 'multipos-for-fluentcart'), allTables.length)}</Link>
                        {/* translators: %s: number of occupied tables */}
                        <Link className={getTableFilterClass(tableType, 'occupied')} to={getPosRoute('/tables/occupied')}>{sprintf(__('Occupied (%s)', 'multipos-for-fluentcart'), occupiedCount)}</Link>
                        {/* translators: %s: number of vacant tables */}
                        <Link className={getTableFilterClass(tableType, 'vacant')} to={getPosRoute('/tables/vacant')}>{sprintf(__('Vacant (%s)', 'multipos-for-fluentcart'), vacantCount)}</Link>
                    </div>
                </div>

                <div className="ddfcpos-table-wrapper">
                    {tablesHTML}
                </div>
                {tableOrderPopupHTML}
            </div>
        );
    }
}

const mapStateToProps = state => ({
    tables: state.tables,
    cart: state.cart,
    holdCarts: state.holdCarts,
});

const mapDispatchToProps = dispatch => bindActionCreators({ getTables, updateDefaultTable, getHoldCarts, getCart }, dispatch);

export default connect(mapStateToProps, mapDispatchToProps)(Tables);
