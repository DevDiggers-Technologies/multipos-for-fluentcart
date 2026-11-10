import React from 'react';

// Line glyphs on a 24px grid with a 1.5 stroke, so every empty screen uses the same icon weight.
const GLYPHS = {
    receipt: (
        <g>
            <path d="M5.5 3.5h13v17l-2.17-1.4-2.16 1.4L12 19.1l-2.17 1.4-2.16-1.4-2.17 1.4z" />
            <path d="M9 8.5h6M9 12h6" />
        </g>
    ),
    users: (
        <g>
            <circle cx="9" cy="8.5" r="3.25" />
            <path d="M3.5 19c.6-3 2.8-4.75 5.5-4.75S13.9 16 14.5 19" />
            <path d="M15.5 5.5a3 3 0 0 1 0 6M17.5 14.6c1.6.6 2.6 2.1 3 4.4" />
        </g>
    ),
    box: (
        <g>
            <path d="M12 3.25 19.75 7.5v9L12 20.75 4.25 16.5v-9z" />
            <path d="M4.25 7.5 12 11.75l7.75-4.25M12 11.75v9" />
        </g>
    ),
    cart: (
        <g>
            <path d="M3 4.25h2.1l2.15 10.5h10.6l1.9-7.5H6.2" />
            <circle cx="9" cy="18.75" r="1.25" />
            <circle cx="16.5" cy="18.75" r="1.25" />
        </g>
    ),
    bag: (
        <g>
            <path d="M5.25 7.75h13.5l-.9 12.5H6.15z" />
            <path d="M9 7.75V6.5a3 3 0 0 1 6 0v1.25" />
        </g>
    ),
};

const EmptyState = ({ icon = 'receipt', title, hint, action }) => (
    <div className="ddfcpos-no-results ddfcpos-empty-state">
        <span className="ddfcpos-empty-state-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" width="1em" height="1em" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round" focusable="false">
                {GLYPHS[icon] || GLYPHS.receipt}
            </svg>
        </span>
        <p>{title}</p>
        {hint ? <small>{hint}</small> : null}
        {action || null}
    </div>
);

export default EmptyState;
