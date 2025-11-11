import { render } from "@wordpress/element";
import App from "./components/App";

const container = document.getElementById("fluent-cart-fakerpress-admin");

if (container) {
  render(<App />, container);
}
