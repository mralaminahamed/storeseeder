/**
 * Stylesheets imported for their side effect on the bundle.
 *
 * webpack turns these into emitted CSS; TypeScript only needs to know the module exists.
 * Required since TypeScript 6 under `moduleResolution: "bundler"`, which stopped silently
 * allowing an untyped side-effect import.
 */
declare module "*.css";
declare module "*.scss";
