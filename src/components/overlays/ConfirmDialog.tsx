import React from "react";
import { useCallback, useEffect, useRef } from "@wordpress/element";
import { __ } from "@wordpress/i18n";

import { Icon } from "@/lib/icons";
import type { IconName } from "@/lib/icons";
import { Button } from "@/components/ui/button";

interface ConfirmDialogProps {
  /** What is about to happen, as a question. */
  title: string;
  /** What it will do, and what it will not. The place to bound the damage. */
  body: React.ReactNode;
  /** The confirming button's label. Name the action, never "OK". */
  confirmLabel: string;
  /** Shown on the confirm button while the action runs. */
  busyLabel?: string;
  /** Icon for the confirm button and the dialog's own mark. */
  icon?: IconName;
  /** True while the action is in flight; both buttons lock and the dialog will not close. */
  busy?: boolean;
  onConfirm: () => void;
  onCancel: () => void;
  testId?: string;
}

/**
 * The dialog that stands between a destructive action and a mis-click.
 *
 * One component rather than a pattern per screen. The purge had grown an inline two-button swap,
 * "forget the remaining records" and "clear run history" had nothing at all, and undoing a recipe
 * — thousands of rows across nine resources — was a single click. Four screens, four answers to
 * the same question, and the two that skipped it were the two nobody had looked at recently.
 *
 * Three things it does that an inline confirmation cannot:
 *
 * - **Cancel takes the focus.** A dialog that opens with the destructive button focused turns a
 *   stray Return into the thing it exists to prevent.
 * - **It traps Tab.** Otherwise focus walks out into the page behind and the next Return presses
 *   something nobody can see.
 * - **Escape and the backdrop cancel** — but not while the action is running, because there is
 *   nothing left to cancel and closing would only hide the outcome.
 */
export function ConfirmDialog({
  title,
  body,
  confirmLabel,
  busyLabel,
  icon = "trash",
  busy = false,
  onConfirm,
  onCancel,
  testId,
}: ConfirmDialogProps) {
  const panel = useRef<HTMLDivElement>(null);
  const cancelButton = useRef<HTMLButtonElement>(null);

  const dismiss = useCallback(() => {
    if (!busy) onCancel();
  }, [busy, onCancel]);

  // Cancel, not confirm. The safe choice is the one already under the finger.
  useEffect(() => {
    cancelButton.current?.focus();
  }, []);

  useEffect(() => {
    const onKey = (event: KeyboardEvent) => {
      if ("Escape" === event.key) {
        event.preventDefault();
        dismiss();

        return;
      }

      if ("Tab" !== event.key || !panel.current) return;

      // Keep Tab inside. A modal whose focus escapes is a modal only visually.
      const focusable = panel.current.querySelectorAll<HTMLElement>(
        'button:not([disabled]), a[href], [tabindex]:not([tabindex="-1"])',
      );

      if (0 === focusable.length) return;

      const first = focusable[0];
      const last = focusable[focusable.length - 1];
      // `ownerDocument`, not the global: this admin can be rendered into an iframe, and `document`
      // would then be the wrong one.
      const active = panel.current.ownerDocument.activeElement;

      if (event.shiftKey && active === first) {
        event.preventDefault();
        last.focus();
      } else if (!event.shiftKey && active === last) {
        event.preventDefault();
        first.focus();
      }
    };

    document.addEventListener("keydown", onKey);

    return () => document.removeEventListener("keydown", onKey);
  }, [dismiss]);

  // The scrim carries a mouse convenience, not a control: Escape and the Cancel button are the
  // keyboard paths, so `presentation` is the honest role rather than pretending to be a button that
  // screen readers would then announce.
  return (
    <div
      className="fp-overlay"
      role="presentation"
      style={{ alignItems: "center", justifyContent: "center" }}
      onMouseDown={(event) => {
        // Only the backdrop itself. A drag that started inside the panel and ended out here is a
        // text selection, not a dismissal.
        if (event.target === event.currentTarget) dismiss();
      }}
    >
      <div
        className="fp-confirm"
        role="alertdialog"
        aria-modal="true"
        aria-labelledby="fp-confirm-title"
        aria-describedby="fp-confirm-body"
        ref={panel}
        data-testid={testId ?? "confirm-dialog"}
      >
        <span className="fp-confirm-ic">
          <Icon name={icon} size={20} />
        </span>

        <h2 id="fp-confirm-title" className="fp-confirm-title">
          {title}
        </h2>

        <div id="fp-confirm-body" className="fp-confirm-body">
          {body}
        </div>

        <div className="fp-confirm-foot">
          <Button
            ref={cancelButton}
            variant="outline"
            size="md"
            type="button"
            disabled={busy}
            onClick={onCancel}
            data-testid="confirm-cancel"
          >
            {__("Cancel", "storeseeder")}
          </Button>
          <Button
            variant="danger"
            size="md"
            icon={icon}
            type="button"
            disabled={busy}
            onClick={onConfirm}
            data-testid="confirm-accept"
          >
            {busy && busyLabel ? busyLabel : confirmLabel}
          </Button>
        </div>
      </div>
    </div>
  );
}

export default ConfirmDialog;
