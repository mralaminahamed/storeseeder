import { useState } from "@wordpress/element";
import { __ } from "@wordpress/i18n";
import ProductGenerator from "./Generators/ProductGenerator";
import CustomerGenerator from "./Generators/CustomerGenerator";
import OrderGenerator from "./Generators/OrderGenerator";
import CouponGenerator from "./Generators/CouponGenerator";
import LocationGenerator from "./Generators/LocationGenerator";
import ShippingPlanGenerator from "./Generators/ShippingPlanGenerator";
import CartSessionGenerator from "./Generators/CartSessionGenerator";
import TaxClassGenerator from "./Generators/TaxClassGenerator";
import ProductVariationGenerator from "./Generators/ProductVariationGenerator";
import TransactionGenerator from "./Generators/TransactionGenerator";

export default function App() {
  const [activeTab, setActiveTab] = useState("products");

  const tabs = [
    { id: "products", label: __("Products", "fluent-cart-fakerpress"), component: ProductGenerator },
    { id: "customers", label: __("Customers", "fluent-cart-fakerpress"), component: CustomerGenerator },
    { id: "orders", label: __("Orders", "fluent-cart-fakerpress"), component: OrderGenerator },
    { id: "coupons", label: __("Coupons", "fluent-cart-fakerpress"), component: CouponGenerator },
    { id: "locations", label: __("Locations", "fluent-cart-fakerpress"), component: LocationGenerator },
    { id: "shipping", label: __("Shipping Plans", "fluent-cart-fakerpress"), component: ShippingPlanGenerator },
    { id: "cart-sessions", label: __("Cart Sessions", "fluent-cart-fakerpress"), component: CartSessionGenerator },
    { id: "tax-classes", label: __("Tax Classes", "fluent-cart-fakerpress"), component: TaxClassGenerator },
    { id: "variations", label: __("Product Variations", "fluent-cart-fakerpress"), component: ProductVariationGenerator },
    { id: "transactions", label: __("Transactions", "fluent-cart-fakerpress"), component: TransactionGenerator },
  ];

  const ActiveComponent = tabs.find(tab => tab.id === activeTab)?.component;

  return (
    <div className="wrap fluent-cart-fakerpress-admin">
      <h1>{__("Fluent Cart FakerPress", "fluent-cart-fakerpress")}</h1>

      <div className="nav-tab-wrapper">
        {tabs.map(tab => (
          <button
            key={tab.id}
            className={`nav-tab ${activeTab === tab.id ? "nav-tab-active" : ""}`}
            onClick={() => setActiveTab(tab.id)}
          >
            {tab.label}
          </button>
        ))}
      </div>

      <div className="tab-content mt-6">
        {ActiveComponent && <ActiveComponent />}
      </div>
    </div>
  );
}
