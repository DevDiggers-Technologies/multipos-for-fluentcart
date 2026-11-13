import React, { Component } from 'react';
import { connect } from 'react-redux';
import { bindActionCreators } from 'redux';
import { __ } from '@wordpress/i18n';
import { MailOutlined, PhoneOutlined, SearchOutlined, DeleteOutlined, CheckOutlined } from '@ant-design/icons';
import { FixedSizeList as List } from "react-window";
import { getCountriesAndStates } from '../../../actions/countries-and-states';
import { getCustomers, loadSearchCustomers, updateDefaultCustomer, deleteCustomer, saveCustomer } from './../../../actions/customers';
import ManageCustomer from './manageCustomer/manageCustomer.jsx';
import { isInternetConnected } from '../../../services/connectivity';
import { getPosRoute } from '../../../services/routes';
import { notify } from '../../../services/notifications';
import { isMobileViewport, pushRoute } from '../../../utils/navigation';
import { getOutletId, isSameId } from '../../../utils/value';
import { isEnterKey } from '../../../utils/event';
import EmptyState from '../../emptyState/emptyState';

const getCustomerName = customer => {
    const customerName = `${customer && customer.first_name ? customer.first_name : ''} ${customer && customer.last_name ? customer.last_name : ''}`;

    return customerName !== ' ' ? customerName : (customer && customer.username ? customer.username : '');
};

const getManageCustomerPanel = () => document.querySelector('.ddfcpos-manage-customer-wrapper');
const getCustomersResultsLabel = customers => `${customers.length} ${__('Results', 'devdiggers-multipos-for-fluentcart')}`;
const getSingleSearchedCustomerId = customersState => (
    Array.isArray(customersState && customersState.searchedCustomers) && customersState.searchedCustomers.length === 1 ? customersState.searchedCustomers[0].id : null
);
const getCustomersState = customers => customers || {};

const toggleManageCustomerPanel = shouldShow => {
    const customerPanel = getManageCustomerPanel();

    if (!customerPanel || !isMobileViewport()) {
        return;
    }

    customerPanel.style.position = shouldShow ? 'fixed' : 'initial';
    customerPanel.style.display = shouldShow ? 'block' : 'none';
};

class Customers extends Component {
    constructor(props) {
        super(props);

        this.state = {
            search: '',
            editCustomer: {}
        }
    }

    componentDidMount() {
        const outletId = getOutletId(this.props.outlet);
        this.props.getCustomers(outletId);
        this.props.getCountriesAndStates(outletId);
    }

    handleCustomerSearch = event => {
        this.setState({
            search: event.target.value
        });

        const customersState = getCustomersState(this.props.customers);
        this.props.loadSearchCustomers(event.target.value, customersState, customersState.defaultCustomer);
    }

    handleCustomerSearchKeyDown = event => {
        if (isEnterKey(event)) {
            const singleCustomerId = getSingleSearchedCustomerId(getCustomersState(this.props.customers));

            if (singleCustomerId) {
                this.handleSetCustomer(singleCustomerId);
            }
        }
    }

    handleDeleteCustomer = customer => {
        if (isInternetConnected()) {
            if (confirm(__('Are you sure you want to delete this customer?', 'devdiggers-multipos-for-fluentcart'))) {
                this.props.deleteCustomer(customer.id);
            }
        } else {
            notify({
                title: __('Error', 'devdiggers-multipos-for-fluentcart'),
                message: __('Sorry, customer cannot be deleted in offline mode.', 'devdiggers-multipos-for-fluentcart'),
                type: 'danger',
            });
        }
    }

    handleResetEditCustomer = () => {
        this.setState({
            editCustomer: {}
        });

        toggleManageCustomerPanel(false);
    }

    handleSetCustomer = customerId => {
        this.props.updateDefaultCustomer(customerId);
        pushRoute(this.props.history, getPosRoute())
    }

    handleAddNewCustomerMobile = () => {
        toggleManageCustomerPanel(true);
    }

    getRowCustomer = index => (Array.isArray(getCustomersState(this.props.customers).searchedCustomers) ? getCustomersState(this.props.customers).searchedCustomers : [])[index]
    handleCustomerSearchInput = event => this.handleCustomerSearch(event)
    handleCustomerSearchInputKeyDown = event => this.handleCustomerSearchKeyDown(event)
    createDeleteCustomerHandler = customer => () => this.handleDeleteCustomer(customer)
    createSetCustomerHandler = customerId => () => this.handleSetCustomer(customerId)

    render() {
        const customersState = getCustomersState(this.props.customers);
        const customers = Array.isArray(customersState.searchedCustomers) ? customersState.searchedCustomers : [];
        const defaultCustomer = customersState.defaultCustomer || {};
        const isMobile = isMobileViewport();

        const Row = ({ index, style }) => {
            const customer = this.getRowCustomer(index);
            if (!customer) {
                return null;
            }

            const customerName = getCustomerName(customer);
            const isCurrent    = defaultCustomer && isSameId(defaultCustomer.id, customer.id);
            return (
                <div className="ddfcpos-list-row" style={style}>
                    <div className={`ddfcpos-list-details${isCurrent ? ' ddfcpos-list-details-current' : ''}`}>
                        {customer.avatar_url ? <img src={customer.avatar_url} alt={customer.username} /> : null}
                        <div>
                            <h3>{customerName}</h3>
                            <p>
                                <MailOutlined />
                                {customer.email}
                            </p>
                            {customer.phone ?
                                <p>
                                    <PhoneOutlined />
                                    {customer.phone}
                                </p>
                                : null}
                        </div>
                        <div>
                            <span className="ddfcpos-icon-card" onClick={this.createDeleteCustomerHandler(customer)} title={__('Delete Customer', 'devdiggers-multipos-for-fluentcart')}>
                                <DeleteOutlined />
                            </span>
                            {isCurrent ?
                                <span className="ddfcpos-icon-card ddfcpos-customer-current" title={__('Current Customer', 'devdiggers-multipos-for-fluentcart')}>
                                    <CheckOutlined />
                                    {__('Current Customer', 'devdiggers-multipos-for-fluentcart')}
                                </span>
                                :
                                <span className="ddfcpos-icon-card ddfcpos-customer-set" onClick={this.createSetCustomerHandler(customer.id)} title={__('Set Customer', 'devdiggers-multipos-for-fluentcart')}>
                                    <CheckOutlined />
                                    {__('Set Customer', 'devdiggers-multipos-for-fluentcart')}
                                </span>
                            }
                        </div>
                    </div>
                </div>
            );
        }

        const rowHeight = isMobile ? 128 : 80;

        return (
            <div className="ddfcpos-customers-tab-wrapper">
                <div className="ddfcpos-list-wrapper">
                    <div className="ddfcpos-search-wrapper">
                        <h2>{__('Customers', 'devdiggers-multipos-for-fluentcart')}</h2>

                        {isMobile ?
                            <button className="ddfcpos-button" onClick={this.handleAddNewCustomerMobile}>{__('Add New', 'devdiggers-multipos-for-fluentcart')}</button>
                            : null}

                        <div className="ddfcpos-search-input-wrapper">
                            <div className="ddfcpos-search-input">
                                <SearchOutlined />
                                <input type="text" className="ddfcpos-form-control" value={this.state.search} placeholder={__('Search Customer by name, email or phone...', 'devdiggers-multipos-for-fluentcart')} onChange={this.handleCustomerSearchInput} onKeyDown={this.handleCustomerSearchInputKeyDown} autoFocus autoComplete="off" />
                            </div>
                            <span>{getCustomersResultsLabel(customers)}</span>
                        </div>
                    </div>
                    {
                        customers.length > 0 ?
                            <List
                                className="ddfcpos-list"
                                height={800}
                                itemCount={customers.length}
                                itemSize={rowHeight}
                            >
                                {Row}
                            </List>
                            :
                            <EmptyState icon="users" title={__('No Customers Found', 'devdiggers-multipos-for-fluentcart')} hint={__('Try another name, email or phone number, or add a new customer.', 'devdiggers-multipos-for-fluentcart')} />
                    }
                </div>
                <ManageCustomer customer={this.state.editCustomer} {...this.props} handleResetEditCustomer={this.handleResetEditCustomer} />
            </div>
        );
    }
}

const mapStateToProps = state => ({
    customers: state.customers,
    countries_and_states: state.countries_and_states,
});

const mapDispatchToProps = dispatch => bindActionCreators({
    getCustomers,
    getCountriesAndStates,
    loadSearchCustomers,
    updateDefaultCustomer,
    deleteCustomer,
    saveCustomer
}, dispatch);

export default connect(mapStateToProps, mapDispatchToProps)(Customers);
