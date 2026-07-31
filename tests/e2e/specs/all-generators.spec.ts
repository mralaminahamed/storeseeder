import { test, expect } from '@playwright/test';

const PLUGIN_URL = '/wp-admin/admin.php?page=storeseeder';

const GENERATORS = [
  { route: "products",             name: 'Products' },
  { route: "customers",            name: 'Customers' },
  { route: "orders",               name: 'Orders' },
  { route: "coupons",              name: 'Coupons' },
  { route: "product-variations",   name: 'Product Variations' },
  { route: "shipping-plans",       name: 'Shipping Plans' },
  { route: "tax_classes",          name: 'Tax Classes' },
  { route: "transactions",         name: 'Transactions' },
  { route: "cart-sessions",        name: 'Cart Sessions' },
  { route: "attributes",           name: 'Product Attributes' },
  { route: "product_categories",   name: 'Product Categories' },
  { route: "brands",               name: 'Product Brands' },
  { route: "product_tags",         name: 'Product Tags' },
  { route: "refunds",              name: 'Refunds' },
  { route: "logs",                 name: 'Logs' },
  { route: "shipping_classes",     name: 'Shipping Classes' },
  { route: "labels",               name: 'Labels' },
  { route: "order_tax_rates",      name: 'Order Tax Lines' },
  { route: "product_downloads",    name: 'Product Downloads' },
  { route: "subscriptions",        name: 'Subscriptions' },
  { route: "licenses",             name: 'Licenses' },
] as const;

for (const { route, name } of GENERATORS) {
  test.describe(`${name} generator`, () => {
    test.beforeEach(async ({ page }) => {
      await page.goto(`${PLUGIN_URL}#/generator/${route}`, { waitUntil: 'domcontentloaded' });
      await page.getByTestId('generator-runbar').waitFor({ timeout: 15_000 });
    });

    test('page loads without error boundary', async ({ page }) => {
      await expect(page.getByText('Something went wrong')).toBeHidden();
    });

    test('topbar shows generator name', async ({ page }) => {
      await expect(page.getByTestId('topbar')).toContainText(name);
    });

    test('config column shows generator name', async ({ page }) => {
      await expect(page.locator('.fp-config-col')).toContainText(name);
    });

    test('generator runbar is visible', async ({ page }) => {
      await expect(page.getByTestId('generator-runbar')).toBeVisible();
    });

    test('preview table renders', async ({ page }) => {
      await expect(page.getByTestId('preview-table')).toBeVisible();
    });

    test('generate-btn is present and enabled', async ({ page }) => {
      await expect(page.getByTestId('generate-btn')).toBeVisible();
      await expect(page.getByTestId('generate-btn')).toBeEnabled();
    });
  });
}
