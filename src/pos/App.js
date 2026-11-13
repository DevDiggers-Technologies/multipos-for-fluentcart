import React, { Component } from 'react';
import { connect } from 'react-redux';
import { __ } from '@wordpress/i18n';
import ReactNotification from 'react-notifications-component';
import { bindActionCreators } from 'redux';
import 'react-notifications-component/dist/theme.css';
import Menu from './components/menu/menu.jsx';
import Tabs from './components/tabs/tabs.jsx';
import { setCurrentOutlet } from './actions/outlet';
import { store } from 'react-notifications-component';
import { getSettings } from './actions/settings';
import { getKeyboardShortcutRoute } from './services/keyboard-shortcuts';
import { hasEntries } from './utils/value';

import './pos.less';

const hasOutletSelection = outlet => hasEntries(outlet);

class App extends Component {

    constructor(props) {
        super(props);
        this.state = {
            outletFetched: false,
        };
        this.isMountedFlag = false;
    }

    componentDidMount() {
        this.isMountedFlag = true;
        this.props.setCurrentOutlet().then(() => {
            if (this.isMountedFlag) {
                this.setState({
                    outletFetched: true,
                });
            }
        });

        this.props.getSettings();
        document.addEventListener('keydown', this.handleKeyboardShortcut);
    }

    componentWillUnmount() {
        this.isMountedFlag = false;
        document.removeEventListener('keydown', this.handleKeyboardShortcut);
    }

    handleKeyboardShortcut = event => {
        if (this.props.settings && this.props.settings.keyboard_shortcuts === 'disabled') {
            return;
        }

        const route = getKeyboardShortcutRoute(event, this.props.settings);

        if (route && this.props.history && this.props.history.push) {
            event.preventDefault();
            this.props.history.push(route);
        }
    }

    renderLoadingView = () => (
        <div className="ddfcpos-container">
            <div className="ddfcpos-progress-bar"></div>
        </div>
    )

    renderPosView = () => (
        <div className="ddfcpos-container">
            <ReactNotification />
            <div className="ddfcpos-progress-bar"></div>
            <Menu {...this.props} />
            <Tabs {...this.props} notification={store}></Tabs>
        </div>
    )

    renderNoOutletView = () => (
        <div className="ddfcpos-container">
            <div className="ddfcpos-no-outlet-notice">
                <p>{__('You do not have any assigned outlet!!', 'devdiggers-multipos-for-fluentcart')}</p>
            </div>
        </div>
    )

    render() {
        if (!this.state.outletFetched) {
            return this.renderLoadingView();
        }

        if (hasOutletSelection(this.props.outlet)) {
            return this.renderPosView();
        }

        return this.renderNoOutletView();
    }
}

const mapStateToProps = state => ({
    outlet: state.outlet,
    settings: state.settings,
});

const mapDispatchToProps = dispatch => bindActionCreators({
    setCurrentOutlet,
    getSettings,
}, dispatch);

export default connect(mapStateToProps, mapDispatchToProps)(App);
