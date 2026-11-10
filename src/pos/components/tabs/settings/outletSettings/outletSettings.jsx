import React, { Component } from 'react';
import Select from '../../../select/select';
import { connect } from 'react-redux';
import { bindActionCreators } from 'redux';
import { __ } from '@wordpress/i18n';
import { RedoOutlined } from '@ant-design/icons';
import { getSettings, saveSettings } from '../../../../actions/settings';
import { setCurrentOutlet } from '../../../../actions/outlet';
import { isInternetConnected } from '../../../../services/connectivity';
import { clearPosCache } from '../../../../services/cache';
import { notify } from '../../../../services/notifications';
import { getPosRoute } from '../../../../services/routes';
import { pushRoute } from '../../../../utils/navigation';
import { getOutletId } from '../../../../utils/value';

const notifyOutletResetting = () => notify({
    title: __('Resetting', 'multipos-for-fluentcart'),
    message: __('Clearing saved outlet data for this browser.', 'multipos-for-fluentcart'),
    type: 'success',
});
const notifyOutletOfflineError = message => notify({
    title: __('Error', 'multipos-for-fluentcart'),
    message,
    type: 'danger',
});
const buildUpdatedOutletSettings = (settings, settingType, value) => ({
    ...settings,
    [settingType]: value,
});
const createSettingsChangeHandler = component => settingType => event => component.handleChangeSettings(event, settingType);
const getSettingValue = (settings, settingKey) => (settings && settings[settingKey]) || '';

class OutletSettings extends Component {
    componentDidMount = () => {
        this.props.getSettings();
    }

    handleChangeSettings = (event, settingType) => {
        const updatedSettings = buildUpdatedOutletSettings(this.props.settings, settingType, event.target.value);

        this.props.saveSettings(updatedSettings);
    }

    handleResetOutlet = () => {
        if (isInternetConnected()) {
            notifyOutletResetting();

            this.props.setCurrentOutlet(getOutletId(this.props.outlet));

            clearPosCache('currentOutletData').then(() => {
                pushRoute(this.props.history, getPosRoute());
            });
        } else {
            notifyOutletOfflineError(__('You need to be online to reset outlet data.', 'multipos-for-fluentcart'));
        }
    }

    handleDisplayCategoryChange = createSettingsChangeHandler(this)('display_category')
    handleSoundsChange = createSettingsChangeHandler(this)('sounds')
    handlePrinterWidthChange = createSettingsChangeHandler(this)('printer_width')
    handlePrinterHeightChange = createSettingsChangeHandler(this)('printer_height')
    handlePrinterMarginChange = createSettingsChangeHandler(this)('printer_margin')

    render() {
        return (
            <div className="ddfcpos-account-settings-wrapper ddfcpos-settings-panel">
                <h2>{__('Outlet Settings', 'multipos-for-fluentcart')}</h2>
                <p className="ddfcpos-settings-copy">{__('Manage how this outlet looks and prints in POS.', 'multipos-for-fluentcart')}</p>

                <form className="ddfcpos-settings-form">
                    <div className="ddfcpos-settings-field">
                        <span>{__('Show Category Cards', 'multipos-for-fluentcart')}</span>
                        <Select value={getSettingValue(this.props.settings, 'display_category')} onChange={this.handleDisplayCategoryChange}>
                            <option value="enabled">{__('Enabled', 'multipos-for-fluentcart')}</option>
                            <option value="disabled">{__('Disabled', 'multipos-for-fluentcart')}</option>
                        </Select>
                        <small>{__('Show product categories on home screen for faster browsing.', 'multipos-for-fluentcart')}</small>
                    </div>

                    <div className="ddfcpos-settings-field">
                        <span>{__('Play POS Sounds', 'multipos-for-fluentcart')}</span>
                        <Select value={getSettingValue(this.props.settings, 'sounds')} onChange={this.handleSoundsChange}>
                            <option value="enabled">{__('Enabled', 'multipos-for-fluentcart')}</option>
                            <option value="disabled">{__('Disabled', 'multipos-for-fluentcart')}</option>
                        </Select>
                        <small>{__('Use sound alerts for actions and updates in POS.', 'multipos-for-fluentcart')}</small>
                    </div>

                    <div className="ddfcpos-settings-subsection">
                        <h3>{__('Receipt Size', 'multipos-for-fluentcart')}</h3>
                        <p className="ddfcpos-settings-copy">{__('These values control default paper size for printed receipts.', 'multipos-for-fluentcart')}</p>
                        <div className="ddfcpos-settings-measurements">
                            <label className="ddfcpos-settings-field">
                                <span>{__('Receipt Width (mm)', 'multipos-for-fluentcart')}</span>
                                <input type="text" value={getSettingValue(this.props.settings, 'printer_width')} onChange={this.handlePrinterWidthChange} />
                            </label>

                            <label className="ddfcpos-settings-field">
                                <span>{__('Receipt Height (mm)', 'multipos-for-fluentcart')}</span>
                                <input type="text" value={getSettingValue(this.props.settings, 'printer_height')} onChange={this.handlePrinterHeightChange} />
                            </label>

                            <label className="ddfcpos-settings-field">
                                <span>{__('Receipt Margin (mm)', 'multipos-for-fluentcart')}</span>
                                <input type="text" value={getSettingValue(this.props.settings, 'printer_margin')} onChange={this.handlePrinterMarginChange} />
                            </label>
                        </div>
                    </div>

                    <div className="ddfcpos-settings-actions">
                        <div className="ddfcpos-settings-action-row">
                            <div>
                                <strong>{__('Reset Outlet Data', 'multipos-for-fluentcart')}</strong>
                                <small>{__('Clear saved outlet data in this browser and reload fresh data.', 'multipos-for-fluentcart')}</small>
                            </div>
                            <button type="button" className="ddfcpos-button-secondary" onClick={this.handleResetOutlet}>
                                <RedoOutlined />
                                {__('Reset', 'multipos-for-fluentcart')}
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        );
    }
}

const mapStateToProps = state => ({
    settings: state.settings,
    outlet: state.outlet,
});

const mapDispatchToProps = dispatch => bindActionCreators({ getSettings, saveSettings, setCurrentOutlet }, dispatch);

export default connect(mapStateToProps, mapDispatchToProps)(OutletSettings);
