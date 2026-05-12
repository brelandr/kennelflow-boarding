/**
 * Mobile Report Card PWA bundle (React).
 *
 * @package KennelPress
 */

const path = require( 'path' );

module.exports = {
	mode: process.env.NODE_ENV === 'production' ? 'production' : 'development',
	entry: './src/pwa-report-card.js',
	output: {
		path: path.resolve( __dirname, 'build' ),
		filename: 'pwa-report-card.js',
	},
	module: {
		rules: [
			{
				test: /\.jsx?$/,
				exclude: /node_modules/,
				use: {
					loader: 'babel-loader',
					options: {
						presets: [
							[ '@babel/preset-env', { targets: 'defaults' } ],
							[ '@babel/preset-react', { runtime: 'automatic' } ],
						],
					},
				},
			},
			{
				test: /\.css$/,
				use: [ 'style-loader', 'css-loader' ],
			},
		],
	},
	resolve: {
		extensions: [ '.js', '.jsx' ],
	},
	performance: {
		hints: false,
	},
	devtool: 'production' === process.env.NODE_ENV ? false : 'eval-source-map',
};
