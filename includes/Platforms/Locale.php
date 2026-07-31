<?php
/**
 * Supported generation locales
 *
 * The single list of locales StoreSeeder can generate in. It exists because there used
 * to be two: the admin offered seventy-three while the REST API's enum accepted six,
 * so sixty-seven of them silently produced English data. A user picking Japanese got
 * English and was told nothing.
 *
 * Every entry here is a locale FakerPHP actually ships a provider for. That is not a
 * detail: `Factory::create()` falls back to en_US without complaint for a locale it
 * does not know, so offering one faker lacks is the same bug in a different place.
 * LocaleTest asserts this list matches faker's providers exactly, so the two cannot
 * drift again.
 *
 * @since   1.1.0
 * @package StoreSeeder\Platforms
 */

namespace StoreSeeder\Platforms;

defined( 'ABSPATH' ) || exit;

/**
 * Generation locales.
 *
 * @since 1.1.0
 */
final class Locale {
	/**
	 * Locale used when none is given, and the fallback when one cannot be matched.
	 *
	 * @since 1.1.0
	 * @var string
	 */
	const DEFAULT_LOCALE = 'en_US';

	/**
	 * Every supported locale, mapped to its display label.
	 *
	 * Labels are deliberately not translated. They name a language and region to
	 * someone choosing which locale to generate *in*, so they read better in their own
	 * terms than translated into the reader's — the same reason a language picker lists
	 * "Deutsch" rather than "German".
	 *
	 * @since 1.1.0
	 *
	 * @return array<string, string>
	 */
	public static function all(): array {
		$locales = array(
			'ar_EG'      => 'Arabic (Egypt)',
			'ar_JO'      => 'Arabic (Jordan)',
			'ar_SA'      => 'Arabic (Saudi Arabia)',
			'at_AT'      => 'Austrian German',
			'bg_BG'      => 'Bulgarian (Bulgaria)',
			'bn_BD'      => 'Bangla (Bangladesh)',
			'cs_CZ'      => 'Czech (Czech Republic)',
			'da_DK'      => 'Danish (Denmark)',
			'de_AT'      => 'German (Austria)',
			'de_CH'      => 'German (Switzerland)',
			'de_DE'      => 'German (Germany)',
			'el_CY'      => 'Greek (Cyprus)',
			'el_GR'      => 'Greek (Greece)',
			'en_AU'      => 'English (Australia)',
			'en_CA'      => 'English (Canada)',
			'en_GB'      => 'English (Great Britain)',
			'en_HK'      => 'English (Hong Kong)',
			'en_IN'      => 'English (India)',
			'en_NG'      => 'English (Nigeria)',
			'en_NZ'      => 'English (New Zealand)',
			'en_PH'      => 'English (Philippines)',
			'en_SG'      => 'English (Singapore)',
			'en_UG'      => 'English (Uganda)',
			'en_US'      => 'English (United States)',
			'en_ZA'      => 'English (South Africa)',
			'es_AR'      => 'Spanish (Argentina)',
			'es_ES'      => 'Spanish (Spain)',
			'es_PE'      => 'Spanish (Peru)',
			'es_VE'      => 'Spanish (Venezuela)',
			'et_EE'      => 'Estonian (Estonia)',
			'fa_IR'      => 'Persian (Iran)',
			'fi_FI'      => 'Finnish (Finland)',
			'fr_BE'      => 'French (Belgium)',
			'fr_CA'      => 'French (Canada)',
			'fr_CH'      => 'French (Switzerland)',
			'fr_FR'      => 'French (France)',
			'he_IL'      => 'Hebrew (Israel)',
			'hr_HR'      => 'Croatian (Croatia)',
			'hu_HU'      => 'Hungarian (Hungary)',
			'hy_AM'      => 'Armenian (Armenia)',
			'id_ID'      => 'Indonesian (Indonesia)',
			'is_IS'      => 'Icelandic (Iceland)',
			'it_CH'      => 'Italian (Switzerland)',
			'it_IT'      => 'Italian (Italy)',
			'ja_JP'      => 'Japanese (Japan)',
			'ka_GE'      => 'Georgian (Georgia)',
			'kk_KZ'      => 'Kazakh (Kazakhstan)',
			'ko_KR'      => 'Korean (South Korea)',
			'lt_LT'      => 'Lithuanian (Lithuania)',
			'lv_LV'      => 'Latvian (Latvia)',
			'me_ME'      => 'Montenegrin (Montenegro)',
			'mn_MN'      => 'Mongolian (Mongolia)',
			'ms_MY'      => 'Malay (Malaysia)',
			'nb_NO'      => 'Norwegian Bokmål (Norway)',
			'ne_NP'      => 'Nepali (Nepal)',
			'nl_BE'      => 'Dutch (Belgium)',
			'nl_NL'      => 'Dutch (Netherlands)',
			'pl_PL'      => 'Polish (Poland)',
			'pt_BR'      => 'Portuguese (Brazil)',
			'pt_PT'      => 'Portuguese (Portugal)',
			'ro_MD'      => 'Romanian (Moldova)',
			'ro_RO'      => 'Romanian (Romania)',
			'ru_RU'      => 'Russian (Russia)',
			'sk_SK'      => 'Slovak (Slovakia)',
			'sl_SI'      => 'Slovenian (Slovenia)',
			'sr_Cyrl_RS' => 'Serbian Cyrillic (Serbia)',
			'sr_Latn_RS' => 'Serbian Latin (Serbia)',
			'sr_RS'      => 'Serbian (Serbia)',
			'sv_SE'      => 'Swedish (Sweden)',
			'th_TH'      => 'Thai (Thailand)',
			'tr_TR'      => 'Turkish (Turkey)',
			'uk_UA'      => 'Ukrainian (Ukraine)',
			'vi_VN'      => 'Vietnamese (Vietnam)',
			'zh_CN'      => 'Chinese (China)',
			'zh_TW'      => 'Chinese (Taiwan)',
		);

		/**
		 * Filters the locales StoreSeeder can generate in.
		 *
		 * Adding a locale only works if FakerPHP has a provider for it, or if you have
		 * registered one yourself — faker falls back to en_US in silence otherwise.
		 *
		 * @since 1.1.0
		 *
		 * @param mixed $locales Locale code => display label, an array<string, string>
		 *                       when unfiltered. Typed loosely because a filter may return
		 *                       anything at all, and the guard below discards a return the
		 *                       plugin cannot use rather than trusting the docblock.
		 */
		$filtered = apply_filters( 'storeseeder_locales', $locales );

		// A filter that returns nothing would leave the plugin unable to generate at
		// all, so the default always survives.
		if ( ! is_array( $filtered ) || array() === $filtered ) {
			return $locales;
		}

		return $filtered;
	}

	/**
	 * Supported locale codes.
	 *
	 * @since 1.1.0
	 *
	 * @return array<int, string>
	 */
	public static function codes(): array {
		return array_keys( self::all() );
	}

	/**
	 * Whether a locale can be generated in.
	 *
	 * @since 1.1.0
	 *
	 * @param string $locale Locale code.
	 *
	 * @return bool
	 */
	public static function is_supported( string $locale ): bool {
		return isset( self::all()[ $locale ] );
	}

	/**
	 * Display label for a locale.
	 *
	 * @since 1.1.0
	 *
	 * @param string $locale Locale code.
	 *
	 * @return string The label, or the code itself when unknown.
	 */
	public static function label( string $locale ): string {
		return self::all()[ $locale ] ?? $locale;
	}

	/**
	 * Narrow any locale to one that can actually be generated in.
	 *
	 * Tries the locale itself, then any locale sharing its language — a site running
	 * `de_LU` gets German data from `de_DE` rather than English — and falls back to the
	 * default only when neither matches.
	 *
	 * @since 1.1.0
	 *
	 * @param string $locale A WordPress locale, or any locale code.
	 *
	 * @return string A supported locale code.
	 */
	public static function resolve( string $locale ): string {
		/**
		 * Filters the locale used for test data generation.
		 *
		 * Allows overriding the locale StoreSeeder generates in, regardless of the
		 * site's own setting.
		 *
		 * @since 1.0.0
		 * @hook  storeseeder_locale
		 *
		 * @param string $locale The incoming locale code.
		 */
		$requested = (string) apply_filters( 'storeseeder_locale', $locale );

		if ( self::is_supported( $requested ) ) {
			return $requested;
		}

		$language = substr( $requested, 0, 2 );

		foreach ( self::codes() as $code ) {
			if ( 0 === strpos( $code, $language . '_' ) ) {
				return $code;
			}
		}

		return self::DEFAULT_LOCALE;
	}
}
