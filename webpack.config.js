const defaultConfig = require("@wordpress/scripts/config/webpack.config");
const path = require("path");

module.exports = {
  ...defaultConfig,
  entry: {
    admin: "./src/admin/index.tsx",
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
