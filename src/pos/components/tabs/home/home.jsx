import React, { Component, Fragment } from 'react';
import { connect } from 'react-redux';
import { bindActionCreators } from 'redux';
import { __, sprintf } from '@wordpress/i18n';
import { Link } from 'react-router-dom';
import LazyLoad from 'react-lazyload';
import { FixedSizeGrid as Grid } from 'react-window';
import { AppstoreOutlined, BarcodeOutlined, SearchOutlined, CheckOutlined } from '@ant-design/icons';
import { getProducts } from '../../../actions/products';
import { getCategories } from '../../../actions/categories';
import { getCustomers } from '../../../actions/customers';
import { getCountriesAndStates } from '../../../actions/countries-and-states';
import { filterProductsByCategory, filterProductsBySearch } from './../../../actions/products';
import Product from './product/product.jsx';
import Popup from './../../popup/popup.jsx';
import { addToCart, getProductViaBarcode } from './../../../actions/cart';
import { getSettings } from './../../../actions/settings';
import { getTables } from './../../../actions/tables';
import { getPosRoute } from '../../../services/routes';
import { notify } from '../../../services/notifications';
import { getPosConfig } from '../../../services/runtime';
import { isMobileViewport } from '../../../utils/navigation';
import { getOutletId, hasEntries, isSameId } from '../../../utils/value';
import { isEnterKey } from '../../../utils/event';
import EmptyState from '../../emptyState/emptyState';

const getHistoryAction = history => history && history.action;
const getProductsStateList = productsState => Array.isArray(productsState && productsState.list) ? productsState.list : [];
// No category is stored as '' and routed as 0: both mean All, or the grid would refilter (and drop the search) on every update.
const getProductsStateCategory = productsState => productsState && productsState.category !== undefined && productsState.category !== '' ? productsState.category : 0;
const getEventInputValue = event => event && event.target ? event.target.value : '';

const getCategoryId = match => {
    const categoryId = match && match.params ? match.params.cid : undefined;

    if (categoryId === undefined) {
        return 0;
    }

    return categoryId === 'all' ? 0 : categoryId;
};

const getVisibleProducts = (productsState, categoryId) => {
    if (productsState.searchTerm) {
        return productsState.searchedProducts;
    }

    if (categoryId) {
        return productsState.categoryProducts;
    }

    return productsState.list;
};

const sortProductsByTitle = products => [...products].sort((firstProduct, secondProduct) => {
    if (firstProduct.title > secondProduct.title) {
        return 1;
    }

    if (secondProduct.title > firstProduct.title) {
        return -1;
    }

    return 0;
});

const buildProductRows = (products, columnCount) => {
    const rows = [];

    (Array.isArray(products) ? products : []).forEach((product, index) => {
        const rowIndex = Math.floor(index / columnCount);

        if (!Array.isArray(rows[rowIndex])) {
            rows[rowIndex] = [];
        }

        rows[rowIndex].push(product);
    });

    return rows;
};

const getProductsListStyle = displayCategory => (
    displayCategory === 'disabled' ? { height: 'calc(100vh - 95px)' } : { height: 'calc(100vh - 218px)' }
);

const getSearchWrapperStyle = () => (
    { gridTemplateColumns: '60% max-content auto' }
);

const getProductGridItemClassName = (columnIndex, rowIndex) => (
    columnIndex % 2
        ? rowIndex % 2 === 0
            ? 'ddfcpos-grid-item-odd'
            : 'ddfcpos-grid-item-even'
        : rowIndex % 2
            ? 'ddfcpos-grid-item-odd'
            : 'ddfcpos-grid-item-even'
);

const getProductGridLayout = (screenWidth, wrapperWidth) => {
    let columnCount = 6;
    let columnWidth = 260;
    let rowHeight = 134;
    let containerWidth = wrapperWidth || 1000;

    if (isMobileViewport()) {
        containerWidth = screenWidth;
    }

    if ('image_top' === getPosConfig().product_layout) {
        columnWidth = 150;

        if (screenWidth >= 1024) {
            // Columns follow the space beside the cart: about 165px per tile, so cards keep a
            // readable size on wide screens instead of squeezing in one more column.
            columnCount = Math.max(screenWidth > 1024 ? 4 : 3, Math.floor((containerWidth - 10) / 165));
            columnWidth = containerWidth / (columnCount + 0.1);
        } else if (screenWidth > 650 && screenWidth < 1024) {
            columnCount = 4;
            columnWidth = containerWidth / 4;
        } else if (screenWidth > 440 && screenWidth <= 650) {
            columnCount = 3;
            columnWidth = containerWidth / 3.09;
        } else {
            columnCount = 2;
            columnWidth = containerWidth / 2.09;
        }

        // A square photo tile (card width less 16px padding) plus about 112px for two lines of
        // name, the price and the stock badge, with the card's 15px of margins.
        rowHeight = Math.round(columnWidth + 112);
    } else {
        // Image left tiles need about 280px to fit a photo beside two lines of name, the price and
        // the stock badge, so the column count follows the space beside the cart, not the screen.
        columnCount = Math.max(isMobileViewport() ? 1 : 2, Math.floor((containerWidth - 10) / 280));
        columnWidth = containerWidth / (columnCount + 0.05);
    }

    return {
        columnCount,
        columnWidth,
        rowHeight,
        containerWidth,
    };
};

const getCategoryCardClassName = isActive => (
    isActive ? 'ddfcpos-category-card ddfcpos-category-active' : 'ddfcpos-category-card'
);

const getCategoryCardsLength = categoryElement => (
    Math.floor((categoryElement ? categoryElement.offsetWidth - 10 : 0) / 86)
);

const getProductCardKey = product => product.id || product.product_id || product.slug || product.title;

const getBarcodeResetState = () => ({
    barcodeValue: '',
});
const getProductsWrapperElement = () => document.querySelector('.ddfcpos-products-tab-wrapper');
const getCategoryWrapperElement = () => document.querySelector('.ddfcpos-category-wrapper');
const getGridDimensions = () => {
    const productsWrapper = getProductsWrapperElement();
    const categoryWrapper = getCategoryWrapperElement();
    const gridLayout = getProductGridLayout(window.innerWidth, productsWrapper ? productsWrapper.offsetWidth : 1000);

    return {
        ...gridLayout,
        categoriesCardsLength: getCategoryCardsLength(categoryWrapper),
    };
};

const shouldShowViewAllCategories = (categoriesCardsLength, categoriesHTML) => Array.isArray(categoriesHTML) && categoriesCardsLength < categoriesHTML.length;
const getProductsResultsLabel = products => `${Array.isArray(products) ? products.length : 0} ${__('Results', 'devdiggers-multipos-for-fluentcart')}`;

const getVisibleCategoryCards = (categoriesHTML, categoriesCardsLength) => (
    shouldShowViewAllCategories(categoriesCardsLength, categoriesHTML)
        ? categoriesHTML.slice(0, categoriesCardsLength)
        : categoriesHTML
);
const getGridCellProduct = (productRows, rowIndex, columnIndex) => (
    Array.isArray(productRows[rowIndex]) ? productRows[rowIndex][columnIndex] : undefined
);

const buildCategoryCards = (categories, activeCategoryId, handleHideAllCategoriesPopup) => ([
    <Link key="all" className={getCategoryCardClassName(isSameId(0, activeCategoryId))} onClick={handleHideAllCategoriesPopup} to={getPosRoute('/category/all')}>
        <span role="img" aria-hidden="true" className="anticon ddfcpos-all-categories-icon">
            <svg viewBox="0 0 24 24" width="1em" height="1em" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinejoin="round" focusable="false">
                <rect x="3.5" y="3.5" width="7" height="7" rx="2" />
                <rect x="13.5" y="3.5" width="7" height="7" rx="2" />
                <rect x="3.5" y="13.5" width="7" height="7" rx="2" />
                <rect x="13.5" y="13.5" width="7" height="7" rx="2" />
            </svg>
        </span>
        <p>{__('All', 'devdiggers-multipos-for-fluentcart')}</p>
    </Link>,
    ...(Array.isArray(categories) ? categories : []).map(category => (
        <Link key={category.id} className={getCategoryCardClassName(isSameId(category.id, activeCategoryId))} onClick={handleHideAllCategoriesPopup} to={getPosRoute(`/category/${category.id}`)}>
            {category.image ? <img src={category.image} alt={category.name} width="24" height="24" /> : ''}
            <p title={category.name}>{category.name}</p>
        </Link>
    )),
]);

class Home extends Component {
    constructor(props) {
        super(props);
        this.barcodeBuffer = '';

        this.state = {
            cid: '',
            search: '',
            productsLoaded: false,
            categoryProductsLoaded: false,
            showAllCategoriesPopup: false,
            showBarcodePopup: false,
            barcodeValue: '',
        };
    }

    componentDidMount() {
        const outletId = getOutletId(this.props.outlet);
        this.props.getCategories(outletId);
        this.props.getProducts(outletId).then(records => {
            this.setState({
                productsLoaded: true
            });
        });
        this.props.getCustomers(outletId);
        this.props.getCountriesAndStates(outletId);
        this.props.getTables();
        this.props.getSettings();

        if (getHistoryAction(this.props.history) === 'POP') {
            this.handleHideAllCategoriesPopup();
        }
        window.addEventListener('keypress', this.handleBarcodeKeypress);
    }

    componentWillUnmount() {
        window.removeEventListener('keypress', this.handleBarcodeKeypress);
    }

    handleBarcodeKeypress = event => {
        const target = event.target || event.srcElement;

        if (!target || !target.tagName || target.tagName.toUpperCase() !== 'BODY') {
            return;
        }

        // A scanner types a code in a burst. A gap of over 100ms means a person typed, so start over
        // and a stray key never sticks to the front of the next scan.
        const now = Date.now();

        if (now - (this.barcodeLastKeyAt || 0) > 100) {
            this.barcodeBuffer = '';
        }

        this.barcodeLastKeyAt = now;

        if (isEnterKey(event) && this.barcodeBuffer) {
            this.setState({
                barcodeValue: this.barcodeBuffer
            }, () => {
                this.handleAddProductViaBarcode(event);
                this.barcodeBuffer = '';
            });
            return;
        }

        if (/^[0-9a-zA-Z\-\/._]$/.test(event.key)) {
            this.barcodeBuffer += event.key;
        }
    }

    componentDidUpdate(prevProps) {
        const products = getProductsStateList(this.props.products);
        const prevProducts = getProductsStateList(prevProps.products);
        const cid = getCategoryId(this.props.match);
        const prevCid = getCategoryId(prevProps.match);
        const productsListChanged = prevProducts !== products;
        const categoryStateChanged = !isSameId(getProductsStateCategory(this.props.products), cid);

        if (products.length && this.state.productsLoaded && (productsListChanged || !isSameId(cid, prevCid) || !this.state.categoryProductsLoaded) && categoryStateChanged) {
            this.setState({
                cid: cid,
                categoryProductsLoaded: true
            }, () => {
                this.props.filterProductsByCategory(cid, products);
            });
        }
    }

    handleProductSearch = event => {
        const searchValue = getEventInputValue(event);

        this.setState({
            search: searchValue
        });

        this.props.filterProductsBySearch(searchValue.toLowerCase(), this.props.products);
    }

    handleToggleShowAllCategories = () => {
        this.setState(prevState => ({
            showAllCategoriesPopup: !prevState.showAllCategoriesPopup
        }));
    }

    handleHideAllCategoriesPopup = () => {
        if (this.state.showAllCategoriesPopup) {
            this.setState({
                showAllCategoriesPopup: false
            });
        }
    }

    handleToggleBarcodePopup = () => {
        this.setState(prevState => ({
            showBarcodePopup: !prevState.showBarcodePopup
        }));
    }

    handleBarcodeInput = event => {
        if (isEnterKey(event)) {
            this.handleAddProductViaBarcode();
        } else {
            const barcodeValue = getEventInputValue(event);
            this.setState({
                barcodeValue
            });
        }
    }

    handleBarcodeFormSubmit = event => this.handleAddProductViaBarcode(event)
    handleBarcodeInputChange = event => this.handleBarcodeInput(event)
    handleProductSearchChange = event => this.handleProductSearch(event)

    renderBarcodePopup = () => {
        if (!this.state.showBarcodePopup) {
            return null;
        }

        const popupContent = (
            <Fragment>
                <h2>{__('Enter/Scan Barcode', 'devdiggers-multipos-for-fluentcart')}</h2>
                <form onSubmit={this.handleBarcodeFormSubmit}>
                    <label className="ddfcpos-popup-field">
                        <span>{__('Barcode', 'devdiggers-multipos-for-fluentcart')}</span>
                        <input type="text" onChange={this.handleBarcodeInputChange} placeholder={__('Enter/Scan Barcode', 'devdiggers-multipos-for-fluentcart')} value={this.state.barcodeValue} autoComplete="off" autoFocus />
                    </label>
                    <p className="ddfcpos-popup-hint">{__('Press enter after entering barcode to add products.', 'devdiggers-multipos-for-fluentcart')}</p>
                </form>
            </Fragment>
        );

        return (
            <Popup
                handleOverlay={this.handleToggleBarcodePopup}
                popupContent={popupContent}
                notDisabled={true}
                hideCancelButton={true}
                singleButton={true}
                successButtonText={<Fragment><CheckOutlined />{__('Done', 'devdiggers-multipos-for-fluentcart')}</Fragment>}
                handleSuccess={this.handleToggleBarcodePopup}
                handleCancel={this.handleToggleBarcodePopup}
            />
        );
    }

    renderAllCategoriesPopup = categoriesHTML => {
        if (!this.state.showAllCategoriesPopup) {
            return null;
        }

        return (
            <Fragment>
                <div className="ddfcpos-popup-overlay" onClick={this.handleToggleShowAllCategories}></div>
                <div className="ddfcpos-all-categories-popup">
                    <div className="ddfcpos-all-categories-popup-content">
                        <h2>{__('All Categories', 'devdiggers-multipos-for-fluentcart')}</h2>
                        <div>
                            {categoriesHTML}
                        </div>
                    </div>
                </div>
            </Fragment>
        );
    }

    renderCategoriesSection = (categories, categoriesCardsLength) => {
        if (!categories.length) {
            return null;
        }

        const allCategoriesHTML = buildCategoryCards(categories, this.state.cid, this.handleHideAllCategoriesPopup);
        const categoriesHTML = getVisibleCategoryCards(allCategoriesHTML, categoriesCardsLength);
        const viewAllCategoriesHTML = shouldShowViewAllCategories(categoriesCardsLength, allCategoriesHTML) ? (
            <Link className="ddfcpos-category-card" to="#" onClick={this.handleToggleShowAllCategories}>
                <AppstoreOutlined />
                <p>{__('View All', 'devdiggers-multipos-for-fluentcart')}</p>
            </Link>
        ) : null;

        return (
            <div className="ddfcpos-category-wrapper">
                <h2>{__('Select Category', 'devdiggers-multipos-for-fluentcart')}</h2>
                <div className="ddfcpos-categories-container">
                    {categoriesHTML}
                    {viewAllCategoriesHTML}
                    {this.renderAllCategoriesPopup(allCategoriesHTML)}
                </div>
            </div>
        );
    }

    handleAddProductViaBarcode = event => {
        if (event) {
            event.preventDefault();
        }

        const barcodeValue = this.state.barcodeValue;

        this.props.getProductViaBarcode(barcodeValue).then(product => {
            if (hasEntries(product)) {
                this.props.addToCart(product.product_id, 1);
            } else {
                notify({
                    title: __('Barcode Error', 'devdiggers-multipos-for-fluentcart'),
                    /* translators: %s: scanned barcode value */
                    message: sprintf(__('No product exists with this barcode "%s".', 'devdiggers-multipos-for-fluentcart'), barcodeValue),
                    type: 'danger',
                });
            }
        });

        this.setState(getBarcodeResetState());
    }

    render() {
        const categories = Array.isArray(this.props.categories) ? this.props.categories : [];
        const mainProducts = this.props.products || {};
        const activeCategoryId = getCategoryId(this.props.match);
        // The free POS sells simple products only.
        const products = sortProductsByTitle((getVisibleProducts(mainProducts, activeCategoryId) || []).filter(product => product.type === 'simple'));


        const { columnCount, columnWidth, rowHeight, containerWidth, categoriesCardsLength } = getGridDimensions();

        const pA = buildProductRows(products, columnCount);

        const Cell = ({ columnIndex, rowIndex, style }) => {
            const gridProduct = getGridCellProduct(pA, rowIndex, columnIndex);

            if (gridProduct !== undefined) {

                return (
                    <div
                        className={getProductGridItemClassName(columnIndex, rowIndex)}
                        style={style}
                    >
                        <LazyLoad
                            once={true}
                            key={getProductCardKey(gridProduct)}
                            overflow
                            height={200}
                        >
                            <Product product={gridProduct} outlet={this.props.outlet} notification={this.props.notification} />
                        </LazyLoad>
                    </div>
                )
            }

            return null;
        };

        const barcodePopupHTML = this.renderBarcodePopup();

        const style = getSearchWrapperStyle();

        return (
            <div className="ddfcpos-products-tab-wrapper">
                {(this.props.settings || {}).display_category !== 'disabled' ?
                    this.renderCategoriesSection(categories, categoriesCardsLength)
                    : null}
                <div className="ddfcpos-search-wrapper">
                    <h2>{__('Products', 'devdiggers-multipos-for-fluentcart')}</h2>

                    <div className="ddfcpos-search-input-wrapper" style={style}>
                        <div className="ddfcpos-search-input">
                            <SearchOutlined />
                            <input type="text" className="ddfcpos-form-control" value={this.state.search} placeholder={__('Search Product by title, ID, SKU or Barcode Number', 'devdiggers-multipos-for-fluentcart')} onChange={this.handleProductSearchChange} autoComplete="off" />
                        </div>
                        <div className="ddfcpos-icon-card ddfcpos-barcode-icon" onClick={this.handleToggleBarcodePopup} title={__('Add Product via Barcode', 'devdiggers-multipos-for-fluentcart')}>
                            <BarcodeOutlined />
                        </div>
                        <span>{getProductsResultsLabel(products)}</span>
                    </div>

                    {barcodePopupHTML}
                </div>
                {products.length > 0 ?
                    <Grid
                        className="ddfcpos-grid ddfcpos-products-list"
                        columnCount={columnCount}
                        columnWidth={columnWidth}
                        height={1000}
                        rowCount={pA.length}
                        rowHeight={rowHeight}
                        width={containerWidth}
                        style={getProductsListStyle((this.props.settings || {}).display_category)}
                    >
                        {Cell}
                    </Grid>
                    :
                    <EmptyState icon="box" title={__('No Products Found', 'devdiggers-multipos-for-fluentcart')} hint={__('Try a different search or category.', 'devdiggers-multipos-for-fluentcart')} />
                }
            </div>
        );
    }
}

const mapStateToProps = state => ({
    categories: state.categories,
    products: state.products,
    settings: state.settings,
});

const mapDispatchToProps = dispatch => bindActionCreators({
    getProducts,
    getCategories,
    getCustomers,
    getCountriesAndStates,
    filterProductsByCategory,
    filterProductsBySearch,
    getProductViaBarcode,
    addToCart,
    getSettings,
    getTables
}, dispatch);

export default connect(mapStateToProps, mapDispatchToProps)(Home);
