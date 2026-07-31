import { __ } from "@wordpress/i18n";

/**
 * The locales StoreSeeder can generate in.
 *
 * Sourced from the server, which builds the list from FakerPHP's own providers, so
 * what the admin offers is exactly what the REST API accepts. That was not previously
 * true: the picker listed 73 while the API's enum allowed 6, and the other 67 silently
 * produced English.
 *
 * Locale is stored and sent as a **code** (`ja_JP`). Labels are for display only —
 * an earlier version stored the label, which meant the value could never be sent as-is.
 */

export const DEFAULT_LOCALE = "en_US";

export interface LocaleOption {
  code: string;
  label: string;
}

export function localeMap(): Record<string, string> {
  return window.storeseederApi?.locale?.allLocales ?? {};
}

export function defaultLocale(): string {
  return (
    window.storeseederApi?.locale?.default ??
    window.storeseederApi?.locale?.faker ??
    DEFAULT_LOCALE
  );
}

/** Every locale as {code,label}, sorted by label. */
export function localeOptions(): LocaleOption[] {
  return Object.entries(localeMap())
    .map(([code, label]) => ({ code, label }))
    .sort((a, b) => a.label.localeCompare(b.label));
}

/** Display label for a code, falling back to the code so nothing renders blank. */
export function localeLabel(code: string): string {
  return localeMap()[code] ?? code;
}

/** Whether a code is one the server will accept. */
export function isSupportedLocale(code: string): boolean {
  return code in localeMap();
}

/**
 * Filter locales by a query, matching label or code.
 *
 * With 75 options a plain list is a scroll-hunt, and someone who knows the code is
 * faster typing "ja_JP" than finding "Japanese (Japan)" by eye.
 */
export function filterLocales(options: LocaleOption[], query: string): LocaleOption[] {
  const q = query.trim().toLowerCase();
  if (!q) return options;

  return options.filter(
    (o) => o.label.toLowerCase().includes(q) || o.code.toLowerCase().includes(q),
  );
}

/** Accessible name for the picker. */
export function localePickerLabel(): string {
  return __("Generation locale", "storeseeder");
}
