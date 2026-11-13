import { __ } from '@wordpress/i18n';
import database from '../services/database';
import { fetchRequest } from '../services/request';
import { notify } from '../services/notifications';
import { getPosApi } from '../services/runtime';
import { normalizeRecordId } from '../utils/value';

import { COUNTRY_AND_STATES } from '../state/action-types';
export { COUNTRY_AND_STATES };
const COUNTRIES_AND_STATES_TABLE = 'countries_and_states';
const getCountriesAndStatesTable = () => database.table(COUNTRIES_AND_STATES_TABLE);
const normalizeCountriesAndStatesRecord = response => (
    response && typeof response === 'object' ? response : null
);
const buildCountriesRequest = outletId => ({
    outlet_id: normalizeRecordId(outletId),
});

const notifyCountriesLoading = () => notify({
    title: __('Loading Countries & States', 'devdiggers-multipos-for-fluentcart'),
    message: __('Loading Countries & States in the POS', 'devdiggers-multipos-for-fluentcart'),
    type: 'info',
});

const notifyCountriesLoaded = () => notify({
    title: __('Countries & States Loaded', 'devdiggers-multipos-for-fluentcart'),
    message: __('All Countries & States are loaded successfully', 'devdiggers-multipos-for-fluentcart'),
    type: 'success',
});

export const setCountriesAndStates = countries_and_states => {
    return {
        type: COUNTRY_AND_STATES,
        countries_and_states
    }
};

const getCountriesAndStatesFromStorage = () => getCountriesAndStatesTable().toArray();
const getFirstStoredCountriesAndStates = countriesAndStates => countriesAndStates && countriesAndStates.length ? countriesAndStates[0] : null;

const loadCountriesAndStatesFromStorage = () => getCountriesAndStatesFromStorage().then(data => data ? data : false);

export const getCountriesAndStates = outletId => dispatch => {
    return loadCountriesAndStatesFromStorage().then(records => {
        const currentRecord = getFirstStoredCountriesAndStates(records);

        if (currentRecord) {
            dispatch(setCountriesAndStates(currentRecord));
            return currentRecord;
        }

        notifyCountriesLoading();

        return fetchRequest(getPosApi().GET_COUNTRIES_STATES_ENDPOINT, buildCountriesRequest(outletId)).then(response => {
            const record = normalizeCountriesAndStatesRecord(response);
            if (!record) {
                return null;
            }

            return getCountriesAndStatesTable().put(record).then(() => record);
        }).then(savedRecord => {
            if (!savedRecord) {
                dispatch(setCountriesAndStates({
                    countries: [],
                    base_country: '',
                    states: [],
                }));
                return null;
            }

            return getCountriesAndStatesFromStorage().then(dbres => {
                const dbRecord = getFirstStoredCountriesAndStates(dbres);

                dispatch(setCountriesAndStates(dbRecord));
                notifyCountriesLoaded();
                return dbRecord;
            });
        });
    });
}
