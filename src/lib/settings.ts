export interface AppSettings {
  defaultCount: number;
  defaultLocale: string;
  defaultSeed: string;
  defaultIncludeMeta: boolean;
  maxRunsPerGenerator: number;
}

const SETTINGS_KEY = "ec_fp_settings";

function getDefaults(): AppSettings {
  return {
    defaultCount: 10,
    defaultLocale: window.storeseederApi?.locale?.faker ?? "en_US",
    defaultSeed: "",
    defaultIncludeMeta: true,
    maxRunsPerGenerator: 10,
  };
}

/**
 * Merge stored settings over the defaults, one field at a time.
 *
 * The previous version spread `JSON.parse(raw)` wholesale, so anything in
 * localStorage became an AppSettings by assertion — a `defaultCount` left as the
 * string "10" by an older build would have flowed into arithmetic unchecked.
 * A field whose stored type is wrong now falls back to its default.
 */
function mergeStored(stored: Record<string, unknown>): AppSettings {
  const defaults = getDefaults();

  const num = (value: unknown, fallback: number) =>
    "number" === typeof value && Number.isFinite(value) ? value : fallback;

  return {
    defaultCount: num(stored.defaultCount, defaults.defaultCount),
    defaultLocale:
      "string" === typeof stored.defaultLocale
        ? stored.defaultLocale
        : defaults.defaultLocale,
    defaultSeed:
      "string" === typeof stored.defaultSeed
        ? stored.defaultSeed
        : defaults.defaultSeed,
    defaultIncludeMeta:
      "boolean" === typeof stored.defaultIncludeMeta
        ? stored.defaultIncludeMeta
        : defaults.defaultIncludeMeta,
    maxRunsPerGenerator: num(
      stored.maxRunsPerGenerator,
      defaults.maxRunsPerGenerator,
    ),
  };
}

export function getSettings(): AppSettings {
  try {
    const raw = localStorage.getItem(SETTINGS_KEY);
    if (!raw) {
      return getDefaults();
    }

    const parsed: unknown = JSON.parse(raw);
    return null !== parsed && "object" === typeof parsed
      ? mergeStored(parsed as Record<string, unknown>)
      : getDefaults();
  } catch {
    return getDefaults();
  }
}

export function saveSettings(settings: AppSettings): void {
  try {
    localStorage.setItem(SETTINGS_KEY, JSON.stringify(settings));
  } catch {
    // ignore quota errors
  }
}
