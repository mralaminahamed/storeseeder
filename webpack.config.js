const defaultConfig = require("@wordpress/scripts/config/webpack.config");
const path = require("path");

module.exports = {
  ...defaultConfig,
  entry: {
    // Not "admin": wp-cli's i18n scanner treats any file ending in "min.js" as a
    // minified build and rewrites the reference — "build/admin.js" becomes
    // "build/a.js", so the JSON of script translations lands under a hash that
    // WordPress never looks for. See the note in class-storeseeder.php.
    "admin-app": "./src/index.tsx",
  },
  target: ['web', 'es5'],
  performance: {
    hints: false,
    maxEntrypointSize: 512000,
    maxAssetSize: 512000,
  },
  resolve: {
    ...defaultConfig.resolve,
    alias: {
      ...defaultConfig.resolve?.alias,
      "@": path.resolve(__dirname, "src"),
    },
    extensions: [".tsx", ".ts", ".js", ".jsx"],
  },
};
