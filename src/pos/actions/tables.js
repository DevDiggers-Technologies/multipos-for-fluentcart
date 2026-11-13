import database from '../services/database';
import { __ } from '@wordpress/i18n';
import { notify } from '../services/notifications';
import { getPosConfig } from '../services/runtime';

import { TABLES } from '../state/action-types';
export { TABLES };
const TABLES_TABLE = 'tables';
const getConfiguredTables = () => {
    const configuredTables = getPosConfig().tables;
    return configuredTables && typeof configuredTables === 'object' ? Object.values(configuredTables) : [];
};
const getOutletTableSlugs = currentState => currentState && currentState.outlet && Array.isArray(currentState.outlet.tables) ? currentState.outlet.tables : [];
const getOutletTableIdentifier = outletTable => {
    if (outletTable && typeof outletTable === 'object') {
        if (outletTable.slug !== undefined && outletTable.slug !== null && outletTable.slug !== '') {
            return String(outletTable.slug);
        }

        if (outletTable.id !== undefined && outletTable.id !== null && outletTable.id !== '') {
            return String(outletTable.id);
        }
    }

    if (outletTable !== undefined && outletTable !== null && outletTable !== '') {
        return String(outletTable);
    }

    return '';
};
const getTableIdentifier = table => {
    if (table && table.slug !== undefined && table.slug !== null && table.slug !== '') {
        return String(table.slug);
    }

    if (table && table.id !== undefined && table.id !== null && table.id !== '') {
        return String(table.id);
    }

    return '';
};
const mapAvailableOutletTables = outletTables => {
    const outletTableIdentifiers = outletTables.map(getOutletTableIdentifier).filter(Boolean);
    const configuredTables = getConfiguredTables();

    if (!outletTableIdentifiers.length) {
        return configuredTables;
    }

    return configuredTables.filter(table => outletTableIdentifiers.includes(getTableIdentifier(table)));
};
const getDefaultTableSlug = currentState => currentState && currentState.tables && currentState.tables.defaultTable ? currentState.tables.defaultTable.slug : '';

const buildTablesState = tablesList => {
    const defaultTable = tablesList.find(table => table.default);

    return {
        list: tablesList,
        defaultTable: defaultTable || {},
    };
};

export const setTables = tables => {
    return {
        type: TABLES,
        tables
    }
};

export const getTables = () => (dispatch, getState) => {
    return database.table(TABLES_TABLE).toArray().then(tables => {
        if (tables.length) {
            dispatch(setTables(buildTablesState(tables)));
            return tables;
        }

        const outletTables = getOutletTableSlugs(getState());
        const tablesData = mapAvailableOutletTables(outletTables);

        return database.table(TABLES_TABLE).bulkPut(tablesData).then(() => {
            dispatch(setTables(buildTablesState(tablesData)));
            return tablesData;
        });
    });
};

const setTableAsVacant = tableSlug => {
    if (!tableSlug) {
        return Promise.resolve();
    }

    return database.table(TABLES_TABLE).where('slug').equals(tableSlug).modify({ tableType: 'vacant' });
};

const setTableAsOccupiedAndDefault = tableSlug => {
    if (!tableSlug) {
        return Promise.resolve();
    }

    return database.table(TABLES_TABLE).where('slug').equals(tableSlug).modify({
        default: 1,
        tableType: 'occupied',
    });
};

export const updateDefaultTable = (tableSlug = '') => (dispatch, getState) => {
    const defaultTableSlug = getDefaultTableSlug(getState());
    return database.table(TABLES_TABLE).toCollection().modify(obj => {
        obj.default = 0;
    }).then(() => setTableAsVacant(defaultTableSlug)).then(() => setTableAsOccupiedAndDefault(tableSlug)).then(() => {
        dispatch(getTables());
        notifyTableSelection(tableSlug);
        return true;
    });
}

const notifyTableSelection = tableSlug => {
    notify({
        title: tableSlug ? __('Table Set', 'devdiggers-multipos-for-fluentcart') : __('Take Away Order', 'devdiggers-multipos-for-fluentcart'),
        message: tableSlug ? __('Table is set successfully for the order.', 'devdiggers-multipos-for-fluentcart') : __('No table is selected for take away orders.', 'devdiggers-multipos-for-fluentcart'),
        type: 'success',
    });
};
