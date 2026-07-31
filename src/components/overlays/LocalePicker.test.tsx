import React from "react";
import { describe, expect, it, jest } from "@jest/globals";
import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";

import { LocalePicker } from "./LocalePicker";

/**
 * The picker that was broken in a way no unit test could see before: it stored the display
 * **label**, so the chosen value could never be sent to the REST API as-is. Every assertion
 * about `setLocale` here is aimed at that.
 *
 * The five locales come from src/test/setup.ts, which mirrors what the server inlines.
 */
describe("LocalePicker", () => {
  const open = (locale = "en_US") => {
    const setLocale = jest.fn();
    const onClose = jest.fn();

    render(
      <LocalePicker locale={locale} setLocale={setLocale} onClose={onClose} />,
    );

    return { setLocale, onClose };
  };

  it("offers every locale the server accepts", () => {
    open();

    expect(screen.getAllByTestId(/^locale-option-/)).toHaveLength(5);
    expect(screen.getByTestId("locale-option-ja_JP")).toBeInTheDocument();
  });

  it("shows both the label and the code, because the code is what travels", () => {
    open();

    const option = screen.getByTestId("locale-option-ja_JP");

    expect(option).toHaveTextContent("Japanese (Japan)");
    expect(option).toHaveTextContent("ja_JP");
  });

  it("focuses the search field on open, so typing narrows without a click", () => {
    open();

    expect(screen.getByTestId("locale-search")).toHaveFocus();
  });

  it("marks the current locale as selected", () => {
    open("ja_JP");

    expect(screen.getByTestId("locale-option-ja_JP")).toHaveClass("sel");
    expect(screen.getByTestId("locale-option-en_US")).not.toHaveClass("sel");
  });

  describe("search", () => {
    it("narrows by label", async () => {
      open();

      await userEvent.type(screen.getByTestId("locale-search"), "bangla");

      expect(screen.getAllByTestId(/^locale-option-/)).toHaveLength(1);
      expect(screen.getByTestId("locale-option-bn_BD")).toBeInTheDocument();
    });

    it("narrows by code", async () => {
      open();

      await userEvent.type(screen.getByTestId("locale-search"), "ja_JP");

      expect(screen.getAllByTestId(/^locale-option-/)).toHaveLength(1);
    });

    it("says so when nothing matches, rather than showing an empty box", async () => {
      open();

      await userEvent.type(screen.getByTestId("locale-search"), "zzzzz");

      expect(screen.getByTestId("locale-empty")).toBeInTheDocument();
      expect(screen.queryAllByTestId(/^locale-option-/)).toHaveLength(0);
    });
  });

  /**
   * The regression test for the original bug. `setLocale` must receive `ja_JP`, never
   * "Japanese (Japan)".
   */
  it("reports the code and closes when a locale is chosen", async () => {
    const { setLocale, onClose } = open();

    await userEvent.click(screen.getByTestId("locale-option-ja_JP"));

    expect(setLocale).toHaveBeenCalledWith("ja_JP");
    expect(onClose).toHaveBeenCalledTimes(1);
  });

  it("closes on Escape without choosing", async () => {
    const { setLocale, onClose } = open();

    await userEvent.keyboard("{Escape}");

    expect(onClose).toHaveBeenCalledTimes(1);
    expect(setLocale).not.toHaveBeenCalled();
  });

  it("closes on a click on the backdrop, but not inside the dialog", async () => {
    const { onClose } = open();

    await userEvent.click(screen.getByRole("dialog"));
    expect(onClose).not.toHaveBeenCalled();

    await userEvent.click(screen.getByTestId("locale-picker"));
    expect(onClose).toHaveBeenCalledTimes(1);
  });

  it("counts what is available in its footer", () => {
    open();

    expect(screen.getByText("5 locales available")).toBeInTheDocument();
  });

  it("renders nothing to choose when the server sent no locales", () => {
    delete window.storeseederApi;
    open();

    expect(screen.queryAllByTestId(/^locale-option-/)).toHaveLength(0);
  });
});
