import React, { useCallback, useEffect, useRef, useState } from "@wordpress/element";
import apiFetch from "@wordpress/api-fetch";
import { __ } from "@wordpress/i18n";

import { Icon } from "@/lib/icons";
import type { EntityKind } from "@/lib/fieldsFromSchema";

interface EntityResult {
  id: number | string;
  label: string;
}

interface EntityFieldProps {
  /** The chosen id, or "" for none. */
  value: string;
  onChange: (v: string) => void;
  /** Which kind of record to look for. */
  entity: EntityKind;
  /** Lets the caller bind its own <label htmlFor> to the input. */
  id?: string;
}

/** How long to wait after the last keystroke before asking the server. */
const DEBOUNCE_MS = 250;

const PLACEHOLDER: Record<EntityKind, string> = {
  product: __("Search products, or type an ID", "storeseeder"),
  customer: __("Search customers, or type an ID", "storeseeder"),
};

/**
 * Picks an existing record by name, and sends its id.
 *
 * `customer_id` and `product_id` are foreign keys into the target store, and the admin rendered
 * them as a number box — answerable only by someone who already knew the id. This asks the
 * `/lookup` endpoint, which reads through the resolved platform's own driver.
 *
 * A typed id still works. Suggestions are an affordance, not a gate: a store with a driver that
 * cannot search, or one whose search finds nothing, must not become a store you cannot target.
 *
 * What is *shown* is the label — "Ada Lovelace — ada@example.test" rather than "42". What is
 * *sent* is only ever the id: `onChange` emits `String(result.id)` and nothing else, so the two
 * cannot drift the way the locale picker's once did, when it stored a display label and tried to
 * send it to the API. The label is remembered beside the value rather than derived from it, and is
 * dropped the moment the value changes to something it does not describe.
 */
export function EntityField({ value, onChange, entity, id }: EntityFieldProps) {
  const [query, setQuery] = useState("");
  /**
   * The label for the currently chosen id, when it was chosen from the list.
   *
   * A value can arrive without one — restored from a saved configuration, or typed as a bare id —
   * and then the id itself is all there is to show.
   */
  const [chosen, setChosen] = useState<{ id: string; label: string } | null>(null);
  const [results, setResults] = useState<EntityResult[]>([]);
  const [open, setOpen] = useState(false);
  const [loading, setLoading] = useState(false);
  const [failed, setFailed] = useState(false);
  const wrap = useRef<HTMLDivElement>(null);
  /** Rising counter, so a slow response cannot overwrite a newer one. */
  const request = useRef(0);

  const search = useCallback(
    async (term: string) => {
      const ticket = ++request.current;

      setLoading(true);
      setFailed(false);

      try {
        const data = await apiFetch<{ results?: EntityResult[] }>({
          path: `/storeseeder/v1/lookup?resource=${encodeURIComponent(entity)}&search=${encodeURIComponent(term)}&limit=20`,
        });

        if (ticket !== request.current) return;

        setResults(Array.isArray(data?.results) ? data.results : []);
      } catch {
        if (ticket !== request.current) return;

        // A failed lookup leaves the field usable: the id can still be typed.
        setResults([]);
        setFailed(true);
      } finally {
        if (ticket === request.current) setLoading(false);
      }
    },
    [entity],
  );

  // Debounced: a keystroke per request would put one query per letter on the store's database.
  useEffect(() => {
    if (!open) return;

    const timer = setTimeout(() => void search(query), DEBOUNCE_MS);

    return () => clearTimeout(timer);
  }, [query, open, search]);

  // A value can arrive without a label — restored from a saved configuration, or typed as a bare
  // id. Ask the store what it is called, so the field can name it rather than showing a number.
  useEffect(() => {
    if ("" === value || (chosen && chosen.id === value)) return;
    if (!/^\d+$/.test(value)) return;

    let cancelled = false;

    void (async () => {
      try {
        const data = await apiFetch<{ results?: EntityResult[] }>({
          path: `/storeseeder/v1/lookup?resource=${encodeURIComponent(entity)}&search=${encodeURIComponent(value)}&limit=5`,
        });

        if (cancelled) return;

        const match = (data?.results ?? []).find((r) => String(r.id) === value);

        if (match) setChosen({ id: value, label: match.label });
      } catch {
        // Leave the id showing. A store that cannot be reached is not a reason to blank the field.
      }
    })();

    return () => {
      cancelled = true;
    };
  }, [value, entity, chosen]);

  // Close on an outside click, the way the other overlays do.
  useEffect(() => {
    if (!open) return;

    const onDown = (event: MouseEvent) => {
      if (!wrap.current?.contains(event.target as Node)) setOpen(false);
    };

    document.addEventListener("mousedown", onDown);

    return () => document.removeEventListener("mousedown", onDown);
  }, [open]);

  const choose = (result: EntityResult) => {
    setChosen({ id: String(result.id), label: result.label });
    onChange(String(result.id));
    setQuery("");
    setOpen(false);
  };

  // What the closed field reads: the label when it describes the current value, the raw id
  // otherwise. Never a stale label — a value the label does not belong to shows as the value.
  const display = chosen && chosen.id === value ? chosen.label : value;

  return (
    <div className="fp-entity" ref={wrap}>
      <div className="fp-entity-input">
        <Icon name="search" size={14} />
        <input
          id={id}
          type="text"
          className="fp-input fp-focusable"
          role="combobox"
          aria-expanded={open}
          aria-autocomplete="list"
          autoComplete="off"
          placeholder={PLACEHOLDER[entity]}
          value={open ? query : display}
          onFocus={() => setOpen(true)}
          onChange={(event) => {
            const next = event.target.value;

            setQuery(next);
            setOpen(true);
            // Typing replaces whatever was chosen, so the remembered label stops applying.
            setChosen(null);

            // A number typed straight in is the id. Anything else is a search term, and the
            // field holds no id until a suggestion is picked.
            onChange(/^\d+$/.test(next.trim()) ? next.trim() : "");
          }}
          onKeyDown={(event) => {
            if ("Escape" === event.key) setOpen(false);
          }}
        />
        {value && (
          <button
            type="button"
            className="fp-entity-clear fp-focusable"
            aria-label={__("Clear", "storeseeder")}
            onClick={() => {
              onChange("");
              setQuery("");
              setChosen(null);
            }}
          >
            <Icon name="x" size={13} />
          </button>
        )}
      </div>

      {open && (
        <div className="fp-entity-list" role="listbox">
          {loading && (
            <div className="fp-entity-note">{__("Searching…", "storeseeder")}</div>
          )}

          {!loading && failed && (
            <div className="fp-entity-note">
              {__("Could not reach the store. Type an ID instead.", "storeseeder")}
            </div>
          )}

          {!loading && !failed && 0 === results.length && (
            <div className="fp-entity-note">{__("No matches.", "storeseeder")}</div>
          )}

          {!loading &&
            results.map((result) => (
              <button
                key={String(result.id)}
                type="button"
                role="option"
                aria-selected={String(result.id) === value}
                className={`fp-entity-item fp-focusable${String(result.id) === value ? " on" : ""}`}
                onClick={() => choose(result)}
              >
                <span>{result.label}</span>
              </button>
            ))}
        </div>
      )}

    </div>
  );
}

export default EntityField;
