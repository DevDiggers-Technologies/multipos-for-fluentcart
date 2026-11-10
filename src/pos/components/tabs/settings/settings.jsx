import React, { Component } from 'react';
import { __ } from '@wordpress/i18n';
import AccountSettings from './accountSettings/accountSettings.jsx';
import OutletSettings from './outletSettings/outletSettings.jsx';
import KeyboardShortcuts from './keyboardShortcuts/keyboardShortcuts.jsx';

const getSettingsSections = props => ([
    { key: 'outlet', label: __('Outlet', 'multipos-for-fluentcart'), component: OutletSettings, props },
    { key: 'account', label: __('Account', 'multipos-for-fluentcart'), component: AccountSettings, props },
    { key: 'shortcuts', label: __('Shortcuts', 'multipos-for-fluentcart'), component: KeyboardShortcuts, props },
]);

class Settings extends Component {
    constructor(props) {
        super(props);
        this.state = {
            activeSection: 'outlet',
        };
    }

    setActiveSection = activeSection => this.setState({ activeSection })

    createSectionChangeHandler = sectionKey => event => {
        event.preventDefault();
        this.setActiveSection(sectionKey);
    }

    renderSectionTab = section => (
        <a
            href={`#${section.key}`}
            key={section.key}
            className={this.state.activeSection === section.key ? 'ddfcpos-active' : ''}
            onClick={this.createSectionChangeHandler(section.key)}
        >
            {section.label}
        </a>
    )

    renderActiveSection = sections => {
        const activeSection = sections.find(section => section.key === this.state.activeSection) || sections[0];
        const SectionComponent = activeSection.component;

        return <SectionComponent {...activeSection.props} />;
    }

    render() {
        const sections = getSettingsSections(this.props);

        return (
            <div className="ddfcpos-settings-tab-wrapper">
                <div className="ddfcpos-settings-shell">
                    <header className="ddfcpos-settings-header">
                        <h1>{__('Settings', 'multipos-for-fluentcart')}</h1>
                        <p className="ddfcpos-settings-copy">{__('Review POS preferences, account details, and shortcuts in one place.', 'multipos-for-fluentcart')}</p>
                        <nav className="ddfcpos-tab-changer ddfcpos-segmented-tab-changer" aria-label={__('Settings Sections', 'multipos-for-fluentcart')}>
                            {sections.map(this.renderSectionTab)}
                        </nav>
                    </header>
                    <div className="ddfcpos-settings-content">
                        {this.renderActiveSection(sections)}
                    </div>
                </div>
            </div>
        );
    }
}

export default Settings;
