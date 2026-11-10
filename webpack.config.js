/** @format */
/**
 * External dependencies
 */
const MiniCssExtractPlugin = require( 'mini-css-extract-plugin' );
const { get } = require( 'lodash' );
const path = require( 'path' );
const TerserPlugin = require( 'terser-webpack-plugin' );
const CssMinimizerPlugin = require( 'css-minimizer-webpack-plugin' );

/**
 * WordPress dependencies
 */
const CustomTemplatedPathPlugin = require( '@wordpress/custom-templated-path-webpack-plugin' );

const NODE_ENV = process.env.NODE_ENV || 'development';
const IS_PRODUCTION = NODE_ENV === 'production';
const SOURCE_EXTENSIONS = [ '.json', '.js', '.jsx' ];
const CAMEL_CASE_REPLACE_REGEX = /-([a-z])/g;
const JS_ENTRY_POINTS = {
	login                  : './src/login/index.js',
	admin                  : './src/admin/index.js',
	pos                    : './src/pos/index.js',
};
const COMMON_EXTERNALS = {
	'@wordpress/api-fetch'    : { this: [ 'wp', 'apiFetch' ] },
	'@wordpress/blocks'       : { this: [ 'wp', 'blocks' ] },
	'@wordpress/data'         : { this: [ 'wp', 'data' ] },
	'@wordpress/editor'       : { this: [ 'wp', 'editor' ] },
	'@wordpress/element'      : { this: [ 'wp', 'element' ] },
	'@wordpress/components'   : { this: [ 'wp', 'components' ] },
	'@wordpress/hooks'        : { this: [ 'wp', 'hooks' ] },
	'@wordpress/url'          : { this: [ 'wp', 'url' ] },
	'@wordpress/html-entities': { this: [ 'wp', 'htmlEntities' ] },
	'@wordpress/i18n'         : { this: [ 'wp', 'i18n' ] },
	'@wordpress/keycodes'     : { this: [ 'wp', 'keycodes' ] },
	'tinymce'                 : 'tinymce',
	'moment'                  : 'moment',
	'lodash'                  : 'lodash',
	'react-dom'               : 'ReactDOM',
	'react'                   : 'React',
};
const toCamelCase = value => value.replace( CAMEL_CASE_REPLACE_REGEX, ( match, letter ) => letter.toUpperCase() );
const createExternals = () => ( { ...COMMON_EXTERNALS } );

const createCssLoaders = () => ( [
	MiniCssExtractPlugin.loader,
	{
		loader : 'css-loader',
		options: {
			url      : false,
			sourceMap: false,
		},
	},
	{
		loader : 'less-loader',
		options: {
			sourceMap        : false,
			javascriptEnabled: true,
		},
	},
] );

const createBabelRules = () => ( [
	{
		test   : /\.jsx?$/,
		loader : 'babel-loader',
		exclude: /node_modules/,
	},
	{
		test: /\.(jsx|js)$/,
		use : {
			loader : 'babel-loader',
			options: {
				presets: [
					[ '@babel/preset-env', { loose: true, modules: 'commonjs' } ],
				],
				plugins: [ 'transform-es2015-template-literals' ],
			},
		},
		include: new RegExp(
			'/node_modules/(' +
				'|acorn-jsx' +
				'|d3-array' +
				'|debug' +
				'|regexpu-core' +
				'|unicode-match-property-ecmascript' +
				'|unicode-match-property-value-ecmascript)/'
		),
	},
] );

const createPlugins = () => ( [
	new CustomTemplatedPathPlugin( {
		modulename( outputPath, data ) {
			const entryName = get( data, [ 'chunk', 'name' ] );

			if ( entryName ) {
				return toCamelCase( entryName );
			}

			return outputPath;
		},
	} ),
	new MiniCssExtractPlugin( {
		filename     : 'assets/css/[name].css',
		// The query busts CDN and browser caches when a lazily loaded chunk changes.
		chunkFilename: 'assets/css/[name].css?ver=[contenthash:8]',
	} ),
] );

const webpackConfig = {
	mode : NODE_ENV,
	entry: JS_ENTRY_POINTS,
	output: {
		filename     : 'assets/js/[name].js',
		path         : __dirname,
		chunkFilename: 'assets/js/chunks/[name].js?ver=[contenthash:8]',
	},
	externals: createExternals(),
	// Not libraryTarget: 'this', which copied each bundle's non-writable __esModule onto window and broke mediaelement.
	externalsType: 'this',
	module   : {
		rules: [
			...createBabelRules(),
			{
				test: /\.(less|css)$/,
				use : createCssLoaders(),
			},
		],
	},
	resolve: {
		extensions: SOURCE_EXTENSIONS,
		modules   : [
			path.join( __dirname, 'src' ),
			'node_modules',
		],
	},
	plugins: createPlugins(),
	optimization: {
		minimize : IS_PRODUCTION,
		minimizer: [ new TerserPlugin(), new CssMinimizerPlugin() ],
		splitChunks: {
			name: false,
		},
	},
};

if ( ! IS_PRODUCTION ) {
	webpackConfig.devtool = 'inline-source-map';
}

module.exports = webpackConfig;
