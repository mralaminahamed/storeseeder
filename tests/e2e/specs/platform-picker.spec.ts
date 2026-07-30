import { test, expect } from '@playwright/test';
import { existsSync, mkdirSync, unlinkSync, writeFileSync } from 'fs';
import { join } from 'path';

/**
 * The target platform picker.
 *
 * Most of these only mean anything with more than one platform active, which a
 * plain dev install will not have. Rather than requiring a second e-commerce
 * plugin to be installed, they register a stub driver through the public
 * `storeseeder_platforms` filter — the same seam a third party would use — via a
 * dropped mu-plugin. That makes the multi-platform paths reachable anywhere, and
 * incidentally proves the extension point works from outside the plugin.
 *
 * Needs write access to wp-content, so the multi-platform group skips unless
 * STORESEEDER_E2E_MU_DIR names the mu-plugins directory.
 */

const PLUGIN_URL = '/wp-admin/admin.php?page=storeseeder';
const MU_DIR = process.env.STORESEEDER_E2E_MU_DIR;

const STUB_MU_PLUGIN = `<?php
add_filter( 'storeseeder_platforms', static function ( array $platforms ): array {
	if ( ! class_exists( '\\\\StoreSeeder\\\\Abstracts\\\\Platform_Driver' ) ) {
		return $platforms;
	}
	$platforms[] = new class() extends \\StoreSeeder\\Abstracts\\Platform_Driver {
		public function id(): string { return 'stub-cart'; }
		public function label(): string { return 'Stub Cart'; }
		public function is_active(): bool { return true; }
		public function version(): ?string { return '2.0.0'; }
		protected function capabilities(): array {
			$matrix = array();
			foreach ( \\StoreSeeder\\Platform\\Resource::all() as $r ) { $matrix[ $r ] = true; }
			$matrix[ \\StoreSeeder\\Platform\\Resource::SUBSCRIPTION ] =
				\\StoreSeeder\\Platform\\Capability::missing_extension( 'stub-subs', 'Stub Subscriptions' );
			return $matrix;
		}
		protected function writer_classes(): array { return array(); }
	};
	return $platforms;
} );
`;

test.describe('Target platform picker', () => {
  test.describe('single platform', () => {
    test.beforeEach(async ({ page }) => {
      await page.goto(PLUGIN_URL);
      await page.getByTestId('app-shell').waitFor();
    });

    /**
     * A select offering one option implies a decision the user does not have, so
     * the control is absent rather than disabled.
     */
    test('the picker is hidden when only one platform is active', async ({ page }) => {
      await expect(page.getByTestId('platform-select')).toBeHidden();
    });

    test('no target prompt blocks the generator page', async ({ page }) => {
      await page.getByTestId('gen-card-products').click();
      await page.getByTestId('generator-runbar').waitFor();

      await expect(page.getByTestId('target-prompt')).toBeHidden();
      await expect(page.getByTestId('generate-btn')).toBeEnabled();
    });
  });

  test.describe('several platforms', () => {
    let stubPath: string | null = null;

    test.beforeAll(() => {
      test.skip(!MU_DIR, 'Set STORESEEDER_E2E_MU_DIR to the wp-content/mu-plugins directory.');
      mkdirSync(MU_DIR as string, { recursive: true });
      stubPath = join(MU_DIR as string, 'zz-storeseeder-e2e-stub-platform.php');
      writeFileSync(stubPath, STUB_MU_PLUGIN);
    });

    test.afterAll(() => {
      if (stubPath && existsSync(stubPath)) unlinkSync(stubPath);
    });

    test.beforeEach(async ({ page }) => {
      await page.goto(PLUGIN_URL);
      await page.getByTestId('app-shell').waitFor();
    });

    test('the picker appears and defaults to Auto', async ({ page }) => {
      const select = page.getByTestId('platform-select');
      await expect(select).toBeVisible();
      await expect(select.locator('select')).toHaveValue('auto');
    });

    /**
     * Auto has to name what it resolved to. "Auto" on its own says nothing about
     * where the rows are going, which is the only question this control answers.
     */
    test('Auto names the platform it resolved to, or flags that it cannot', async ({ page }) => {
      const select = page.getByTestId('platform-select').locator('select');
      const autoLabel = await select.locator('option[value="auto"]').textContent();

      // With two active and nothing stored, resolution is ambiguous, so Auto stands
      // alone; once a target is chosen it names it.
      expect(autoLabel).toMatch(/^Auto( · .+)?$/);
    });

    test('both platforms are offered', async ({ page }) => {
      const select = page.getByTestId('platform-select').locator('select');
      await expect(select.locator('option')).toContainText(['Auto', 'Fluent Cart', 'Stub Cart']);
    });

    test('the generator page demands a target before it will run', async ({ page }) => {
      await page.getByTestId('gen-card-products').click();
      await page.getByTestId('generator-runbar').waitFor();

      await expect(page.getByTestId('target-prompt')).toBeVisible();
      await expect(page.getByTestId('generate-btn')).toBeDisabled();
      // Queueing is blocked too: a batched run would fail later, further from the
      // explanation.
      await expect(page.getByTestId('add-to-batch')).toBeDisabled();
    });

    test('choosing a target from the prompt unblocks the run', async ({ page }) => {
      await page.getByTestId('gen-card-products').click();
      await page.getByTestId('generator-runbar').waitFor();

      await page.getByTestId('target-choice-fluent-cart').click();

      await expect(page.getByTestId('target-prompt')).toBeHidden();
      await expect(page.getByTestId('generate-btn')).toBeEnabled();
    });

    test('the chosen target survives a reload', async ({ page }) => {
      await page.getByTestId('platform-select').locator('select').selectOption('fluent-cart');

      // The write is a REST round-trip to a site option, so give it a moment before
      // reloading — a reload that races the request would prove nothing.
      await expect(page.getByTestId('platform-select').locator('select')).toHaveValue('fluent-cart');

      await page.reload();
      await page.getByTestId('app-shell').waitFor();

      await expect(page.getByTestId('platform-select').locator('select')).toHaveValue('fluent-cart');
    });

    test('a resource the target cannot generate is dimmed and explained', async ({ page }) => {
      await page.getByTestId('platform-select').locator('select').selectOption('stub-cart');
      await expect(page.getByTestId('platform-select').locator('select')).toHaveValue('stub-cart');

      const card = page.getByTestId('gen-card-subscriptions');
      await expect(card).toHaveAttribute('data-unavailable', 'true');
      await expect(card).toContainText('Unavailable here');

      // Still navigable, and the reason names the plugin that would enable it.
      await card.click();
      await page.getByTestId('generator-runbar').waitFor();
      await expect(page.getByTestId('unsupported-notice')).toContainText('Stub Subscriptions');
      await expect(page.getByTestId('generate-btn')).toBeDisabled();
    });
  });
});
