<?php
/**
 * Generated product imagery
 *
 * @since   1.3.0
 * @package StoreSeeder\Platforms
 */

namespace StoreSeeder\Platforms;

use StoreSeeder\Generation\Ledger;

defined( 'ABSPATH' ) || exit;

/**
 * A small pool of generated attachments, shared by every product in a run.
 *
 * A catalogue of grey placeholders is the first thing anyone notices about generated data, and
 * the last thing a screenshot forgives. This creates identicons — deterministic geometric marks
 * drawn from a seed — and hands them out.
 *
 * It is a *pool* rather than an image per product, and that is the whole design. Nine hundred
 * products would otherwise mean nine hundred PNG uploads, nine hundred rows in `wp_posts`, and a
 * `wp_generate_attachment_metadata()` call each — which is slower than generating the products
 * themselves and fills a media library nobody asked for. A dozen images spread over a catalogue
 * reads as a catalogue; the eye is looking at the grid, not comparing thumbnails.
 *
 * Existing attachments are reused before anything is created, so a site that already has media
 * gets its own pictures rather than a fresh set of abstract marks.
 *
 * @since 1.3.0
 */
final class Media {

	/**
	 * Edge length, in pixels, of a generated image.
	 *
	 * Matches wc-smooth-generator, which is the size WooCommerce's own image settings assume.
	 *
	 * @since 1.3.0
	 * @var int
	 */
	const SIZE = 700;

	/**
	 * How many attachments a pool holds when the caller does not say.
	 *
	 * @since 1.3.0
	 * @var int
	 */
	const POOL_SIZE = 10;

	/**
	 * The largest pool a caller can ask for.
	 *
	 * A recipe asking for a thousand images would spend the whole run in `imagepng()`.
	 *
	 * @since 1.3.0
	 * @var int
	 */
	const MAX_POOL = 60;

	/**
	 * Attachment ids for this request, keyed by platform.
	 *
	 * Static because the pool is built once and drawn from thousands of times. A run is a single
	 * request from this class's point of view; a second request rebuilds it, which is correct —
	 * it will find the first run's attachments and reuse them.
	 *
	 * @since 1.3.0
	 * @var array<string, array<int, int>>
	 */
	private static $pool = array();

	/**
	 * Whether images can be generated at all on this host.
	 *
	 * GD is not guaranteed. WordPress runs on Imagick-only hosts, and `imagecreatefromstring()`
	 * is what turns the identicon's PNG data into something `imagepng()` can write.
	 *
	 * @since 1.3.0
	 *
	 * @return bool
	 */
	public static function available(): bool {
		return class_exists( '\Jdenticon\Identicon' )
			&& function_exists( 'imagecreatefromstring' )
			&& function_exists( 'imagepng' );
	}

	/**
	 * Attachment ids to draw product images from.
	 *
	 * @since 1.3.0
	 *
	 * @param string $platform  Platform id, for the ledger.
	 * @param int    $size      How many attachments the pool should hold.
	 * @param int    $image_size Edge length in pixels.
	 *
	 * @return array<int, int> Attachment ids. Empty when this host cannot generate images.
	 */
	public static function pool( string $platform, int $size = self::POOL_SIZE, int $image_size = self::SIZE ): array {
		if ( isset( self::$pool[ $platform ] ) ) {
			return self::$pool[ $platform ];
		}

		$size       = max( 1, min( self::MAX_POOL, $size ) );
		$image_size = max( 64, min( 2000, $image_size ) );

		// Whatever the site already has comes first: a store with real photographs should be
		// seeded with its own, not with abstract marks beside them.
		$existing = get_posts(
			array(
				'post_type'      => 'attachment',
				'post_mime_type' => 'image',
				'post_status'    => 'inherit',
				'posts_per_page' => $size,
				'fields'         => 'ids',
				'exclude'        => array( (int) get_option( 'woocommerce_placeholder_image', 0 ) ),
			)
		);

		$ids = array_map( 'intval', (array) $existing );

		if ( self::available() ) {
			for ( $i = count( $ids ); $i < $size; $i++ ) {
				$id = self::create( $platform, 'storeseeder-' . $i, $image_size );

				if ( $id > 0 ) {
					$ids[] = $id;
				}
			}
		}

		self::$pool[ $platform ] = $ids;

		return $ids;
	}

	/**
	 * Render one identicon and store it as an attachment.
	 *
	 * Recorded in the ledger here rather than by the generator, which is the exception to the
	 * rule stated in `Generation\Generator`: the ledger is written centrally so that eighteen
	 * writers are not eighteen chances to forget. An attachment has no entity of its own and is
	 * never the `id` a writer returns, so the central path cannot see it — and an unrecorded
	 * attachment is a file cleanup leaves behind in `uploads/` for ever. One shared helper that
	 * always records is not the same risk as eighteen that might.
	 *
	 * @since 1.3.0
	 *
	 * @param string $platform Platform id, for the ledger.
	 * @param string $seed     Identicon seed. The same seed always draws the same mark.
	 * @param int    $size     Edge length in pixels.
	 *
	 * @return int Attachment id, or 0 if the image could not be created.
	 */
	private static function create( string $platform, string $seed, int $size ): int {
		$seed = sanitize_key( $seed );

		$identicon = new \Jdenticon\Identicon();
		$identicon->setValue( $seed );
		$identicon->setSize( $size );

		// The library returns PNG bytes; going back through GD is what wc-smooth-generator does
		// and what produces a file WordPress will generate thumbnails from.
		$image = @imagecreatefromstring( (string) $identicon->getImageData() ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- a malformed image is handled below rather than warned about.

		if ( false === $image ) {
			return 0;
		}

		ob_start();
		imagepng( $image );
		$bytes = (string) ob_get_clean();

		// imagedestroy() has no effect since PHP 8.0 and is deprecated in 8.5; dropping the
		// reference is what frees the memory now.
		unset( $image );

		$upload = wp_upload_bits( "img-{$seed}.png", null, $bytes );

		if ( ! empty( $upload['error'] ) || empty( $upload['file'] ) ) {
			return 0;
		}

		$attachment_id = (int) wp_insert_attachment(
			array(
				'post_title'     => "img-{$seed}",
				'post_mime_type' => (string) $upload['type'],
				'post_status'    => 'inherit',
				'post_content'   => '',
			),
			$upload['file']
		);

		if ( $attachment_id < 1 ) {
			return 0;
		}

		if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}

		wp_update_attachment_metadata(
			$attachment_id,
			wp_generate_attachment_metadata( $attachment_id, $upload['file'] )
		);

		Ledger::record( $platform, Resource::MEDIA, $attachment_id );

		return $attachment_id;
	}

	/**
	 * Forget the cached pool.
	 *
	 * For tests, and for a long-running process that has just deleted the attachments it was
	 * holding ids for.
	 *
	 * @since 1.3.0
	 *
	 * @return void
	 */
	public static function reset(): void {
		self::$pool = array();
	}
}
