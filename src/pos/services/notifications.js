import { store } from 'react-notifications-component';

// Keep at most this many toasts. Older ones are removed through the store, not hidden with CSS,
// so the stack never jumps when a hidden toast suddenly becomes visible.
const MAX_VISIBLE = 3;

// Toasts on screen, oldest first, keyed by their text so a burst of identical messages shows once.
const activeToasts = [];

const getNotificationDismiss = duration => ( {
    duration,
    pauseOnHover: true,
} );
const buildNotificationPayload = ( {
    title,
    message,
    type,
    duration,
    container,
    onRemoval,
} ) => ( {
    title,
    message,
    type,
    insert      : 'top',
    container,
    animationIn : [ 'ddfcpos-toast-in' ],
    animationOut: [ 'ddfcpos-toast-out' ],
    slidingEnter: { duration: 220, timingFunction: 'cubic-bezier(.2, .8, .2, 1)', delay: 0 },
    slidingExit : { duration: 200, timingFunction: 'ease-in', delay: 0 },
    dismiss     : getNotificationDismiss( duration ),
    onRemoval,
} );

export const notify = ( {
    title,
    message,
    type = 'info',
    duration = 3000,
    container = 'top-center',
} ) => {
    if ( ! message ) {
        return;
    }

    const key = `${ type }|${ title || '' }|${ message }`;

    if ( activeToasts.some( toast => toast.key === key ) ) {
        return;
    }

    const toast = { key, id: null };

    activeToasts.push( toast );

    toast.id = store.addNotification( buildNotificationPayload( {
        title,
        message,
        type,
        duration,
        container,
        onRemoval: () => {
            const index = activeToasts.indexOf( toast );

            if ( index > -1 ) {
                activeToasts.splice( index, 1 );
            }
        },
    } ) );

    // Drop the oldest from the list as it is removed, not in onRemoval: the library never calls onRemoval
    // for a toast removed while it is still sliding in, so in a burst (the POS start sends a dozen) the
    // same toast was removed again and again, and its message stayed blocked as a duplicate.
    while ( activeToasts.length > MAX_VISIBLE ) {
        store.removeNotification( activeToasts.shift().id );
    }
};
