import React, { Component } from 'react';
import Select from '../../../select/select';
import { connect } from 'react-redux';
import { bindActionCreators } from 'redux';
import { __ } from '@wordpress/i18n';
import { saveSettings } from '../../../../actions/settings';
import {
    POS_KEYBOARD_SHORTCUTS,
    KEYBOARD_SHORTCUT_OPTIONS,
    getDefaultKeyboardShortcutMap,
    getKeyboardShortcutDisplay,
    getKeyboardShortcutMap,
} from '../../../../services/keyboard-shortcuts';

const getKeyboardShortcutsValue = settings => settings && settings.keyboard_shortcuts === 'disabled' ? 'disabled' : 'enabled';

class KeyboardShortcuts extends Component {
    handleKeyboardShortcutsChange = event => {
        this.props.saveSettings({
            ...this.props.settings,
            keyboard_shortcuts: event.target.value,
        });
    }

    handleShortcutChange = actionId => event => {
        const shortcut = event.target.value;
        const shortcutMap = getKeyboardShortcutMap(this.props.settings);
        const nextShortcutMap = Object.keys(shortcutMap).reduce((map, id) => ({
            ...map,
            [id]: shortcut && shortcutMap[id] === shortcut ? '' : shortcutMap[id],
        }), {});

        nextShortcutMap[actionId] = shortcut;

        this.props.saveSettings({
            ...this.props.settings,
            keyboard_shortcuts_map: nextShortcutMap,
        });
    }

    resetShortcuts = () => {
        this.props.saveSettings({
            ...this.props.settings,
            keyboard_shortcuts_map: getDefaultKeyboardShortcutMap(),
        });
    }

    renderShortcut = shortcut => (
        <div className="ddfcpos-shortcut-row" key={shortcut.id}>
            <span className="ddfcpos-shortcut-label">{shortcut.label}</span>
            <Select
                aria-label={shortcut.label}
                value={getKeyboardShortcutMap( this.props.settings )[ shortcut.id ]}
                onChange={this.handleShortcutChange( shortcut.id )}
            >
                <option value="">{__( 'Unassigned', 'multipos-for-fluentcart' )}</option>
                {KEYBOARD_SHORTCUT_OPTIONS.map( option => (
                    <option value={option} key={option}>{getKeyboardShortcutDisplay( option )}</option>
                ) )}
            </Select>
        </div>
    )

    render() {
        return (
            <div className="ddfcpos-account-settings-wrapper ddfcpos-settings-panel">
                <div className="ddfcpos-settings-section-heading">
                    <h2>{__('Keyboard Shortcuts', 'multipos-for-fluentcart')}</h2>
                    <button type="button" className="ddfcpos-button ddfcpos-shortcuts-reset" onClick={this.resetShortcuts}>
                        {__('Reset Defaults', 'multipos-for-fluentcart')}
                    </button>
                </div>
                <p className="ddfcpos-settings-copy">{__('Choose shortcuts that feel natural for your keyboard. Each shortcut can be used only once.', 'multipos-for-fluentcart')}</p>

                <form className="ddfcpos-settings-form">
                    <div className="ddfcpos-settings-field">
                        <span>{__( 'Keyboard Shortcuts', 'multipos-for-fluentcart' )}</span>
                        <Select value={getKeyboardShortcutsValue( this.props.settings )} onChange={this.handleKeyboardShortcutsChange}>
                            <option value="enabled">{__( 'Enabled', 'multipos-for-fluentcart' )}</option>
                            <option value="disabled">{__( 'Disabled', 'multipos-for-fluentcart' )}</option>
                        </Select>
                        <small>{__( 'Turn shortcuts on or off for this POS screen.', 'multipos-for-fluentcart' )}</small>
                    </div>

                    <div className="ddfcpos-shortcuts-list">
                        {POS_KEYBOARD_SHORTCUTS.map(this.renderShortcut)}
                    </div>
                </form>
            </div>
        );
    }
}

const mapStateToProps = state => ({
    settings: state.settings,
});

const mapDispatchToProps = dispatch => bindActionCreators({ saveSettings }, dispatch);

export default connect(mapStateToProps, mapDispatchToProps)(KeyboardShortcuts);
