import React from "react";
import { describe, expect, it, jest } from "@jest/globals";
import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";

import { Toggle } from "./Toggle";

/**
 * The whole row is the control, not a label wrapping a button — a button is not labelable
 * the way an input is, so the earlier `<label>` around a `role="switch"` made an
 * association the browser never honoured. These assert what that buys: the caption is part
 * of the accessible name, and clicking it toggles.
 */
describe("Toggle", () => {
  it("exposes itself as a switch with its state", () => {
    render(<Toggle checked onChange={jest.fn()} label="Include metadata" />);

    expect(
      screen.getByRole("switch", { name: /Include metadata/ }),
    ).toHaveAttribute("aria-checked", "true");
  });

  it("reports unchecked as false rather than omitting the state", () => {
    render(<Toggle checked={false} onChange={jest.fn()} label="Include metadata" />);

    expect(screen.getByRole("switch")).toHaveAttribute("aria-checked", "false");
  });

  it("takes the hint into its accessible name, so the caption is not orphaned", () => {
    render(
      <Toggle
        checked={false}
        onChange={jest.fn()}
        label="Include metadata"
        hint="Pre-check the toggle on every generator."
      />,
    );

    expect(
      screen.getByRole("switch", { name: /Pre-check the toggle/ }),
    ).toBeInTheDocument();
  });

  it("reports the opposite of its current state when clicked", async () => {
    const onChange = jest.fn();
    const { rerender } = render(
      <Toggle checked={false} onChange={onChange} label="Include metadata" />,
    );

    await userEvent.click(screen.getByRole("switch"));
    expect(onChange).toHaveBeenCalledWith(true);

    rerender(<Toggle checked onChange={onChange} label="Include metadata" />);
    await userEvent.click(screen.getByRole("switch"));
    expect(onChange).toHaveBeenLastCalledWith(false);
  });

  it("toggles when the caption is clicked, not only the switch", async () => {
    const onChange = jest.fn();
    render(
      <Toggle checked={false} onChange={onChange} label="Include metadata" />,
    );

    await userEvent.click(screen.getByText("Include metadata"));

    expect(onChange).toHaveBeenCalledWith(true);
  });

  it("is operable by keyboard", async () => {
    const onChange = jest.fn();
    render(
      <Toggle checked={false} onChange={onChange} label="Include metadata" />,
    );

    await userEvent.tab();
    expect(screen.getByRole("switch")).toHaveFocus();

    await userEvent.keyboard("{Enter}");
    expect(onChange).toHaveBeenCalledWith(true);
  });

  /**
   * The Settings access card renders these read-only for anyone who is not an
   * administrator, because only an administrator may change who has access.
   */
  describe("disabled", () => {
    it("does not fire on click", async () => {
      const onChange = jest.fn();
      render(
        <Toggle
          checked={false}
          disabled
          onChange={onChange}
          label="Editor"
          testId="role-editor"
        />,
      );

      await userEvent.click(screen.getByTestId("role-editor"));

      expect(onChange).not.toHaveBeenCalled();
    });

    it("still reports its state, so the setting remains readable", () => {
      render(<Toggle checked disabled onChange={jest.fn()} label="Editor" />);

      const control = screen.getByRole("switch");

      expect(control).toBeDisabled();
      expect(control).toHaveAttribute("aria-checked", "true");
    });
  });
});
