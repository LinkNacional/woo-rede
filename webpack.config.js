const path = require('path');

module.exports = {
  mode: 'production',
  entry: {
    creditCardMaxipago: './Public/js/creditCard/maxipago/lknIntegrationMaxipagoForWoocommerceCheckout.js',
    debitCardMaxipago: './Public/js/debitCard/maxipago/lknIntegrationMaxipagoForWoocommerceCheckout.js',
    creditCardRede: './Public/js/creditCard/rede/lknIntegrationRedeForWoocommerceCheckout.js',
    debitCardRede: './Public/js/debitCard/rede/lknIntegrationRedeForWoocommerceCheckout.js',
    lknRedeAnalytics: './Admin/js/analytics/lknRedeAnalytics.tsx',
    // JS do layout compacto (checkout clássico/shortcode): bandeiras + ícones.
    wooRedeDebitCompact: './Public/js/debitCard/rede/wooRedeDebitCompact.js'
  },
  output: {
    filename: (pathData) => {
      const name = pathData.chunk.name;
      const entryPath = module.exports.entry[name];
      
      // Para o arquivo analytics, compilar diretamente na pasta Admin
      if (name === 'lknRedeAnalytics') {
        return 'Admin/js/analytics/lknRedeAnalyticsCompiled.js';
      }
      
      // Para outros arquivos, manter lógica original
      const baseName = path.basename(entryPath, path.extname(entryPath));
      return entryPath.replace(baseName, `${baseName}Compiled`);
    },
    path: path.resolve(__dirname)
  },
  externals: {
    '@wordpress/i18n': 'wp.i18n',
    '@wordpress/hooks': 'wp.hooks',
    '@wordpress/element': 'wp.element',
    '@woocommerce/components': 'wc.components',
    'react': 'React',
    'react-dom': 'ReactDOM'
  },
  resolve: {
    extensions: ['.tsx', '.ts', '.js', '.jsx']
  },
  module: {
    rules: [
      {
        test: /\.(js|jsx)$/,
        exclude: /node_modules/,
        use: {
          loader: 'babel-loader',
          options: {
            presets: ['@babel/preset-env', '@babel/preset-react']
          }
        }
      },
      {
        test: /\.(ts|tsx)$/,
        exclude: /node_modules/,
        use: 'ts-loader'
      },
      {
        test: /\.css$/,
        use: ['style-loader', 'css-loader']
      }
    ]
  }
};