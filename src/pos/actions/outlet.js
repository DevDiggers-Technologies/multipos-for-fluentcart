import database from '../services/database';
import { getAssignedOutlets } from '../services/runtime';

import { OUTLET } from '../state/action-types';
export { OUTLET };
const getOutletStore = () => database.table( 'outlet' );
// The store holds the one outlet this till works in. Clear it first: a record another build saved
// under the same id as a number or a string would otherwise sit beside it and could be read first.
const saveOutlet = outlet => getOutletStore().clear().then( () => getOutletStore().put( outlet ) );
const getStoredOutlet = outletRecords => Array.isArray( outletRecords ) && outletRecords.length > 0 ? outletRecords[0] : {};
const hasOutletIdentifier = outletId => outletId !== undefined && outletId !== null && outletId !== '';

export const setOutlet = outlet => {
    return {
        type: OUTLET,
        outlet
    }
};

const getResolvedOutlet = outletId => {
    const outlet = getAssignedOutlets().find( assignedOutlet => String( assignedOutlet.id ) === String( outletId ) );

    if ( ! outlet ) {
        return null;
    }

    return {
        ...outlet,
    };
};

export const setCurrentOutlet = ( outletId = '' ) => dispatch => {
    return getOutletStore().toArray().then( outletRecords => {
        if ( hasOutletIdentifier( outletId ) ) {
            const outlet = getResolvedOutlet( outletId );

            if ( ! outlet ) {
                dispatch( setOutlet( {} ) );
                return {};
            }

            return saveOutlet( outlet ).then( () => {
                dispatch( setOutlet( outlet ) );
                return outlet;
            });
        }

        const storedOutlet = getStoredOutlet( outletRecords );
        const freshOutlet  = Object.keys( storedOutlet ).length ? getResolvedOutlet( storedOutlet.id ) : null;

        // An outlet cached by an earlier session (since unassigned, deleted, or saved by another
        // MultiPOS build) is refused by the server, so drop it and pick again.
        if ( Object.keys( storedOutlet ).length && ! freshOutlet ) {
            return getOutletStore().clear().then( () => setCurrentOutlet()( dispatch ) );
        }

        // Keep the cached choice but take the server's copy, so outlet edits made in the admin
        // (name, address, payment methods) reach the till and its receipts.
        if ( freshOutlet ) {
            return saveOutlet( freshOutlet ).then( () => {
                dispatch( setOutlet( freshOutlet ) );
                return freshOutlet;
            });
        }

        // Single-outlet build: auto-select the only assigned outlet, no selection screen.
        const defaultOutlet = getStoredOutlet( getAssignedOutlets() );

        if ( Object.keys( defaultOutlet ).length ) {
            return saveOutlet( defaultOutlet ).then( () => {
                dispatch( setOutlet( defaultOutlet ) );
                return defaultOutlet;
            });
        }

        dispatch( setOutlet( {} ) );
        return {};
    } );
};
