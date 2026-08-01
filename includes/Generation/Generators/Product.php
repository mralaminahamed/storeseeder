<?php
/**
 * Product Generator Class for StoreSeeder Plugin
 *
 * @since      1.0.0
 * @subpackage Generators
 * @package    StoreSeeder\Generation\Generators
 */

namespace StoreSeeder\Generation\Generators;

use StoreSeeder\Generation\Generator;
use StoreSeeder\Platforms\Status;

defined( 'ABSPATH' ) || exit;

/**
 * Product Generator Class
 *
 * Shapes realistic product data. Persisting it is a platform writer's job — see
 * StoreSeeder\Platforms\Drivers\Fluent_Cart\Writers\Product.
 */
class Product extends Generator {


	/**
	 * Get the resource type name
	 *
	 * @return string Resource type name.
	 */
	protected function get_resource_type(): string {
		return 'product';
	}

	/**
	 * Get supported data types for this generator.
	 *
	 * @return array Supported types
	 */
	public function get_supported_types(): array {
		return array(
			'products' => 'Fluent Cart Products',
		);
	}

	/**
	 * Get generator description.
	 *
	 * @return string Description
	 */
	public function get_description(): string {
		return 'Generates comprehensive product data with pricing, inventory, categories, and attributes for testing Fluent Cart functionality.';
	}

	/**
	 * A product's name, from the active vocabulary.
	 *
	 * Shared with the preview row, which used to draw its own from `words( 3 )` — Lorem, on the one
	 * screen whose job is showing what a run will do. One method so the two cannot say different
	 * things about the same product again.
	 *
	 * @since 1.2.0
	 *
	 * @return string
	 */
	private function title(): string {
		$sample_data = $this->load_sample_data();
		$adjectives  = $sample_data['adjectives'] ?? array( 'Amazing', 'Premium', 'Deluxe', 'Professional' );
		$products    = $sample_data['products'] ?? array( 'Widget', 'Gadget', 'Tool', 'Device', 'System' );

		return $this->get_faker()->randomElement( $adjectives ) . ' ' . $this->get_faker()->randomElement( $products );
	}

	/**
	 * The words a product is named from.
	 *
	 * This override was missing, and its absence is why every product in every locale was called
	 * "Premium Widget". `build_entity()` has always asked for `adjectives` and `products` and always
	 * fallen through to the four-and-five inline literals beside the request, because the abstract's
	 * `load_sample_data()` returns an empty array and nothing here replaced it. The sample-data
	 * repository has shipped `products/<locale>/product_names.json` the whole time, read by nobody.
	 *
	 * It matters more now than it did: product names are the most visible thing a recipe changes, so
	 * a grocer whose catalogue says "Deluxe Gadget" is a recipe that did nothing anyone can see.
	 *
	 * Cast because `load_json_file()` answers null on a miss, and the two `??` in `build_entity()`
	 * already handle a file that is present but missing a list.
	 *
	 * @since 1.2.0
	 *
	 * @return array<string, mixed>
	 */
	protected function load_sample_data(): array {
		return (array) $this->load_json_file( $this->get_sample_data_path( 'products', 'product_names' ) );
	}

	/**
	 * Build a canonical product
	 *
	 * FakerPHP and sample data only. The SKU here is a *candidate*: the unique index
	 * that makes it matter lives in the platform, so the writer is what checks it and
	 * re-rolls on a collision.
	 *
	 * @since 1.1.0
	 *
	 * @return array<string, mixed> Canonical product entity.
	 */
	protected function build_entity() {
		$title = $this->title();

		$fulfillment_type = $this->get_faker()->randomElement( array( 'physical', 'digital' ) );
		$price            = $this->price();
		$manage_stock     = $this->manage_stock();

		return array(
			'title'             => $title,
			'slug'              => sanitize_title( $title ),
			'description'       => $this->description(),
			'short_description' => $this->get_faker()->sentence( 12 ),
			// Integer minor units. Fluent Cart stores cents directly; platforms that
			// want decimals divide in their own writer.
			'price'             => $price,
			// A second price, which every platform has and calls something different:
			// WooCommerce a sale price, Fluent Cart a compare-at price. Null on most
			// products, because a catalogue where everything is discounted tests nothing.
			'sale_price'        => $this->sale_price( $price ),
			// What the shop paid. Null unless asked for, since a store that does not track
			// cost of goods is the common case.
			'cost'              => $this->cost( $price ),
			'status'            => Status::PUBLISHED,
			'sku'               => strtoupper( $this->get_faker()->bothify( '??-#####' ) ),
			'stock'             => $manage_stock ? $this->stock() : null,
			'manage_stock'      => $manage_stock,
			// Both platforms store all three, with the same meaning.
			'backorders'        => $this->get_faker()->randomElement( array( 'no', 'no', 'notify', 'yes' ) ),
			'sold_individually' => $this->get_faker()->boolean( 15 ),
			'fulfillment_type'  => $fulfillment_type,
			// How many existing categories to file it under. The terms themselves belong to
			// the Product Categories generator — this only says how many to draw, because
			// which ones exist is something only the platform knows.
			'category_count'    => $this->categories_per_product(),
		);
	}

	/**
	 * The price, in integer minor units, within the requested range.
	 *
	 * `price_range` has been a declared parameter since the beginning and was read by nothing:
	 * the admin offered a min and a max and every product came out between 9.99 and 999.99. It is
	 * honoured here, which is the whole point of a parameter existing.
	 *
	 * @since 1.1.0
	 *
	 * @return int
	 */
	private function price(): int {
		$range = (array) ( $this->generation_params['price_range'] ?? array() );
		$min   = isset( $range['min'] ) ? (float) $range['min'] : 9.99;
		$max   = isset( $range['max'] ) ? (float) $range['max'] : 999.99;

		// A max below the min is a request nobody meant; the wider of the two wins rather than
		// FakerPHP throwing.
		if ( $max < $min ) {
			$max = $min;
		}

		return (int) round( $this->get_faker()->randomFloat( 2, max( 0, $min ), $max ) * 100 );
	}

	/**
	 * A sale price below the regular one, on a minority of products.
	 *
	 * @since 1.1.0
	 *
	 * @param int $price The regular price, in minor units.
	 *
	 * @return int|null
	 */
	private function sale_price( int $price ) {
		if ( ! $this->get_faker()->boolean( 30 ) || $price < 200 ) {
			return null;
		}

		// Between 10% and 40% off, rounded to the minor unit — a discount, not an arbitrary
		// smaller number, so percentage-based reporting has something sensible to read.
		$off = $this->get_faker()->numberBetween( 10, 40 );

		return (int) round( $price * ( 100 - $off ) / 100 );
	}

	/**
	 * What the shop paid for the product, when cost tracking is asked for.
	 *
	 * @since 1.1.0
	 *
	 * @param int $price The regular price, in minor units.
	 *
	 * @return int|null
	 */
	private function cost( int $price ) {
		if ( empty( $this->generation_params['track_cost'] ) ) {
			return null;
		}

		// A margin between 25% and 70%, which is the range a real catalogue spans.
		$margin = $this->get_faker()->numberBetween( 25, 70 );

		return (int) round( $price * ( 100 - $margin ) / 100 );
	}

	/**
	 * Whether this product tracks stock.
	 *
	 * @since 1.1.0
	 *
	 * @return bool
	 */
	private function manage_stock(): bool {
		$inventory = (array) ( $this->generation_params['inventory'] ?? array() );

		return ! isset( $inventory['manage_stock'] ) || (bool) $inventory['manage_stock'];
	}

	/**
	 * Stock level, within the requested range.
	 *
	 * @since 1.1.0
	 *
	 * @return int
	 */
	private function stock(): int {
		$inventory = (array) ( $this->generation_params['inventory'] ?? array() );
		$range     = (array) ( $inventory['stock_range'] ?? array() );
		$min       = isset( $range['min'] ) ? (int) $range['min'] : 0;
		$max       = isset( $range['max'] ) ? (int) $range['max'] : 100;

		if ( $max < $min ) {
			$max = $min;
		}

		return $this->get_faker()->numberBetween( max( 0, $min ), $max );
	}

	/**
	 * A description of the requested length.
	 *
	 * @since 1.1.0
	 *
	 * @return string
	 */
	private function description(): string {
		$options = (array) ( $this->generation_params['content_options'] ?? array() );
		$length  = (string) ( $options['description_length'] ?? 'medium' );

		$paragraphs = array(
			'short'  => 1,
			'medium' => 3,
			'long'   => 6,
		);

		// paragraphs() is typed array|string because its second argument decides which; `true`
		// always yields a string, and implode covers the analyser rather than a bare cast.
		$text = $this->get_faker()->paragraphs( $paragraphs[ $length ] ?? 3, true );

		return is_array( $text ) ? implode( "\n\n", $text ) : (string) $text;
	}

	/**
	 * How many existing categories to file each product under.
	 *
	 * @since 1.1.0
	 *
	 * @return int
	 */
	private function categories_per_product(): int {
		$categories = (array) ( $this->generation_params['categories'] ?? array() );
		$max        = isset( $categories['max_per_product'] ) ? (int) $categories['max_per_product'] : 3;

		return $this->get_faker()->numberBetween( 0, max( 0, min( 10, $max ) ) );
	}

	/**
	 * Preview columns for products
	 *
	 * @since 1.0.1
	 *
	 * @return array<int, array{key: string, label: string}>
	 */
	protected function get_preview_columns(): array {
		return array(
			array(
				'key'   => 'name',
				'label' => __( 'Name', 'storeseeder' ),
			),
			array(
				'key'   => 'sku',
				'label' => __( 'SKU', 'storeseeder' ),
			),
			array(
				'key'   => 'type',
				'label' => __( 'Type', 'storeseeder' ),
			),
			array(
				'key'   => 'price',
				'label' => __( 'Price', 'storeseeder' ),
			),
			array(
				'key'   => 'stock',
				'label' => __( 'Stock', 'storeseeder' ),
			),
			array(
				'key'   => 'status',
				'label' => __( 'Status', 'storeseeder' ),
			),
		);
	}

	/**
	 * The product type a previewed row would be created as.
	 *
	 * Reads the run's own `product_type` parameter rather than rolling a fresh value, so the
	 * preview answers the question the control next to it just asked: choose `digital` and
	 * every row says digital. `mixed` is the only case that varies per row, which is what
	 * mixed means.
	 *
	 * @since 1.1.0
	 *
	 * @return string
	 */
	private function preview_product_type(): string {
		$requested = $this->generation_params['product_type'] ?? 'mixed';

		if ( 'physical' === $requested || 'digital' === $requested ) {
			return $requested;
		}

		return $this->get_faker()->randomElement( array( 'physical', 'digital' ) );
	}

	/**
	 * Build a product preview row
	 *
	 * @since 1.0.1
	 *
	 * @return array<string, array{v: mixed, kind: string}>
	 */
	protected function build_preview_row(): array {
		$faker = $this->get_faker();

		return array(
			'name'   => array(
				// words() without the $asText flag returns an array, which joins
				// cleanly — asking for the string form types as array|string.
				// `title()`, the same path `build_entity()` names a product with. This was
				// `words( 3 )` — Lorem — so the preview said "Voluptatem Quia Dolor" while the run
				// produced "Organic Rolled Oats", and the one screen whose job is showing what a
				// recipe does showed the opposite of it.
				'v'    => $this->title(),
				'kind' => 'text',
			),
			'sku'    => array(
				'v'    => 'SKU-' . $faker->numberBetween( 1000, 9999 ),
				'kind' => 'mono',
			),
			'type'   => array(
				'v'    => $this->preview_product_type(),
				'kind' => 'text',
			),
			'price'  => array(
				// `price()`, not a fresh literal range. This row hardcoded 5–500 and so showed a
				// price unrelated to `price_range` on every preview — the parameter appeared to do
				// nothing, which is the one thing a preview must not say about a parameter that
				// works. Divided by a hundred because `price()` answers in minor units.
				'v'    => '$' . number_format( $this->price() / 100, 2 ),
				'kind' => 'money',
			),
			'stock'  => array(
				'v'    => $this->stock(),
				'kind' => 'num',
			),
			'status' => array(
				'v'    => $faker->randomElement( array( 'publish', 'draft', 'pending' ) ),
				'kind' => 'status',
			),
		);
	}
}
