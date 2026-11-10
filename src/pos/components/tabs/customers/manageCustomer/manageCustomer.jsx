import React, { Component } from 'react';
import Select from '../../../select/select';
import { connect } from 'react-redux';
import { bindActionCreators } from 'redux';
import { __ } from '@wordpress/i18n';
import { saveCustomer } from './../../../../actions/customers';
import { SaveOutlined } from '@ant-design/icons';
import { isInternetConnected } from '../../../../services/connectivity';
import { notify } from '../../../../services/notifications';
import { isMobileViewport } from '../../../../utils/navigation';
import { hasEntries, isSameId } from '../../../../utils/value';

const getEmptyCustomerForm = context => ({
    id: '',
    first_name: '',
    last_name: '',
    email: '',
    phone: '',
    address_1: '',
    address_2: '',
    country: '',
    state: '',
    city: '',
    postcode: ''
});

const getCustomerBilling = customer => customer && customer.billing ? customer.billing : {};

const buildCustomerFormState = (customer, context) => {
    const billing = getCustomerBilling(customer);

    return {
        ...getEmptyCustomerForm(context),
        id: customer.id || '',
        first_name: customer.first_name || '',
        last_name: customer.last_name || '',
        email: customer.email || '',
        phone: customer.phone || '',
        address_1: billing.address_1 ? billing.address_1 : '',
        address_2: billing.address_2 ? billing.address_2 : '',
        country: billing.country || '',
        state: billing.state || '',
        city: billing.city || '',
        postcode: billing.postcode || '',
    };
};

const getCountryOptions = countries => Object.keys(countries && typeof countries === 'object' ? countries : {}).map(countryCode => (
    <option key={countryCode} value={countryCode}>{countries[countryCode]}</option>
));

const getStateOptions = (states, selectedCountry) => {
    if (!hasEntries(states) || !states[selectedCountry]) {
        return [];
    }

    return Object.keys(states[selectedCountry]).map(stateCode => (
        <option key={stateCode} value={stateCode}>{states[selectedCountry][stateCode]}</option>
    ));
};

const getManageCustomerWrapperStyle = () => (isMobileViewport() ? { display: 'none' } : {});
const hasMissingRequiredManageCustomerField = (requiredFields, customerState) => (
    requiredFields.some(requiredField => !customerState[requiredField])
);
const getCustomerFormTitle = customerId => customerId ? __('Edit Customer', 'multipos-for-fluentcart') : __('Add New Customer', 'multipos-for-fluentcart');
const getCountriesAndStates = countriesAndStates => countriesAndStates && typeof countriesAndStates === 'object' ? countriesAndStates : {};
const getSelectedCountry = (customerState, countriesAndStates) => customerState.country ? customerState.country : getCountriesAndStates(countriesAndStates).base_country;
const getSelectedState = customerState => customerState.state ? customerState.state : '';

class ManageCustomer extends Component {
    constructor(props) {
        super(props);

        this.requiredFields = ['email'];

        this.state = getEmptyCustomerForm(this);
    }
    componentDidUpdate = prevProps => {
        const nextProps = this.props;

        if (prevProps.customer === nextProps.customer) {
            return;
        }

        if (hasEntries(nextProps.customer) && !isSameId(nextProps.customer.id, this.state.id)) {
            this.setState(buildCustomerFormState(nextProps.customer, this));
        } else if (!hasEntries(nextProps.customer)) {
            this.setState(getEmptyCustomerForm(this));
        }
    }
    handleInput = (event, field) => {
        const updatedState = {
            [field]: event.target.value
        };

        if (field === 'country') {
            updatedState.state = '';
        }

        this.setState(updatedState);
    }

    handleFormSubmit = event => {
        event.preventDefault();
        if (isInternetConnected()) {
            if (!hasMissingRequiredManageCustomerField(this.requiredFields, this.state)) {
                this.props.saveCustomer(this.state, this);
            } else {
                notify({
                    title: __('Error', 'multipos-for-fluentcart'),
                    message: __('Kindly fill out the mandatory field(s).', 'multipos-for-fluentcart'),
                    type: 'danger',
                });
            }
        } else {
            notify({
                title: __('Error', 'multipos-for-fluentcart'),
                message: __('Sorry, customer cannot be saved in offline mode.', 'multipos-for-fluentcart'),
                type: 'danger',
            });
        }
    }

    handleCustomerInputChange = field => event => this.handleInput(event, field)

    render() {
        const countries_and_states = getCountriesAndStates(this.props.countries_and_states);
        const requiredHtml = <span className="required">*</span>;
        const selectedCountry = getSelectedCountry(this.state, countries_and_states);
        const countriesHTML = getCountryOptions(countries_and_states.countries);
        const statesHTML = getStateOptions(countries_and_states.states, selectedCountry);
        const wrapperStyle = getManageCustomerWrapperStyle();

        return (
            <div className="ddfcpos-manage-customer-wrapper" style={wrapperStyle}>
                <h2>
                    {getCustomerFormTitle(this.state.id)}
                </h2>

                <form onSubmit={this.handleFormSubmit}>
                    <div className="ddfcpos-form-group-column-2">
                        <label>
                            {__('First Name', 'multipos-for-fluentcart')}
                            {this.requiredFields.includes('first_name') && requiredHtml}
                            <input type="text" value={this.state.first_name} onChange={this.handleCustomerInputChange('first_name')} placeholder={__('Enter First Name', 'multipos-for-fluentcart')} />
                        </label>
                        <label>
                            {__('Last Name', 'multipos-for-fluentcart')}
                            {this.requiredFields.includes('last_name') && requiredHtml}
                            <input type="text" value={this.state.last_name} onChange={this.handleCustomerInputChange('last_name')} placeholder={__('Enter Last Name', 'multipos-for-fluentcart')} />
                        </label>
                    </div>
                    <label>
                        {__('Email', 'multipos-for-fluentcart')}
                        {this.requiredFields.includes('email') && requiredHtml}
                        <input type="email" value={this.state.email} onChange={this.handleCustomerInputChange('email')} placeholder={__('Enter Email', 'multipos-for-fluentcart')} disabled={this.state.id} />
                    </label>
                    <label>
                        {__('Phone', 'multipos-for-fluentcart')}
                        {this.requiredFields.includes('phone') && requiredHtml}
                        <input type="text" value={this.state.phone} onChange={this.handleCustomerInputChange('phone')} placeholder={__('Enter Phone Number', 'multipos-for-fluentcart')} />
                    </label>
                    <label>
                        {__('Address Line 1', 'multipos-for-fluentcart')}
                        {this.requiredFields.includes('address_1') && requiredHtml}
                        <input type="text" value={this.state.address_1} onChange={this.handleCustomerInputChange('address_1')} placeholder={__('Enter Address Line 1', 'multipos-for-fluentcart')} />
                    </label>
                    <label>
                        {__('Address Line 2', 'multipos-for-fluentcart')}
                        {this.requiredFields.includes('address_2') && requiredHtml}
                        <input type="text" value={this.state.address_2} onChange={this.handleCustomerInputChange('address_2')} placeholder={__('Enter Address Line 2', 'multipos-for-fluentcart')} />
                    </label>

                    <div className="ddfcpos-form-group-column-2">
                        <label>
                            {__('Country', 'multipos-for-fluentcart')}
                            {this.requiredFields.includes('country') && requiredHtml}
                            <Select value={this.state.country ? this.state.country : countries_and_states.base_country || ''} onChange={this.handleCustomerInputChange('country')}>
                                {countriesHTML}
                            </Select>
                        </label>

                        <label>
                            {__('State', 'multipos-for-fluentcart')}
                            {this.requiredFields.includes('state') && requiredHtml}
                            {statesHTML.length ?
                                <Select value={getSelectedState(this.state)} onChange={this.handleCustomerInputChange('state')}>
                                    {statesHTML}
                                </Select>
                                :
                                <input type="text" value={getSelectedState(this.state)} onChange={this.handleCustomerInputChange('state')} placeholder={__('Enter State', 'multipos-for-fluentcart')} />
                            }
                        </label>
                    </div>

                    <div className="ddfcpos-form-group-column-2">
                        <label>
                            {__('City', 'multipos-for-fluentcart')}
                            {this.requiredFields.includes('city') && requiredHtml}
                            <input type="text" value={this.state.city} onChange={this.handleCustomerInputChange('city')} placeholder={__('Enter City', 'multipos-for-fluentcart')} />
                        </label>
                        <label>
                            {__('Postcode', 'multipos-for-fluentcart')}
                            {this.requiredFields.includes('postcode') && requiredHtml}
                            <input type="text" value={this.state.postcode} onChange={this.handleCustomerInputChange('postcode')} placeholder={__('Enter Postcode', 'multipos-for-fluentcart')} />
                        </label>
                    </div>


                    <button type="submit" className="ddfcpos-button">
                        <SaveOutlined />
                        {__('Save', 'multipos-for-fluentcart')}
                    </button>

                    {this.state.id || isMobileViewport() ?
                        <button type="button" className="ddfcpos-button-secondary" onClick={this.props.handleResetEditCustomer}>{__('Cancel', 'multipos-for-fluentcart')}</button>
                        : null}
                </form>
            </div>
        );
    }
}

const mapDispatchToProps = dispatch => bindActionCreators({ saveCustomer }, dispatch);

export default connect(null, mapDispatchToProps)(ManageCustomer);
