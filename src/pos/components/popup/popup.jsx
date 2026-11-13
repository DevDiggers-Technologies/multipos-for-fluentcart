import React, { Component, Fragment } from 'react';
import { __ } from '@wordpress/i18n';
import { PlusOutlined } from '@ant-design/icons';
import { hasEntries } from '../../utils/value';

const isEscapeKey = event => event.key === 'Escape' || event.which === 27;
const hasPopupStyle = popupStyle => hasEntries( popupStyle );
const getPopupActionsStyle = isSingleButton => ( isSingleButton ? { gridTemplateColumns: '1fr' } : {} );
const getPopupClassName = additionalPopupClass => (
    additionalPopupClass ? `ddfcpos-popup ${additionalPopupClass}` : 'ddfcpos-popup'
);

class Popup extends Component {
    componentDidMount = () => {
        document.addEventListener('keydown', this.handleKeyDown, false);
    }

    componentWillUnmount = () => {
        document.removeEventListener('keydown', this.handleKeyDown, false);
    }

    handleKeyDown = event => {
        if ( isEscapeKey( event ) ) {
            this.props.handleCancel();
        }
    }

    handleSuccessClick = event => {
        const successArgs = this.props.handleSuccessArgs ? this.props.handleSuccessArgs : [];
        this.props.handleSuccess( event, ...successArgs );
    }

    render() {
        const style = getPopupActionsStyle( this.props.singleButton );
        const popupClass = getPopupClassName( this.props.additionalPopupClass );

        return(
            <Fragment>
                <div className="ddfcpos-popup-overlay" onClick={this.props.handleOverlay}></div>
                <div className={popupClass} style={hasPopupStyle( this.props.popupStyle ) ? this.props.popupStyle : {}}>
                    <div className="ddfcpos-popup-content">
                        {this.props.popupContent}

                        {this.props.hideActions ?
                            null
                            :
                            <div className="ddfcpos-actions-wrapper" style={style}>
                                {
                                    ! this.props.hideSuccessButton &&
                                    <button disabled={! this.props.notDisabled} onClick={this.handleSuccessClick}>
                                        {this.props.successButtonText ?
                                            this.props.successButtonText
                                        :
                                            <Fragment>
                                                <PlusOutlined />
                                                { __( 'Add', 'devdiggers-multipos-for-fluentcart' ) }
                                            </Fragment>
                                        }
                                    </button>
                                }
                                {
                                    ! this.props.hideCancelButton &&
                                    <button className="ddfcpos-button-secondary" onClick={this.props.handleCancel}>
                                        {this.props.cancelButtonText ?
                                            this.props.cancelButtonText
                                        :
                                            __( 'Cancel', 'devdiggers-multipos-for-fluentcart' )
                                        }
                                    </button>
                                }
                            </div>
                        }
                    </div>
                </div>
            </Fragment>
        );
    }
}

export default Popup;
