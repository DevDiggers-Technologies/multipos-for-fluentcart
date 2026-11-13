import { __ } from '@wordpress/i18n';
import database from '../services/database';
import { fetchRequest } from '../services/request';
import { notify } from '../services/notifications';
import { getPosApi, getPosConfigValue } from '../services/runtime';
import { normalizeRecordId } from '../utils/value';

import { PRODUCTS, TAXES } from '../state/action-types';
export { PRODUCTS, TAXES };
const PRODUCTS_TABLE = 'products';
const TAXES_TABLE = 'taxes';
const getProductsTable = () => database.table(PRODUCTS_TABLE);
const getTaxesTable = () => database.table(TAXES_TABLE);
const getProductsList = productsStateOrList => (
    Array.isArray(productsStateOrList)
        ? productsStateOrList
        : (productsStateOrList && Array.isArray(productsStateOrList.list) ? productsStateOrList.list : [])
);
const getCategoryProductsList = productsState => (
    productsState && Array.isArray(productsState.categoryProducts) ? productsState.categoryProducts : []
);
const buildProductsRequest = (outletId, currentPage = 0, perPage = -1) => ({
    outlet_id: normalizeRecordId(outletId),
    per_page: perPage,
    current_page: currentPage
});
export const setProducts = products => {
    return {
        type: PRODUCTS,
        products
    }
};

export const setTaxes = taxes => {
    return {
        type: TAXES,
        taxes
    }
};

export const getProductsFromStorage = () => {
    return getProductsTable().where('type').equals('simple').toArray();
}

const loadProductsFromStorage = () => getProductsFromStorage().then(data => data ? data : false);

const getTaxesFromStorage = () => getTaxesTable().toArray();

const loadTaxesFromStorage = () => getTaxesFromStorage().then(data => data ? data : false);

const createProductsState = (list, isFetching = 0, overrides = {}) => ({
    list,
    isFetching,
    searchTerm: '',
    searchedProducts: [],
    category: '',
    categoryProducts: [],
    ...overrides,
});

const buildFilteredProductsState = (productsState, overrides = {}) => createProductsState(productsState.list, productsState.isFetching, {
    searchTerm: productsState.searchTerm,
    searchedProducts: productsState.searchedProducts,
    category: productsState.category,
    categoryProducts: productsState.categoryProducts,
    ...overrides,
});

const syncProductsStateFromStorage = (dispatch, isFetching = 0) => (
    getProductsFromStorage().then(productsFromStorage => {
        const products = createProductsState(productsFromStorage, isFetching);

        dispatch(setProducts(products));

        return products;
    })
);

const normalizeTaxesResponse = responseTaxes => (
    responseTaxes && typeof responseTaxes === 'object' ? responseTaxes : {}
);
const storeTaxes = (responseTaxes = {}, dispatch) => {
    const normalizedTaxes = normalizeTaxesResponse(responseTaxes);
    const taxes = Object.keys(normalizedTaxes).map(id => ({
        id: normalizeRecordId(id),
        rate: normalizedTaxes[id].rate,
        shipping: normalizedTaxes[id].shipping,
        label: normalizedTaxes[id].label,
        compound: normalizedTaxes[id].compound,
    }));

    if (!taxes.length) {
        return Promise.resolve(false);
    }

    return getTaxesTable().bulkPut(taxes).then(() => {
        dispatch(setTaxes(taxes));

        return taxes;
    });
};

export const getProducts = outletId => dispatch => {
    const taxesPromise = loadTaxesFromStorage().then(storedTaxes => {
        if (storedTaxes && storedTaxes.length) {
            dispatch(setTaxes(storedTaxes));
        }

        return storedTaxes;
    });

    return Promise.all([taxesPromise, loadProductsFromStorage()]).then(([, storedProducts]) => {
        if (storedProducts && storedProducts.length) {
            const products = createProductsState(storedProducts);
            dispatch(setProducts(products));

            return products;
        }

        notify({
            title: __('Loading Products', 'devdiggers-multipos-for-fluentcart'),
            message: __('Loading Products in the POS.', 'devdiggers-multipos-for-fluentcart'),
            type: 'info',
        });

        return getProductsRecursively(buildProductsRequest(outletId), dispatch);
    });
}

const getProductsRecursively = (postData, dispatch, remainingProducts = '') => (
    fetchRequest(getPosApi().GET_PRODUCTS_ENDPOINT, postData).then(response => {
        if (response.total_products !== null && typeof response.total_products === 'number') {
            const storeTaxesPromise = storeTaxes(response.taxes, dispatch);

            return storeTaxesPromise.then(() => {
                if (response.total_products) {
                    const pagedRequest = buildProductsRequest(
                        postData.outlet_id,
                        postData.current_page + 1,
                        getPosConfigValue('per_page', -1)
                    );

                    return getProductsRecursively(pagedRequest, dispatch, response.total_products);
                }

                return syncProductsStateFromStorage(dispatch);
            });
        }

        const batchProducts = Array.isArray(response) ? response : [];

        // A page can come back empty when every product on it is skipped (out of stock, grouped, excluded),
        // so only stop once the whole catalogue has been requested.
        if (!batchProducts.length && !(remainingProducts - postData.per_page > 0)) {
            return syncProductsStateFromStorage(dispatch);
        }

        return (batchProducts.length ? getProductsTable().bulkPut(batchProducts) : Promise.resolve()).then(() => {
            const updatedRemainingProducts = remainingProducts > 0 ? remainingProducts - postData.per_page : 0;

            if (updatedRemainingProducts > 0) {
                const nextRequest = buildProductsRequest(postData.outlet_id, postData.current_page + 1, postData.per_page);

                return syncProductsStateFromStorage(dispatch, 1).then(() => (
                    getProductsRecursively(nextRequest, dispatch, updatedRemainingProducts)
                ));
            }

            return syncProductsStateFromStorage(dispatch).then(products => {
                notify({
                    title: __('Products Loaded', 'devdiggers-multipos-for-fluentcart'),
                    message: __('All Products are loaded successfully.', 'devdiggers-multipos-for-fluentcart'),
                    type: 'success',
                });

                return products;
            });
        });
    })
);

export const filterProductsByCategory = (category, pos_products) => (dispatch) => {
    const allProducts = getProductsList(pos_products);

    if (category) {
        const normalizedCategoryId = normalizeRecordId(category);
        const final_products = allProducts.filter(product => (
            Array.isArray(product.categories) &&
            product.categories.length > 0 &&
            product.categories.some(productCategoryId => String(productCategoryId) === String(normalizedCategoryId))
        ));

        const products = createProductsState(allProducts, 0, {
            category: normalizedCategoryId,
            categoryProducts: final_products,
        });

        dispatch(setProducts(products));
        return products;
    } else {
        const products = createProductsState(allProducts);

        dispatch(setProducts(products));
        return products;
    }
};

export const filterProductsBySearch = (search, posProducts) => (dispatch) => {
    const productsState = posProducts || createProductsState([]);
    const searchTerm = String(search || '');

    if (searchTerm !== '') {
        const normalizedSearch = searchTerm.toLowerCase();
        const categoryProducts = getCategoryProductsList(productsState);
        const searchableProducts = categoryProducts.length ? categoryProducts : getProductsList(productsState);
        const matchedProducts = searchableProducts.filter(product => productMatchesSearch(product, normalizedSearch));

        dispatch(setProducts(buildFilteredProductsState(productsState, {
            searchedProducts: matchedProducts,
            searchTerm,
        })));
    } else {
        dispatch(setProducts(buildFilteredProductsState(productsState, {
            searchedProducts: [],
            searchTerm: '',
        })));
    }
}

const searchableValueMatches = (value, search) => (
    value !== undefined &&
    value !== null &&
    String(value).toLowerCase().includes(search)
);

const productIdentityMatchesSearch = (product, search) => (
    searchableValueMatches(product.title, search) ||
    searchableValueMatches(product.sku, search) ||
    searchableValueMatches(product.barcode, search)
);

const productMatchesSearch = (product, search) => {
    return productIdentityMatchesSearch(product, search);
};
