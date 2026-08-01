<?php
/**
 * Customer Generator.
 *
 * @since   1.0.0
 * @package StoreSeeder\Generation\Generators
 */

namespace StoreSeeder\Generation\Generators;

use StoreSeeder\Generation\Generator;

defined( 'ABSPATH' ) || exit;

/**
 * Customer Generator Class
 *
 * Shapes realistic customer profiles: names, localised addresses, preferences and
 * purchase history. Persisting them, and reconciling with any existing WordPress user,
 * is a platform writer's job.
 *
 * This is the generator where the split pays off most: of its nine hundred lines, only
 * a handful ever touched a platform, and the locale-aware address, phone and postcode
 * logic below now serves every platform unchanged.
 *
 * @since 1.0.0
 */
class Customer extends Generator {


	/**
	 * Get the resource type name
	 *
	 * @since 1.0.0
	 *
	 * @return string Resource type name.
	 */
	protected function get_resource_type(): string {
		return 'customer';
	}

	/**
	 * Load sample data for the current locale
	 *
	 * Loads locale-specific sample data for customer generation including
	 * countries, languages, currencies, categories, tags, phone patterns,
	 * states/provinces, and postcode patterns.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed> Sample data arrays for customer generation.
	 */
	protected function load_sample_data(): array {
		return array(
			'countries'            => $this->load_json_file( $this->get_sample_data_path( 'customers', 'countries' ) ) ?? array(),
			'preferred_languages'  => $this->load_json_file( $this->get_sample_data_path( 'customers', 'preferred_languages' ) ) ?? array(),
			'currencies'           => $this->load_json_file( $this->get_sample_data_path( 'customers', 'currencies' ) ) ?? array(),
			'preferred_categories' => $this->load_json_file( $this->get_sample_data_path( 'customers', 'preferred_categories' ) ) ?? array(),
			'customer_tags'        => $this->load_json_file( $this->get_sample_data_path( 'customers', 'customer_tags' ) ) ?? array(),
			'phone_patterns'       => $this->load_json_file( $this->get_sample_data_path( 'customers', 'phone_patterns' ) ) ?? array(),
			'states_provinces'     => $this->load_json_file( $this->get_sample_data_path( 'customers', 'states_provinces' ) ) ?? array(),
			'postcode_patterns'    => $this->load_json_file( $this->get_sample_data_path( 'customers', 'postcode_patterns' ) ) ?? array(),
		);
	}

	/**
	 * Get supported data types for this generator.
	 *
	 * @return array Supported types
	 */
	public function get_supported_types(): array {
		return array(
			'customers' => __( 'Customer Profiles with Addresses and Metadata', 'storeseeder' ),
		);
	}

	/**
	 * Get generator description.
	 *
	 * @return string Description
	 */
	public function get_description(): string {
		return __( 'Generates realistic customer profiles with comprehensive personal information, billing/shipping addresses, preferences, purchase history, loyalty tiers, and engagement metrics for testing customer management systems.', 'storeseeder' );
	}

	/**
	 * Build a canonical customer
	 *
	 * @since 1.1.0
	 *
	 * @return array<string, mixed> Canonical customer entity.
	 */
	protected function build_entity() {
		$first_name = $this->get_faker()->firstName();
		$last_name  = $this->get_faker()->lastName();
		$email      = $this->get_faker()->unique()->safeEmail();
		$full_name  = $first_name . ' ' . $last_name;
		$type       = $this->customer_type();

		// Generate comprehensive customer data.
		$billing_address  = $this->generate_billing_address( $first_name, $last_name, $email );
		$shipping_address = $this->generate_shipping_address( $first_name, $last_name, $billing_address['country'] );
		$customer_meta    = $this->generate_customer_meta( $type );

		// A wholesale buyer is a business, so the company is not optional for one; the address
		// builder leaves it to chance for everybody else.
		if ( 'wholesale' === $type && empty( $billing_address['company'] ) ) {
			$billing_address['company'] = $this->get_faker()->company();
		}

		/**
		 * Filters the customer data before creating the customer.
		 *
		 * Allows developers to modify customer data, addresses, and metadata
		 * before the customer is created in the database.
		 *
		 * @since 1.0.0
		 * @hook  storeseeder_customer_data_before_create
		 *
		 * @param array $customer_data {
		 *     Customer data array.
		 *
		 *     @type string $first_name       Customer first name.
		 *     @type string $last_name        Customer last name.
		 *     @type string $email            Customer email.
		 *     @type string $full_name        Customer full name.
		 *     @type array  $billing_address  Billing address data.
		 *     @type array  $shipping_address Shipping address data.
		 *     @type array  $customer_meta    Customer metadata.
		 * }
		 */
		$customer_data = apply_filters(
			'storeseeder_customer_data_before_create',
			array(
				'first_name'       => $first_name,
				'last_name'        => $last_name,
				'email'            => $email,
				'full_name'        => $full_name,
				'billing_address'  => $billing_address,
				'shipping_address' => $shipping_address,
				'customer_meta'    => $customer_meta,
			)
		);

		// Extract filtered data.
		$first_name       = $customer_data['first_name'];
		$last_name        = $customer_data['last_name'];
		$email            = $customer_data['email'];
		$full_name        = $customer_data['full_name'];
		$billing_address  = $customer_data['billing_address'];
		$shipping_address = $customer_data['shipping_address'];
		$customer_meta    = $customer_data['customer_meta'];

		return array(
			'first_name'       => $first_name,
			'last_name'        => $last_name,
			'full_name'        => $full_name,
			'email'            => $email,
			'billing_address'  => $billing_address,
			'shipping_address' => $shipping_address,
			'meta'             => $customer_meta,
			'notes'            => $this->get_faker()->sentence( 3, true ),
			// The segment this customer belongs to, which is what the writers read to decide
			// between an account and a guest record.
			'customer_type'    => $type,
			// A guest holds no account by definition; everybody else might. The writer reconciles
			// that with whatever user records the platform already has.
			'with_account'     => 'guest' !== $type && $this->get_faker()->boolean( 30 ),
			// When the record itself was created. `customer_since` was metadata on a row created
			// today, so a customer "since 2021" registered this morning — and every
			// cohort or retention report read one or the other and disagreed.
			'date_created'     => $customer_meta['customer_since'],
			// What the lifetime figures are denominated in. Fluent Cart keys its purchase totals
			// by currency, so a writer needs to be told rather than assuming.
			'currency'         => 'USD',
			// A base for the account name; the writer disambiguates it, since only
			// the site knows which usernames are taken.
			'username_base'    => strtolower( $first_name . '.' . $last_name ),
		);
	}

	/**
	 * Generate comprehensive billing address
	 *
	 * @since 1.0.0
	 *
	 * @param string $first_name First name.
	 * @param string $last_name  Last name.
	 * @param string $email      Email address.
	 *
	 * @return array Billing address data.
	 */
	private function generate_billing_address( string $first_name, string $last_name, string $email ): array {
		$country = $this->random_country_code();

		return array(
			'first_name' => $first_name,
			'last_name'  => $last_name,
			'email'      => $email,
			// `contact_preferences.phone_numbers` off leaves the field empty rather than absent, so
			// every writer keeps the same shape.
			'phone'      => $this->contact_switch( 'phone_numbers', true ) ? $this->generate_phone_number( $country ) : '',
			'company'    => $this->get_faker()->optional( 0.25 )->company(),
			'address_1'  => $this->get_faker()->streetAddress(),
			'address_2'  => $this->get_faker()->optional( 0.35 )->secondaryAddress(),
			'city'       => $this->get_faker()->city(),
			'state'      => $this->generate_state( $country ),
			'country'    => $country,
			'postcode'   => $this->generate_postcode( $country ),
		);
	}

	/**
	 * Generate shipping address
	 *
	 * @since 1.0.0
	 *
	 * @param string $first_name      First name.
	 * @param string $last_name       Last name.
	 * @param string $billing_country Billing country code for consistency.
	 *
	 * @return array Shipping address data (empty if same as billing).
	 */
	private function generate_shipping_address( string $first_name, string $last_name, string $billing_country ): array {
		if ( ! $this->address_switch( 'include_shipping' ) ) {
			return array();
		}

		// An empty array means "same as billing". `different_addresses_ratio` says how often the
		// two should differ; it was declared on all three surfaces and fixed at 30 in the code.
		if ( ! $this->get_faker()->boolean( $this->different_addresses_ratio() ) ) {
			return array();
		}

		// 80% chance shipping address is in the same country
		$country = $this->get_faker()->boolean( 80 ) ? $billing_country : $this->random_country_code();

		return array(
			'first_name'   => $first_name,
			'last_name'    => $last_name,
			'company'      => $this->get_faker()->optional( 0.2 )->company(),
			'address_1'    => $this->get_faker()->streetAddress(),
			'address_2'    => $this->get_faker()->optional( 0.3 )->secondaryAddress(),
			'city'         => $this->get_faker()->city(),
			'state'        => $this->generate_state( $country ),
			'country'      => $country,
			'postcode'     => $this->generate_postcode( $country ),
			'instructions' => $this->get_faker()->optional( 0.4 )->sentence( 6, true ),
		);
	}

	/**
	 * Read one `address_preferences` switch.
	 *
	 * @since 1.1.0
	 *
	 * @param string $name Switch name.
	 *
	 * @return bool
	 */
	private function address_switch( string $name ): bool {
		$prefs = (array) ( $this->generation_params['address_preferences'] ?? array() );

		if ( ! isset( $prefs[ $name ] ) ) {
			return true;
		}

		return (bool) $prefs[ $name ];
	}

	/**
	 * How often billing and shipping should differ, as a percentage.
	 *
	 * @since 1.1.0
	 *
	 * @return int
	 */
	private function different_addresses_ratio(): int {
		$prefs = (array) ( $this->generation_params['address_preferences'] ?? array() );
		$ratio = isset( $prefs['different_addresses_ratio'] ) ? (int) $prefs['different_addresses_ratio'] : 30;

		return max( 0, min( 100, $ratio ) );
	}

	/**
	 * How many customers opt in to marketing, as a percentage.
	 *
	 * @since 1.1.0
	 *
	 * @return int
	 */
	private function marketing_opt_in_ratio(): int {
		$prefs = (array) ( $this->generation_params['contact_preferences'] ?? array() );
		$ratio = isset( $prefs['marketing_opt_in_ratio'] ) ? (int) $prefs['marketing_opt_in_ratio'] : 60;

		return max( 0, min( 100, $ratio ) );
	}

	/**
	 * A customer who has bought nothing.
	 *
	 * @since 1.1.0
	 *
	 * @return array<string, mixed>
	 */
	private function empty_customer_history(): array {
		return array(
			'total_orders'        => 0,
			// Integer minor units, like every other amount. A float here was written straight into
			// Fluent Cart's `ltv`, a BIGINT of cents — so $1,234.56 was stored as 1234 and shown
			// as $12.34.
			'total_spent'         => 0,
			'average_order_value' => 0,
			'first_order_date'    => null,
			'last_order_date'     => null,
			// A requested tier applies to a customer who has bought nothing too: this branch
			// hardcoded bronze, so asking for platinum customers and getting any without a
			// purchase history silently produced bronze ones.
			'loyalty_tier'        => $this->loyalty_tier( 0.0 ),
			'loyalty_points'      => 0,
			'cart_abandonments'   => $this->get_faker()->numberBetween( 0, 3 ),
			'coupon_usage'        => 0,
		);
	}

	/**
	 * Read one `contact_preferences` switch.
	 *
	 * @since 1.1.0
	 *
	 * @param string $name     Switch name.
	 * @param bool   $fallback Value when the caller sent none.
	 *
	 * @return bool
	 */
	private function contact_switch( string $name, bool $fallback ): bool {
		$prefs = (array) ( $this->generation_params['contact_preferences'] ?? array() );

		if ( ! isset( $prefs[ $name ] ) ) {
			return $fallback;
		}

		return (bool) $prefs[ $name ];
	}

	/**
	 * The loyalty tier for this customer.
	 *
	 * `loyalty_tier_focus` names the tiers a run wants. Where it does, the tier is drawn from that
	 * list rather than derived from spend — a store needed for testing a platinum-only screen has
	 * no reason to also contain the spend that earns it.
	 *
	 * @since 1.1.0
	 *
	 * @param float $total_spent Lifetime spend in major units.
	 *
	 * @return string
	 */
	private function loyalty_tier( float $total_spent ): string {
		$known = array( 'bronze', 'silver', 'gold', 'platinum' );
		$focus = array_values(
			array_intersect(
				array_filter( (array) ( $this->generation_params['loyalty_tier_focus'] ?? array() ), 'is_string' ),
				$known
			)
		);

		if ( array() !== $focus ) {
			return (string) $this->get_faker()->randomElement( $focus );
		}

		return $this->determine_loyalty_tier( $total_spent );
	}

	/**
	 * Whether this run should generate purchase history at all.
	 *
	 * Two names for one switch, because two surfaces shipped different ones: the endpoint declares
	 * `include_history` and the admin `purchase_history.simulate_history`. Either turns it off.
	 *
	 * @since 1.1.0
	 *
	 * @return bool
	 */
	private function history_enabled(): bool {
		if ( isset( $this->generation_params['include_history'] ) && ! $this->generation_params['include_history'] ) {
			return false;
		}

		$history = (array) ( $this->generation_params['purchase_history'] ?? array() );

		if ( isset( $history['simulate_history'] ) && ! $history['simulate_history'] ) {
			return false;
		}

		return true;
	}

	/**
	 * The account status for generated customers.
	 *
	 * @since 1.1.0
	 *
	 * @return string
	 */
	private function account_status(): string {
		$known     = array( 'active', 'inactive', 'pending' );
		$requested = (string) ( $this->generation_params['account_status'] ?? 'mixed' );

		if ( in_array( $requested, $known, true ) ) {
			return $requested;
		}

		return (string) $this->get_faker()->randomElement( $known );
	}

	/**
	 * A birth date inside one of the requested age groups.
	 *
	 * `demographics.age_groups` was declared and ignored, so a run asked for customers over 65
	 * produced eighteen-year-olds. A third of customers give no birth date at all, which is the
	 * realistic case and the one a report has to survive.
	 *
	 * @since 1.1.0
	 *
	 * @return string|null
	 */
	private function birth_date(): ?string {
		if ( ! $this->get_faker()->boolean( 65 ) ) {
			return null;
		}

		$bands = array(
			'18-25' => array( 18, 25 ),
			'26-35' => array( 26, 35 ),
			'36-45' => array( 36, 45 ),
			'46-55' => array( 46, 55 ),
			'56-65' => array( 56, 65 ),
			'65+'   => array( 66, 85 ),
		);

		$demographics = (array) ( $this->generation_params['demographics'] ?? array() );
		$requested    = array_values(
			array_intersect(
				array_filter( (array) ( $demographics['age_groups'] ?? array() ), 'is_string' ),
				array_keys( $bands )
			)
		);

		if ( array() === $requested ) {
			$requested = array_keys( $bands );
		}

		list( $min, $max ) = $bands[ (string) $this->get_faker()->randomElement( $requested ) ];

		return $this->get_faker()
			->dateTimeBetween( '-' . $max . ' years', '-' . $min . ' years' )
			->format( 'Y-m-d' );
	}

	/**
	 * Which segment this customer belongs to.
	 *
	 * `customer_types` was declared on the admin and the MCP ability, `customer_type` on the
	 * endpoint with an entirely different vocabulary, and nothing read either.
	 *
	 * @since 1.1.0
	 *
	 * @return string
	 */
	private function customer_type(): string {
		$known     = array( 'regular', 'vip', 'wholesale', 'guest', 'returning' );
		$requested = (array) ( $this->generation_params['customer_types'] ?? array() );
		$allowed   = array_values( array_intersect( array_filter( $requested, 'is_string' ), $known ) );

		if ( array() === $allowed ) {
			$allowed = array( 'regular', 'returning' );
		}

		return (string) $this->get_faker()->randomElement( $allowed );
	}

	/**
	 * Generate customer metadata with realistic patterns
	 *
	 * @since 1.0.0
	 *
	 * @param string $type Which segment this customer belongs to.
	 *
	 * @return array Customer metadata.
	 */
	private function generate_customer_meta( string $type = 'regular' ): array {
		$sample_data = $this->load_sample_data();
		$join_date   = $this->get_faker()->dateTimeBetween( '-5 years', '-1 week' );
		$last_login  = $this->get_faker()->optional( 0.85 )->dateTimeBetween( $join_date, 'now' );

		// Generate realistic customer history based on how long they've been a customer.
		$customer_age_days = $join_date->diff( new \DateTime() )->days;
		$customer_history  = $this->history_enabled()
			? $this->generate_realistic_customer_history( (int) $customer_age_days, 'returning' === $type )
			: $this->empty_customer_history();

		return array_merge(
			array(
				// Customer preferences.
				'customer_preferences' => array(
					'newsletter'           => $this->get_faker()->boolean( 70 ),
					'sms_notifications'    => $this->get_faker()->boolean( 40 ),
					'email_notifications'  => $this->get_faker()->boolean( 85 ),
					// `contact_preferences.marketing_opt_in_ratio` says how many opt in; it was declared
					// on all three surfaces and fixed at 60 in the code.
					'marketing_opt_in'     => $this->get_faker()->boolean( $this->marketing_opt_in_ratio() ),
					'preferred_language'   => $this->get_faker()->randomElement(
						$sample_data['preferred_languages'] ? $sample_data['preferred_languages'] : array( 'en_US', 'es_ES', 'fr_FR', 'de_DE', 'it_IT', 'ja_JP', 'pt_BR', 'hi_IN' )
					),
					'currency'             => $this->get_faker()->randomElement(
						$sample_data['currencies'] ? $sample_data['currencies'] : array( 'USD', 'CAD', 'GBP', 'AUD', 'EUR', 'JPY', 'INR', 'BRL', 'MXN' )
					),
					'timezone'             => $this->get_faker()->timezone(),
					'communication_method' => $this->get_faker()->randomElement( array( 'email', 'sms', 'both', 'none' ) ),
					'preferred_categories' => $this->get_faker()->randomElements(
						$sample_data['preferred_categories'] ? $sample_data['preferred_categories'] : array( 'Electronics', 'Fashion', 'Books', 'Home', 'Sports', 'Beauty' ),
						$this->get_faker()->numberBetween( 1, 3 )
					),
				),

				// Customer statistics.
				'customer_since'       => $join_date->format( 'Y-m-d H:i:s' ),
				'last_login'           => null !== $last_login ? $last_login->format( 'Y-m-d H:i:s' ) : null,

				// Loyalty and engagement.
				'referral_code'        => strtoupper( $this->get_faker()->lexify( '????' ) . $this->get_faker()->numerify( '###' ) ),
				'referred_by'          => $this->get_faker()->optional( 0.2 )->randomNumber( 5 ), // Customer ID who referred.
				'referral_count'       => $this->get_faker()->numberBetween( 0, 5 ),

				// Personal information.
				'birth_date'           => $this->birth_date(),
				'gender'               => $this->get_faker()->optional( 0.55 )->randomElement(
					array( 'male', 'female', 'non_binary', 'prefer_not_to_say' )
				),
				'occupation'           => $this->get_faker()->optional( 0.45 )->jobTitle(),
				'marital_status'       => $this->get_faker()->optional( 0.4 )->randomElement(
					array( 'single', 'married', 'divorced', 'widowed' )
				),

				// Marketing and engagement.
				'source'               => $this->get_faker()->randomElement(
					array( 'organic', 'google_ads', 'facebook_ads', 'instagram', 'referral', 'email_campaign', 'direct', 'affiliate' )
				),
				'utm_campaign'         => $this->get_faker()->optional( 0.35 )->words( 3, true ),
				'utm_source'           => $this->get_faker()->optional( 0.35 )->randomElement(
					array( 'google', 'facebook', 'twitter', 'linkedin', 'email' )
				),
				'utm_medium'           => $this->get_faker()->optional( 0.35 )->randomElement(
					array( 'cpc', 'social', 'email', 'referral', 'organic' )
				),
				'tags'                 => $this->generate_customer_tags(),

				// Customer service.
				'notes'                => $this->get_faker()->optional( 0.25 )->paragraph( 3, true ),
				// Asked for VIPs, every customer is one; otherwise the usual few percent.
				'vip_status'           => 'vip' === $type || $this->get_faker()->boolean( 8 ),
				'account_status'       => $this->account_status(),
				'email_verified'       => $this->get_faker()->boolean( 90 ),
				'phone_verified'       => $this->get_faker()->boolean( 65 ),
			),
			$customer_history
		);
	}

	/**
	 * Generate customer tags for segmentation
	 *
	 * @since 1.0.0
	 *
	 * @return array Customer tags.
	 */
	private function generate_customer_tags(): array {
		$sample_data    = $this->load_sample_data();
		$available_tags = $sample_data['customer_tags'] ? $sample_data['customer_tags'] : array(
			'high_value_customer',
			'frequent_shopper',
			'bargain_seeker',
			'early_adopter',
			'loyal_customer',
			'gift_shopper',
			'bulk_purchaser',
			'international_customer',
			'mobile_shopper',
			'newsletter_subscriber',
			'social_media_engaged',
			'product_reviewer',
			'referrer',
			'seasonal_shopper',
			'cart_abandoner',
			'new_customer',
			'returning_customer',
		);

		return $this->get_faker()->randomElements( $available_tags, $this->get_faker()->numberBetween( 1, 5 ) );
	}

	/**
	 * Pick a country code from the sample data.
	 *
	 * The file customers/{locale}/countries.json holds objects, not bare codes:
	 * `{"code":"US","name":"United States",...}`. Passing an element straight
	 * through hands an array to callers that are typed `string`, which raises a
	 * TypeError — and TypeError extends Error, not Exception, so the per-item
	 * catch in Generator::generate() does not stop it. One bad element takes
	 * down the whole batch as a PHP fatal.
	 *
	 * Both shapes are accepted so the generator keeps working whether or not
	 * the companion sample-data plugin is installed.
	 *
	 * @since 1.0.0
	 *
	 * @return string Two-letter country code.
	 */
	private function random_country_code(): string {
		// A requested list wins over the sample data. `country_focus` has been declared on the
		// endpoint since the first release and read by nothing, so a run asked to stay in Germany
		// produced addresses in fourteen countries.
		$focus = array_values(
			array_filter( (array) ( $this->generation_params['country_focus'] ?? array() ), 'is_string' )
		);

		if ( array() !== $focus ) {
			return strtoupper( (string) $this->get_faker()->randomElement( $focus ) );
		}

		$sample_data = $this->load_sample_data();
		$countries   = ! empty( $sample_data['countries'] )
			? $sample_data['countries']
			: array( 'US', 'CA', 'GB', 'AU', 'DE', 'FR', 'IT', 'ES', 'NL', 'BE', 'JP', 'IN', 'BR', 'MX' );

		$country = $this->get_faker()->randomElement( $countries );

		if ( is_array( $country ) ) {
			$country = $country['code'] ?? '';
		} elseif ( is_object( $country ) ) {
			$country = $country->code ?? '';
		}

		$country = (string) $country;

		return '' === $country ? 'US' : $country;
	}

	/**
	 * Generate phone number based on country
	 *
	 * @since 1.0.0
	 *
	 * @param string $country Country code.
	 *
	 * @return string Phone number.
	 */
	private function generate_phone_number( string $country ): string {
		$sample_data = $this->load_sample_data();
		$patterns    = $sample_data['phone_patterns'] ? $sample_data['phone_patterns'] : array(
			'US' => '+1-###-###-####',
			'CA' => '+1-###-###-####',
			'GB' => '+44-####-######',
			'AU' => '+61-#-####-####',
			'DE' => '+49-###-#######',
			'FR' => '+33-#-##-##-##-##',
			'IT' => '+39-###-###-####',
			'ES' => '+34-###-###-###',
			'NL' => '+31-##-###-####',
			'BE' => '+32-###-###-###',
			'JP' => '+81-##-####-####',
			'IN' => '+91-####-######',
			'BR' => '+55-##-#####-####',
			'MX' => '+52-###-###-####',
		);

		$pattern = $patterns[ $country ] ?? '+1-###-###-####';

		return $this->get_faker()->numerify( $pattern );
	}

	/**
	 * Generate state/province based on country
	 *
	 * @since 1.0.0
	 *
	 * @param string $country Country code.
	 *
	 * @return string State/province.
	 */
	private function generate_state( string $country ): string {
		$sample_data      = $this->load_sample_data();
		$states_provinces = $sample_data['states_provinces'] ? $sample_data['states_provinces'] : array();

		switch ( $country ) {
			case 'US':
				return $this->get_faker()->stateAbbr();
			case 'CA':
				$provinces = $states_provinces['CA'] ?? array( 'AB', 'BC', 'MB', 'NB', 'NL', 'NS', 'NT', 'NU', 'ON', 'PE', 'QC', 'SK', 'YT' );
				return $this->get_faker()->randomElement( $provinces );
			case 'AU':
				$states = $states_provinces['AU'] ?? array( 'NSW', 'VIC', 'QLD', 'WA', 'SA', 'TAS', 'ACT', 'NT' );
				return $this->get_faker()->randomElement( $states );
			case 'GB':
				$counties = $states_provinces['GB'] ?? array(
					'Greater London',
					'Manchester',
					'West Midlands',
					'West Yorkshire',
					'Glasgow',
					'Merseyside',
					'South Yorkshire',
					'Hampshire',
				);
				return $this->get_faker()->randomElement( $counties );
			case 'JP':
				$prefectures = $states_provinces['JP'] ?? array(
					'Tokyo',
					'Osaka',
					'Kyoto',
					'Hokkaido',
					'Aichi',
					'Fukuoka',
					'Kanagawa',
					'Saitama',
				);
				return $this->get_faker()->randomElement( $prefectures );
			case 'IN':
				$states = $states_provinces['IN'] ?? array(
					'Maharashtra',
					'Delhi',
					'Karnataka',
					'Tamil Nadu',
					'Gujarat',
					'West Bengal',
					'Rajasthan',
					'Uttar Pradesh',
				);
				return $this->get_faker()->randomElement( $states );
			case 'BR':
				$states = $states_provinces['BR'] ?? array( 'SP', 'RJ', 'MG', 'RS', 'BA', 'PE', 'CE', 'PR' );
				return $this->get_faker()->randomElement( $states );
			case 'MX':
				$states = $states_provinces['MX'] ?? array(
					'CDMX',
					'Jalisco',
					'Nuevo León',
					'Puebla',
					'Guanajuato',
					'Veracruz',
					'Yucatán',
					'Chihuahua',
				);
				return $this->get_faker()->randomElement( $states );
			default:
				return $this->get_faker()->state();
		}
	}

	/**
	 * Generate postcode based on country
	 *
	 * @since 1.0.0
	 *
	 * @param string $country Country code.
	 *
	 * @return string Postcode.
	 */
	private function generate_postcode( string $country ): string {
		$sample_data = $this->load_sample_data();
		$patterns    = $sample_data['postcode_patterns'] ? $sample_data['postcode_patterns'] : array(
			'US' => '#####',
			'CA' => '?#? #?#',
			'GB' => '??# #??',
			'AU' => '####',
			'DE' => '#####',
			'FR' => '#####',
			'IT' => '#####',
			'ES' => '#####',
			'NL' => '#### ??',
			'BE' => '####',
			'JP' => '###-####',
			'IN' => '######',
			'BR' => '#####-###',
			'MX' => '#####',
		);

		$pattern = $patterns[ $country ] ?? '#####';

		return $this->get_faker()->bothify( $pattern );
	}

	/**
	 * Generate realistic customer history based on customer age
	 *
	 * @since 1.0.0
	 *
	 * @param int  $customer_age_days   Number of days since customer joined.
	 * @param bool $must_have_purchased Whether this customer has definitely bought something.
	 *
	 * @return array Customer history data.
	 */
	private function generate_realistic_customer_history( int $customer_age_days, bool $must_have_purchased = false ): array {
		// Base probability of having made purchases increases with customer age.
		$purchase_probability = min( 0.95, $customer_age_days / 365 * 0.4 + 0.1 );

		// A returning customer has returned, so the coin toss does not apply to one.
		$has_purchases = $must_have_purchased || $this->get_faker()->boolean( $purchase_probability * 100 );

		if ( ! $has_purchases ) {
			return $this->empty_customer_history();
		}

		// Generate realistic purchase history.
		$months_active        = max( 1, $customer_age_days / 30 );
		$avg_orders_per_month = $this->get_faker()->randomFloat( 2, 0.05, 3.0 );
		$total_orders         = max( 1, round( $months_active * $avg_orders_per_month ) );

		// Generate realistic spending patterns.
		$avg_order_value = $this->get_faker()->randomFloat( 2, 30, 500 );
		$total_spent     = $total_orders * $avg_order_value;

		// Add variance for realism.
		$total_spent *= $this->get_faker()->randomFloat( 2, 0.8, 1.5 );

		// Determine loyalty tier.
		$loyalty_tier = $this->loyalty_tier( $total_spent );

		// Calculate loyalty points (1 point per $1, with tier bonuses).
		$base_points     = floor( $total_spent );
		$tier_multiplier = array(
			'bronze'   => 1.0,
			'silver'   => 1.2,
			'gold'     => 1.5,
			'platinum' => 2.0,
		);
		$loyalty_points  = floor( $base_points * $tier_multiplier[ $loyalty_tier ] );

		// Determine last order date.
		$last_order_days_ago = $this->get_faker()->numberBetween( 1, min( 365, $customer_age_days ) );
		$last_order_date     = $this->get_faker()->dateTimeBetween( "-{$last_order_days_ago} days", 'now' );
		$first_order_date    = $this->get_faker()->dateTimeBetween(
			'-' . max( 1, $customer_age_days ) . ' days',
			$last_order_date
		);

		// Generate additional engagement metrics.
		$cart_abandonments = $this->get_faker()->numberBetween( 0, ceil( $total_orders / 2 ) );
		$coupon_usage      = $this->get_faker()->numberBetween( 0, ceil( $total_orders / 3 ) );

		$spent = (int) round( $total_spent * 100 );

		return array(
			'total_orders'        => $total_orders,
			'total_spent'         => $spent,
			'average_order_value' => (int) round( $spent / $total_orders ),
			// A first purchase cannot precede the account. Fluent Cart stores both dates and
			// showed the same value in each, which made every customer look like a one-off.
			'first_order_date'    => $first_order_date->format( 'Y-m-d H:i:s' ),
			'last_order_date'     => $last_order_date->format( 'Y-m-d H:i:s' ),
			'loyalty_tier'        => $loyalty_tier,
			'loyalty_points'      => $loyalty_points,
			'cart_abandonments'   => $cart_abandonments,
			'coupon_usage'        => $coupon_usage,
		);
	}

	/**
	 * Determine loyalty tier based on total spent
	 *
	 * @since 1.0.0
	 *
	 * @param float $total_spent Total amount spent by customer.
	 *
	 * @return string Loyalty tier.
	 */
	private function determine_loyalty_tier( float $total_spent ): string {
		if ( $total_spent >= 7500 ) {
			return 'platinum';
		}

		if ( $total_spent >= 3000 ) {
			return 'gold';
		}

		if ( $total_spent >= 1000 ) {
			return 'silver';
		}

		return 'bronze';
	}

	/**
	 * Preview columns for customers
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
				'key'   => 'email',
				'label' => __( 'Email', 'storeseeder' ),
			),
			array(
				'key'   => 'city',
				'label' => __( 'City', 'storeseeder' ),
			),
			array(
				'key'   => 'country',
				'label' => __( 'Country', 'storeseeder' ),
			),
			array(
				'key'   => 'orders',
				'label' => __( 'Orders', 'storeseeder' ),
			),
		);
	}

	/**
	 * Build a customer preview row
	 *
	 * Mirrors the fields generate_single_item() fills in, without touching the
	 * database.
	 *
	 * @since 1.0.1
	 *
	 * @return array<string, array{v: mixed, kind: string}>
	 */
	protected function build_preview_row(): array {
		$faker = $this->get_faker();
		$first = $faker->firstName();
		$last  = $faker->lastName();

		return array(
			'name'    => array(
				'v'    => $first . ' ' . $last,
				'kind' => 'text',
			),
			'email'   => array(
				'v'    => strtolower( $first . '.' . $last ) . '@example.com',
				'kind' => 'mono',
			),
			'city'    => array(
				'v'    => $faker->city(),
				'kind' => 'text',
			),
			'country' => array(
				'v'    => $faker->countryCode(),
				'kind' => 'badge',
			),
			'orders'  => array(
				// Zero when history is switched off, which is the whole point of the switch. The
				// literal 0–50 showed a lifetime of purchases for a run that would create none of
				// them, so the parameter looked broken on the only screen that could have shown it
				// working.
				'v'    => $this->history_enabled() ? $faker->numberBetween( 1, 50 ) : 0,
				'kind' => 'num',
			),
		);
	}
}
