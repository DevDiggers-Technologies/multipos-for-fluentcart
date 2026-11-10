import React, { Component } from 'react';
import { connect } from 'react-redux';
import { bindActionCreators } from 'redux';
import { __ } from '@wordpress/i18n';
import { saveCashier } from './../../../../actions/settings';
import { SaveOutlined } from '@ant-design/icons';
import { isInternetConnected } from '../../../../services/connectivity';
import { notify } from '../../../../services/notifications';
import { getCurrentPosUser } from '../../../../services/runtime';

const hasValidPasswordChange = state => {
    return state.new_password && state.confirm_password && state.new_password === state.confirm_password;
};

const showAccountSettingsOfflineError = () => notify({
    title: __('Error', 'multipos-for-fluentcart'),
    message: __('You need to be online to update account details.', 'multipos-for-fluentcart'),
    type: 'danger',
});

const showAccountSettingsPasswordError = () => notify({
    title: __('Error', 'multipos-for-fluentcart'),
    message: __('Check password fields and try again.', 'multipos-for-fluentcart'),
    type: 'danger',
});
const getAccountSettingsState = () => ({
    id: getCurrentPosUser().ID || '',
    first_name: getCurrentPosUser().first_name || '',
    last_name: getCurrentPosUser().last_name || '',
    email: getCurrentPosUser().user_email || '',
    current_password: '',
    new_password: '',
    confirm_password: '',
});
const getInputStateChange = (field, value) => ({
    [field]: value,
});

class AccountSettings extends Component {
    constructor(props) {
        super(props);

        this.state = getAccountSettingsState();
    }

    handleInput = (event, field) => {
        this.setState(getInputStateChange(field, event.target.value));
    }

    handleAccountInput = field => event => this.handleInput(event, field)

    handleFormSubmit = event => {
        event.preventDefault();

        if (!isInternetConnected()) {
            showAccountSettingsOfflineError();
            return;
        }

        if (this.state.current_password && !hasValidPasswordChange(this.state)) {
            showAccountSettingsPasswordError();
            return;
        }

        this.props.saveCashier(this.state, this);
    }

    render() {
        return (
            <div className="ddfcpos-account-settings-wrapper ddfcpos-settings-panel">
                <h2>{__('Account Settings', 'multipos-for-fluentcart')}</h2>
                <p className="ddfcpos-settings-copy">{__('Update cashier details for this account.', 'multipos-for-fluentcart')}</p>

                <form className="ddfcpos-settings-form" onSubmit={this.handleFormSubmit}>
                    <div className="ddfcpos-form-group-column-2">
                        <label className="ddfcpos-settings-field">
                            <span>{__('First Name', 'multipos-for-fluentcart')}</span>
                            <input type="text" value={this.state.first_name} onChange={this.handleAccountInput('first_name')} placeholder={__('First name', 'multipos-for-fluentcart')} />
                        </label>
                        <label className="ddfcpos-settings-field">
                            <span>{__('Last Name', 'multipos-for-fluentcart')}</span>
                            <input type="text" value={this.state.last_name} onChange={this.handleAccountInput('last_name')} placeholder={__('Last name', 'multipos-for-fluentcart')} />
                        </label>
                    </div>
                    <label className="ddfcpos-settings-field">
                        <span>{__('Email', 'multipos-for-fluentcart')}</span>
                        <input type="email" value={this.state.email} onChange={this.handleAccountInput('email')} placeholder={__('Email address', 'multipos-for-fluentcart')} disabled />
                        <small>{__('Email address cannot be changed here.', 'multipos-for-fluentcart')}</small>
                    </label>
                    <div className="ddfcpos-settings-subsection">
                        <h3>{__('Password', 'multipos-for-fluentcart')}</h3>
                        <p className="ddfcpos-settings-copy">{__('Leave password fields empty if you do not want to change password.', 'multipos-for-fluentcart')}</p>
                        <label className="ddfcpos-settings-field">
                            <span>{__('Current Password', 'multipos-for-fluentcart')}</span>
                            <input type="password" value={this.state.current_password} onChange={this.handleAccountInput('current_password')} placeholder={__('Current password', 'multipos-for-fluentcart')} autoComplete="off" />
                        </label>

                        <label className="ddfcpos-settings-field">
                            <span>{__('New Password', 'multipos-for-fluentcart')}</span>
                            <input type="password" value={this.state.new_password} onChange={this.handleAccountInput('new_password')} placeholder={__('New password', 'multipos-for-fluentcart')} autoComplete="off" />
                        </label>

                        <label className="ddfcpos-settings-field">
                            <span>{__('Confirm New Password', 'multipos-for-fluentcart')}</span>
                            <input type="password" value={this.state.confirm_password} onChange={this.handleAccountInput('confirm_password')} placeholder={__('Confirm new password', 'multipos-for-fluentcart')} autoComplete="off" />
                        </label>
                    </div>

                    <button type="submit" className="ddfcpos-button ddfcpos-settings-save-button">
                        <SaveOutlined />
                        {__('Save Changes', 'multipos-for-fluentcart')}
                    </button>
                </form>
            </div>
        );
    }
}

const mapDispatchToProps = dispatch => bindActionCreators({ saveCashier }, dispatch);

export default connect(null, mapDispatchToProps)(AccountSettings);
