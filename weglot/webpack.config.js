const path = require("path");
const MiniCssExtractPlugin = require("mini-css-extract-plugin");
const CopyWebpackPlugin = require("copy-webpack-plugin");
const webpack = require("webpack");
const sass = require("sass");
const env = process.env.NODE_ENV || "development";

module.exports = {
	entry: {
		"front-js": "./app/javascripts/front.js",
		"admin-js": "./app/javascripts/index.js",
		"front-css": "./app/styles/index.scss",
		"new-flags": "./app/styles/new-flags.scss",
		"admin-css": "./app/styles/admin.scss",
		"front-amp-css": "./app/styles/amp.scss",
		"nav-js" : "./app/javascripts/nav.js"
	},
	output: {
		path: __dirname + "/dist",
		publicPath: "/dist/"
	},
	mode: env,
	module: {
		rules: [
			{
				test: /\.(js|jsx)$/,
				exclude: /node_modules/,
				include: path.join(__dirname, "node_modules"),
				use: "babel-loader"
			},
			{
				test: /\.(gif|jpe?g|png)$/,
				loader: "url-loader",
				options: {
					limit: 10000,
					name: "images/[name].[ext]"
				}
			},
			{
				test: /\.scss$/,
				use: [
					MiniCssExtractPlugin.loader,
					{
						loader: "css-loader",
						options: {
							url: false
						}
					},
					{
						loader: "postcss-loader"
					},
					{
						loader: "sass-loader",
						options: {
							implementation: sass,
							sassOptions: {
								api: "modern"
							}
						}
					}
				]
			}
		]
	},
	plugins: [
		new webpack.DefinePlugin({
			NODE_ENV: env
		}),
		new MiniCssExtractPlugin({
			filename: "css/[name].css"
		}),
		new CopyWebpackPlugin({
			patterns: [
				{ from: "app/images", to: "images" },
				{ from: "app/static", to: "images" },
				{ from: "app/javascripts/selectize.js", to: "selectize.js" }
			]
		})
	]
};
