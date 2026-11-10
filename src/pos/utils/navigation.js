export const isMobileViewport = () => window.screen.width < 768;

export const pushRoute = ( history, route ) => {
    if ( history && history.push ) {
        history.push( route );
    }
};

export const getRouteType = ( props, fallbackType ) => (
    props && props.match && props.match.params ? props.match.params.type : fallbackType
);

export const getOrderDetailsPanel = () => document.querySelector( '.ddfcpos-order-details-wrapper' );
