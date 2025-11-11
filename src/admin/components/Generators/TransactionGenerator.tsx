import { __ } from "@wordpress/i18n";
import GeneratorBase from "../GeneratorBase";

export default function TransactionGenerator() {
  const parameterConfig = {
    payment_methods: {
      description: __("Payment methods to generate transactions for", "fluent-cart-fakerpress"),
      type: "array" as const,
      items: {
        type: "string" as const,
        enum: ["stripe", "paypal", "cod", "bank_transfer", "check", "credit_card", "debit_card"],
      },
      default: ["stripe", "paypal", "cod"],
    },
    transaction_statuses: {
      description: __("Transaction statuses to generate", "fluent-cart-fakerpress"),
      type: "array" as const,
      items: {
        type: "string" as const,
        enum: ["completed", "pending", "failed", "cancelled", "refunded", "partially_refunded"],
      },
      default: ["completed", "pending", "failed"],
    },
    amount_range: {
      description: __("Transaction amount range", "fluent-cart-fakerpress"),
      type: "object" as const,
      properties: {
        min: {
          type: "number" as const,
          description: __("Minimum transaction amount", "fluent-cart-fakerpress"),
          minimum: 0.01,
          default: 10,
        },
        max: {
          type: "number" as const,
          description: __("Maximum transaction amount", "fluent-cart-fakerpress"),
          minimum: 0.01,
          default: 1000,
        },
      },
    },
    include_refunds: {
      description: __("Include refund transactions", "fluent-cart-fakerpress"),
      type: "boolean" as const,
      default: true,
    },
    refund_percentage: {
      description: __("Percentage of transactions that should be refunds", "fluent-cart-fakerpress"),
      type: "number" as const,
      minimum: 0,
      maximum: 50,
      default: 5,
    },
    include_gateway_metadata: {
      description: __("Include payment gateway-specific metadata", "fluent-cart-fakerpress"),
      type: "boolean" as const,
      default: true,
    },
    associate_with_orders: {
      description: __("Associate transactions with existing orders", "fluent-cart-fakerpress"),
      type: "boolean" as const,
      default: true,
    },
  };

  return (
    <GeneratorBase
      title={__("Generate Transactions", "fluent-cart-fakerpress")}
      description={__(
        "Create payment transactions with various methods, statuses, and gateway metadata for testing Fluent Cart payment processing and transaction management.",
        "fluent-cart-fakerpress",
      )}
      apiEndpoint="/fluent-cart-fakerpress/v1/transactions/generate"
      parameterConfig={parameterConfig}
    />
  );
}