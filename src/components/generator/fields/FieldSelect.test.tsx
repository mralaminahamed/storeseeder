import React from "react";
import { describe, expect, it, jest } from "@jest/globals";
import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";

import { FieldSelect } from "./FieldSelect";

/**
 * The app's select. It replaced a native `<select>` in the topbar and is the control the
 * Settings page uses for locale and platform, so two things are load-bearing: it works in
 * **values** rather than labels — the label-to-value mapping is what made the locale picker
 * send display text to the REST API — and it can be dismissed by keyboard.
 */
describe("FieldSelect", () => {
  const OPTIONS = [
    { value: "en_US", label: "English (United States)" },
    { value: "ja_JP", label: "Japanese (Japan)" },
  ];

  it("shows the label for the current value, not the value itself", () => {
    render(
      <FieldSelect value="ja_JP" options={OPTIONS} onChange={jest.fn()} />,
    );

    expect(screen.getByRole("combobox")).toHaveTextContent("Japanese (Japan)");
    expect(screen.getByRole("combobox")).not.toHaveTextContent("ja_JP");
  });

  it("accepts bare strings, where the value is the label", () => {
    render(
      <FieldSelect
        value="physical"
        options={["physical", "digital"]}
        onChange={jest.fn()}
      />,
    );

    expect(screen.getByRole("combobox")).toHaveTextContent("physical");
  });

  it("falls back to the raw value when no option matches", () => {
    // A platform deactivated after being chosen: better to show its id than blank.
    render(
      <FieldSelect value="gone-away" options={OPTIONS} onChange={jest.fn()} />,
    );

    expect(screen.getByRole("combobox")).toHaveTextContent("gone-away");
  });

  it("keeps the list closed until asked", () => {
    render(<FieldSelect value="en_US" options={OPTIONS} onChange={jest.fn()} />);

    expect(screen.queryByRole("listbox")).not.toBeInTheDocument();
    expect(screen.getByRole("combobox")).toHaveAttribute(
      "aria-expanded",
      "false",
    );
  });

  it("opens on click and announces itself as expanded", async () => {
    render(<FieldSelect value="en_US" options={OPTIONS} onChange={jest.fn()} />);

    await userEvent.click(screen.getByRole("combobox"));

    expect(screen.getByRole("listbox")).toBeInTheDocument();
    expect(screen.getByRole("combobox")).toHaveAttribute(
      "aria-expanded",
      "true",
    );
    expect(screen.getAllByRole("option")).toHaveLength(2);
  });

  it("marks the current option as selected", async () => {
    render(<FieldSelect value="ja_JP" options={OPTIONS} onChange={jest.fn()} />);

    await userEvent.click(screen.getByRole("combobox"));

    expect(
      screen.getByRole("option", { name: /Japanese/ }),
    ).toHaveAttribute("aria-selected", "true");
    expect(screen.getByRole("option", { name: /English/ })).toHaveAttribute(
      "aria-selected",
      "false",
    );
  });

  /**
   * The bug this component's shape exists to prevent: callers used to map the chosen label
   * back to a value with `options.find( o => o.label === label )`, and one of them got it
   * wrong.
   */
  it("reports the chosen option's value, never its label", async () => {
    const onChange = jest.fn();
    render(<FieldSelect value="en_US" options={OPTIONS} onChange={onChange} />);

    await userEvent.click(screen.getByRole("combobox"));
    await userEvent.click(screen.getByRole("option", { name: /Japanese/ }));

    expect(onChange).toHaveBeenCalledWith("ja_JP");
  });

  it("closes after a choice", async () => {
    render(<FieldSelect value="en_US" options={OPTIONS} onChange={jest.fn()} />);

    await userEvent.click(screen.getByRole("combobox"));
    await userEvent.click(screen.getByRole("option", { name: /Japanese/ }));

    expect(screen.queryByRole("listbox")).not.toBeInTheDocument();
  });

  it("closes on a click outside without choosing", async () => {
    const onChange = jest.fn();
    render(
      <div>
        <FieldSelect value="en_US" options={OPTIONS} onChange={onChange} />
        <button type="button">elsewhere</button>
      </div>,
    );

    await userEvent.click(screen.getByRole("combobox"));
    await userEvent.click(screen.getByRole("button", { name: "elsewhere" }));

    expect(screen.queryByRole("listbox")).not.toBeInTheDocument();
    expect(onChange).not.toHaveBeenCalled();
  });

  /**
   * Escape is the one a keyboard user reaches for. Without it the only way out was
   * clicking elsewhere, which is not a keyboard action at all.
   */
  it("closes on Escape without choosing", async () => {
    const onChange = jest.fn();
    render(<FieldSelect value="en_US" options={OPTIONS} onChange={onChange} />);

    await userEvent.click(screen.getByRole("combobox"));
    await userEvent.keyboard("{Escape}");

    expect(screen.queryByRole("listbox")).not.toBeInTheDocument();
    expect(onChange).not.toHaveBeenCalled();
  });

  describe("the pill variant", () => {
    it("carries its icon, label and flag for the topbar", () => {
      const { container } = render(
        <FieldSelect
          variant="pill"
          icon="store"
          ariaLabel="Target platform"
          needsChoice
          value="en_US"
          options={OPTIONS}
          onChange={jest.fn()}
        />,
      );

      expect(container.querySelector(".fp-select")).toHaveClass("is-pill");
      expect(
        screen.getByRole("combobox", { name: "Target platform" }),
      ).toHaveAttribute("data-needs-choice", "true");
      expect(container.querySelector(".fp-select-ic")).toBeInTheDocument();
    });

    it("omits the flag when a choice has been made", () => {
      render(
        <FieldSelect
          variant="pill"
          value="en_US"
          options={OPTIONS}
          onChange={jest.fn()}
        />,
      );

      expect(screen.getByRole("combobox")).not.toHaveAttribute(
        "data-needs-choice",
      );
    });
  });
});
