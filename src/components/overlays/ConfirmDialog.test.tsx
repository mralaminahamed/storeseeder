import React from "react";
import { describe, expect, it, jest } from "@jest/globals";
import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";

import { ConfirmDialog } from "./ConfirmDialog";

/**
 * This dialog exists to stand between a destructive action and a mis-click, so the tests are about
 * the ways it could fail to do that rather than about what it looks like.
 */
describe("ConfirmDialog", () => {
  function open(over: Partial<Parameters<typeof ConfirmDialog>[0]> = {}) {
    const onConfirm = jest.fn();
    const onCancel = jest.fn();

    render(
      <ConfirmDialog
        title="Delete the generated data?"
        body={<p>This cannot be undone.</p>}
        confirmLabel="Yes, delete 1,204 rows"
        onConfirm={onConfirm}
        onCancel={onCancel}
        {...over}
      />,
    );

    return { onConfirm, onCancel };
  }

  /**
   * The single most important property. A dialog that opens with the destructive button focused
   * turns a stray Return — from the click that opened it — into the thing it exists to prevent.
   */
  it("puts the focus on Cancel, not on the destructive answer", () => {
    open();

    expect(screen.getByTestId("confirm-cancel")).toHaveFocus();
  });

  it("confirms only when the confirming button is pressed", async () => {
    const { onConfirm, onCancel } = open();

    await userEvent.click(screen.getByTestId("confirm-accept"));

    expect(onConfirm).toHaveBeenCalledTimes(1);
    expect(onCancel).not.toHaveBeenCalled();
  });

  it("cancels on the Cancel button, on Escape, and on the backdrop", async () => {
    const first = open();

    await userEvent.click(screen.getByTestId("confirm-cancel"));
    expect(first.onCancel).toHaveBeenCalledTimes(1);
    expect(first.onConfirm).not.toHaveBeenCalled();

    screen.getByTestId("confirm-dialog").remove();

    const second = open();

    await userEvent.keyboard("{Escape}");
    expect(second.onCancel).toHaveBeenCalled();
  });

  /**
   * Once the action is running there is nothing left to cancel, and closing would hide the outcome.
   * Both buttons lock so a second Return cannot fire it twice.
   */
  it("cannot be dismissed or fired twice while busy", async () => {
    const { onConfirm, onCancel } = open({ busy: true });

    await userEvent.keyboard("{Escape}");
    await userEvent.click(screen.getByTestId("confirm-accept"));

    expect(onCancel).not.toHaveBeenCalled();
    expect(onConfirm).not.toHaveBeenCalled();
    expect(screen.getByTestId("confirm-accept")).toBeDisabled();
    expect(screen.getByTestId("confirm-cancel")).toBeDisabled();
  });

  it("shows the busy label in place of the confirming one", () => {
    open({ busy: true, busyLabel: "Deleting…" });

    expect(screen.getByTestId("confirm-accept")).toHaveTextContent("Deleting…");
    expect(screen.queryByText("Yes, delete 1,204 rows")).not.toBeInTheDocument();
  });

  /**
   * Announced as an alert dialog and described by its own body, so a screen reader reads what is
   * about to happen rather than only the button labels.
   */
  it("announces itself as a modal alert with a name and a description", () => {
    open();

    const dialog = screen.getByRole("alertdialog");

    expect(dialog).toHaveAttribute("aria-modal", "true");
    expect(dialog).toHaveAccessibleName("Delete the generated data?");
    expect(dialog).toHaveAccessibleDescription("This cannot be undone.");
  });

  /** Tab must not walk out into the page behind, where the next Return presses something unseen. */
  it("keeps Tab inside the dialog", async () => {
    open();

    const cancel = screen.getByTestId("confirm-cancel");
    const accept = screen.getByTestId("confirm-accept");

    await userEvent.tab();
    expect(accept).toHaveFocus();

    await userEvent.tab();
    expect(cancel).toHaveFocus();

    await userEvent.tab({ shift: true });
    expect(accept).toHaveFocus();
  });
});
