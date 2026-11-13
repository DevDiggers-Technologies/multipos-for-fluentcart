import React, { Component, Fragment } from 'react';
import { createPortal } from 'react-dom';
import { __ } from '@wordpress/i18n';

// Lists longer than this get a search box (countries, states).
const SEARCH_THRESHOLD = 10;

const getText = node => React.Children.toArray( node ).map( child => ( 'object' === typeof child ? getText( child.props && child.props.children ) : String( child ) ) ).join( '' );

// Reads <option> children (arrays and fragments included) into plain { value, label, disabled } rows.
const getOptions = children => React.Children.toArray( children ).reduce( ( options, child ) => {
    if ( ! child || ! child.props ) {
        return options;
    }

    if ( 'option' !== child.type ) {
        return options.concat( getOptions( child.props.children ) );
    }

    const label = getText( child.props.children );

    return options.concat( {
        value: String( undefined === child.props.value ? label : child.props.value ),
        label,
        disabled: !! child.props.disabled,
    } );
}, [] );

// Drop-in replacement for a single <select>: same value/onChange/<option> API, styled list instead of the browser menu.
// onChange receives an event-like object with target.value, target.options and target.selectedIndex, so existing handlers keep working.
class Select extends Component {
    state = { open: false, active: -1, search: '', rect: null }

    buttonRef = React.createRef()
    listRef = React.createRef()

    componentWillUnmount() {
        this.removeListeners();
    }

    componentDidUpdate( prevProps, prevState ) {
        if ( this.state.open && prevState.active !== this.state.active && this.listRef.current ) {
            const activeItem = this.listRef.current.querySelector( '.is-active' );

            if ( activeItem ) {
                activeItem.scrollIntoView( { block: 'nearest' } );
            }
        }
    }

    addListeners() {
        document.addEventListener( 'mousedown', this.handleOutside, true );
        document.addEventListener( 'touchstart', this.handleOutside, true );
        window.addEventListener( 'resize', this.close );
        window.addEventListener( 'scroll', this.handleScroll, true );
    }

    removeListeners() {
        document.removeEventListener( 'mousedown', this.handleOutside, true );
        document.removeEventListener( 'touchstart', this.handleOutside, true );
        window.removeEventListener( 'resize', this.close );
        window.removeEventListener( 'scroll', this.handleScroll, true );
    }

    getSelectedIndex = options => options.findIndex( option => option.value === String( null === this.props.value || undefined === this.props.value ? '' : this.props.value ) )

    open = () => {
        if ( this.props.disabled || ! this.buttonRef.current ) {
            return;
        }

        const options = getOptions( this.props.children );

        this.setState( { open: true, search: '', active: Math.max( 0, this.getSelectedIndex( options ) ), rect: this.buttonRef.current.getBoundingClientRect() } );
        this.addListeners();
    }

    close = () => {
        if ( this.state.open ) {
            this.setState( { open: false } );
            this.removeListeners();
        }
    }

    handleOutside = event => {
        const inside = ( this.listRef.current && this.listRef.current.contains( event.target ) ) || ( this.buttonRef.current && this.buttonRef.current.contains( event.target ) );

        if ( ! inside ) {
            this.close();
        }
    }

    handleScroll = event => {
        if ( ! this.listRef.current || ! this.listRef.current.contains( event.target ) ) {
            this.close();
        }
    }

    choose = option => {
        this.close();

        if ( this.buttonRef.current ) {
            this.buttonRef.current.focus();
        }

        if ( ! option || option.disabled || ! this.props.onChange ) {
            return;
        }

        const options = getOptions( this.props.children ).map( item => ( { ...item, text: item.label } ) );
        const target = { value: option.value, name: this.props.name, options, selectedIndex: options.findIndex( item => item.value === option.value ) };

        this.props.onChange( { target, currentTarget: target, preventDefault() {}, stopPropagation() {} } );
    }

    getVisibleOptions = () => {
        const options = getOptions( this.props.children );
        const search = this.state.search.trim().toLowerCase();

        return search ? options.filter( option => option.label.toLowerCase().includes( search ) ) : options;
    }

    moveActive = step => {
        const options = this.getVisibleOptions();

        if ( ! options.length ) {
            return;
        }

        let next = this.state.active;

        for ( let i = 0; i < options.length; i++ ) {
            next = ( next + step + options.length ) % options.length;

            if ( ! options[ next ].disabled ) {
                break;
            }
        }

        this.setState( { active: next } );
    }

    // Keys stop here so POS shortcuts, the pay screen and popup Escape do not react while the list is open.
    handleKeyDown = event => {
        const { open } = this.state;

        if ( ! open ) {
            if ( [ 'ArrowDown', 'ArrowUp', 'Enter', ' ' ].includes( event.key ) ) {
                event.preventDefault();
                event.stopPropagation();
                this.open();
            }

            return;
        }

        const handled = {
            ArrowDown: () => this.moveActive( 1 ),
            ArrowUp: () => this.moveActive( -1 ),
            Home: () => this.setState( { active: 0 } ),
            End: () => this.setState( { active: this.getVisibleOptions().length - 1 } ),
            Enter: () => this.choose( this.getVisibleOptions()[ this.state.active ] ),
            Escape: () => this.close(),
            Tab: () => this.close(),
        }[ event.key ];

        event.stopPropagation();

        if ( handled ) {
            if ( 'Tab' !== event.key ) {
                event.preventDefault();
            }

            handled();
        } else if ( 1 === event.key.length && event.target === this.buttonRef.current ) {
            // Type-ahead on short lists without a search box.
            const key = event.key.toLowerCase();
            const index = this.getVisibleOptions().findIndex( option => ! option.disabled && option.label.toLowerCase().startsWith( key ) );

            if ( -1 !== index ) {
                this.setState( { active: index } );
            }
        }
    }

    handleSearch = event => this.setState( { search: event.target.value, active: 0 } )

    renderMenu( allOptions ) {
        const { rect, active, search } = this.state;
        const options = this.getVisibleOptions();
        const selectedIndex = this.getSelectedIndex( allOptions );
        // Matches the closed field: an unknown value falls back to the first option.
        const selectedValue = allOptions.length ? allOptions[ Math.max( 0, selectedIndex ) ].value : '';
        const spaceBelow = window.innerHeight - rect.bottom;
        const openUp = spaceBelow < 260 && rect.top > spaceBelow;
        const style = {
            left: rect.left,
            width: Math.max( rect.width, 180 ),
            maxHeight: Math.min( 320, ( openUp ? rect.top : spaceBelow ) - 16 ),
            [ openUp ? 'bottom' : 'top' ]: openUp ? window.innerHeight - rect.top + 6 : rect.bottom + 6,
        };

        return createPortal(
            <div className={ `ddfcpos-select-menu${ openUp ? ' is-up' : '' }` } style={ style } ref={ this.listRef } onKeyDown={ this.handleKeyDown }>
                { allOptions.length > SEARCH_THRESHOLD ? (
                    <input type="text" className="ddfcpos-select-search" value={ search } onChange={ this.handleSearch } placeholder={ __( 'Search...', 'devdiggers-multipos-for-fluentcart' ) } autoFocus autoComplete="off" />
                ) : null }
                <ul role="listbox">
                    { options.length ? options.map( ( option, index ) => (
                        <li
                            key={ `${ option.value }-${ index }` }
                            role="option"
                            aria-selected={ option.value === selectedValue }
                            aria-disabled={ option.disabled }
                            className={ [ index === active ? 'is-active' : '', option.value === selectedValue ? 'is-selected' : '', option.disabled ? 'is-disabled' : '' ].join( ' ' ).trim() }
                            onMouseEnter={ () => this.setState( { active: index } ) }
                            onClick={ () => this.choose( option ) }
                        >
                            <span>{ option.label }</span>
                            { option.value === selectedValue ? (
                                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5" /></svg>
                            ) : null }
                        </li>
                    ) ) : <li className="ddfcpos-select-empty">{ __( 'No matches', 'devdiggers-multipos-for-fluentcart' ) }</li> }
                </ul>
            </div>,
            document.body
        );
    }

    render() {
        const options = getOptions( this.props.children );
        // Like a native select, an unknown value shows the first option.
        const selected = options[ Math.max( 0, this.getSelectedIndex( options ) ) ];

        return (
            <Fragment>
                <button
                    type="button"
                    id={ this.props.id }
                    ref={ this.buttonRef }
                    className={ `ddfcpos-select${ this.state.open ? ' is-open' : '' }${ this.props.className ? ` ${ this.props.className }` : '' }` }
                    aria-haspopup="listbox"
                    aria-expanded={ this.state.open }
                    aria-label={ this.props[ 'aria-label' ] }
                    disabled={ this.props.disabled }
                    onClick={ this.state.open ? this.close : this.open }
                    onKeyDown={ this.handleKeyDown }
                >
                    <span className={ selected && '' !== selected.value ? '' : 'is-placeholder' }>{ selected ? selected.label : '' }</span>
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true"><path d="M6 9l6 6 6-6" /></svg>
                </button>
                { this.state.open && this.state.rect ? this.renderMenu( options ) : null }
            </Fragment>
        );
    }
}

export default Select;
