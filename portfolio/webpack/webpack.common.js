const fs = require('fs');
const ImageminWebpWebpackPlugin= require("imagemin-webp-webpack-plugin");
const MiniCssExtractPlugin = require('mini-css-extract-plugin');
const CopyWebpackPlugin = require('copy-webpack-plugin');
const HtmlWebpackPlugin = require('html-webpack-plugin');
const path = require('path');
const paths = require('./paths');

function generateHtmlPlugins(dir) {
  // Read files in template directory
  const templateFiles = fs.readdirSync(path.resolve(__dirname, dir));

  return templateFiles.map(item => {
    const parts = item.split('.');
    const name = parts[0];
    const extension = parts[1];

    return extension !== 'html' ? false : new HtmlWebpackPlugin({
      filename: item,
      template: path.resolve(__dirname, `${dir}/${name}.${extension}`),
      minify: {
        removeComments: true,
        removeEmptyAttributes: true,
      },
      templateParameters: {
        content: extension === 'json' ? path.resolve(__dirname, `${dir}/${name}.json`) : ''
      }
    });
  });
}

const htmlPlugins = generateHtmlPlugins('../src/template/page');

module.exports = (env, argv) => { return {
  context: paths.src,
  entry: {
    app: `./scripts/index.js`,
  },
  output: {
    filename: `portfolio/build/scripts/[name].js`,
    path: paths.root,
    publicPath: './',
  },
  devServer: {
    allowedHosts: [
      'ykosinets.xyz'
    ]
  },
  module: {
    rules: [
      {
        test: /\.js$/,
        exclude: /node_modules/,
        loader: 'babel-loader',
      },
      {
        test: /\.(css|scss)$/,
        use: [
          MiniCssExtractPlugin.loader,
          {
            loader: 'css-loader',
            options: {
              importLoaders: 2,
              sourceMap: true,
            },
          },
          {
            loader: 'postcss-loader',
            options: {
              plugins: () => [require('autoprefixer'), require('postcss-flexbugs-fixes')],
              sourceMap: true,
            },
          },
          {
            loader: 'sass-loader',
            options: {
              sourceMap: true,
            },
          }
        ],
      },
      {
        test: /\.html$/i,
        loader: 'html-loader',
        options: {
          attributes: true,
          interpolate: true,
          minimize: false,
          exportAsEs6Default: true
        },
      },
      {
        test: /\.(woff2?|eot|ttf|otf|svg)$/,
        exclude: [/images/, /static/, /videos/],
        loader: 'file-loader',
        options: {
          publicPath: '../fonts',
          outputPath: 'portfolio/build/fonts',
          name: '[name].[ext]',
        },
      },
      {
        test: /\.(gif|ico|jpe?g|png|svg|webp)$/,
        exclude: [/fonts/, /static/, /videos/],
        oneOf: [
          {
            issuer: /\.(css|scss)$/,
            loader: 'file-loader',
            options: {
              publicPath: '../images',
              outputPath: 'portfolio/build/images',
              name: '[name].[ext]',
            },
          },
          {
            loader: 'file-loader',
            options: {
              publicPath: './portfolio/build/images',
              outputPath: 'portfolio/build/images',
              name: '[name].[ext]',
            },
          },
        ],
      },
      {
        test: /\.(mp4|mpeg|webm)$/,
        exclude: [/fonts/, /images/],
        loader: 'file-loader',
        options: {
          publicPath: './portfolio/build/videos',
          outputPath: 'portfolio/build/videos',
          name: '[name].[ext]',
        },
      },
    ],
  },
  plugins: [
    new ImageminWebpWebpackPlugin({
      config: [{
        test: /\.(jpe?g|png)/,
        options: {
          publicPath: './portfolio/build/images',
          outputPath: 'portfolio/build/images',
          quality:  100
        }
      }],
      overrideExtension: true,
      detailedLogs: false,
      silent: false,
      strict: true
    }),
    new MiniCssExtractPlugin({
      filename: 'portfolio/build/styles/[name].css',
    })
  ].concat(htmlPlugins)
}};
