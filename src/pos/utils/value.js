export const hasEntries = value => (
    Boolean( value )
    && typeof value === 'object'
    && Object.keys( value ).length > 0
);

export const isSameId = ( firstId, secondId ) => String( firstId ) === String( secondId );

export const parseWholeNumber = value => parseInt( value, 10 ) || 0;

export const toDecimal = value => parseFloat( value ) || 0;

export const normalizeRecordId = id => {
    const numericId = Number( id );

    return id !== '' && ! Number.isNaN( numericId ) ? numericId : id;
};

export const getOutletId = outlet => outlet && outlet.id;

export const getOutletMode = outlet => outlet && outlet.mode;
