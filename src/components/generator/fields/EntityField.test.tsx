import React from "react";
import { describe, expect, it, jest, beforeEach } from "@jest/globals";
import { render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";

import { EntityField } from "./EntityField";

jest.mock("@wordpress/api-fetch", () => ({
  __esModule: true,
  default: jest.fn(),
}));

/**
 * The picker exists because `customer_id` was a number box: choosing a customer meant knowing
 * their id. The property that matters most here is the split — a *name* is shown, an *id* is
 * sent — because the locale picker once stored a display label and tried to send it to the API.
 */
describe("EntityField", () => {
  const results = [
    { id: 42, label: "Ada Lovelace — ada@example.test" },
    { id: 43, label: "Grace Hopper — grace@example.test" },
  ];

  async function apiMock() {
    return (await import("@wordpress/api-fetch"))
      .default as unknown as ReturnType<typeof jest.fn>;
  }

  beforeEach(async () => {
    (await apiMock()).mockReset();
    (await apiMock()).mockResolvedValue({ results });
  });

  it("sends the id, not the label", async () => {
    const onChange = jest.fn();
    render(<EntityField value="" entity="customer" onChange={onChange} />);

    await userEvent.click(screen.getByRole("combobox"));
    await waitFor(() =>
      expect(screen.getByText("Ada Lovelace — ada@example.test")).toBeInTheDocument(),
    );
    await userEvent.click(screen.getByText("Ada Lovelace — ada@example.test"));

    expect(onChange).toHaveBeenCalledWith("42");
    // The label must never reach the caller — that is the bug this shape prevents.
    expect(onChange).not.toHaveBeenCalledWith(
      expect.stringContaining("Ada Lovelace"),
    );
  });

  it("shows the label once one has been chosen", async () => {
    const onChange = jest.fn();
    const { rerender } = render(
      <EntityField value="" entity="customer" onChange={onChange} />,
    );

    await userEvent.click(screen.getByRole("combobox"));
    await waitFor(() =>
      expect(screen.getByText("Ada Lovelace — ada@example.test")).toBeInTheDocument(),
    );
    await userEvent.click(screen.getByText("Ada Lovelace — ada@example.test"));

    // The parent stores the id and hands it back, the way the params bag does.
    rerender(<EntityField value="42" entity="customer" onChange={onChange} />);

    expect(screen.getByRole("combobox")).toHaveValue("Ada Lovelace — ada@example.test");
  });

  /**
   * A value restored from a saved configuration arrives with no label. Rather than leaving a bare
   * number on screen, the field asks the store what it is called.
   */
  it("resolves an id that arrived without a label", async () => {
    (await apiMock()).mockResolvedValue({
      results: [{ id: 99, label: "Deluxe Device — ZR-1 (#99)" }],
    });

    render(<EntityField value="99" entity="product" onChange={jest.fn()} />);

    await waitFor(() =>
      expect(screen.getByRole("combobox")).toHaveValue("Deluxe Device — ZR-1 (#99)"),
    );
  });

  it("leaves the id showing when the store cannot name it", async () => {
    (await apiMock()).mockResolvedValue({ results: [] });

    render(<EntityField value="99" entity="product" onChange={jest.fn()} />);

    await waitFor(() => expect(screen.getByRole("combobox")).toHaveValue("99"));
  });

  it("treats a typed number as an id", async () => {
    const onChange = jest.fn();
    render(<EntityField value="" entity="product" onChange={onChange} />);

    await userEvent.type(screen.getByRole("combobox"), "77");

    expect(onChange).toHaveBeenLastCalledWith("77");
  });

  /**
   * Typing a name is a search, not a value. Emitting the text as an id would send "ada" where an
   * integer belongs.
   */
  it("does not treat typed text as an id", async () => {
    const onChange = jest.fn();
    render(<EntityField value="" entity="customer" onChange={onChange} />);

    await userEvent.type(screen.getByRole("combobox"), "ada");

    expect(onChange).toHaveBeenLastCalledWith("");
  });

  it("stops showing a label once the value moves off it", async () => {
    const onChange = jest.fn();
    const { rerender } = render(
      <EntityField value="" entity="customer" onChange={onChange} />,
    );

    await userEvent.click(screen.getByRole("combobox"));
    await waitFor(() =>
      expect(screen.getByText("Ada Lovelace — ada@example.test")).toBeInTheDocument(),
    );
    await userEvent.click(screen.getByText("Ada Lovelace — ada@example.test"));

    // Something else set the value — a preset, or another control.
    rerender(<EntityField value="7" entity="customer" onChange={onChange} />);

    expect(screen.getByRole("combobox")).toHaveValue("7");
  });

  /**
   * A store whose driver cannot search, or that is briefly unreachable, must not become a store
   * you cannot target.
   */
  it("stays usable when the lookup fails", async () => {
    (await apiMock()).mockRejectedValue(new Error("network"));

    const onChange = jest.fn();
    render(<EntityField value="" entity="product" onChange={onChange} />);

    await userEvent.click(screen.getByRole("combobox"));

    await waitFor(() =>
      expect(screen.getByText(/Type an ID instead/)).toBeInTheDocument(),
    );

    await userEvent.type(screen.getByRole("combobox"), "5");

    expect(onChange).toHaveBeenLastCalledWith("5");
  });

  it("asks the endpoint for the kind of record it was given", async () => {
    render(<EntityField value="" entity="product" onChange={jest.fn()} />);

    await userEvent.click(screen.getByRole("combobox"));

    await waitFor(async () =>
      expect(await apiMock()).toHaveBeenCalledWith(
        expect.objectContaining({
          path: expect.stringContaining("resource=product"),
        }),
      ),
    );
  });
});
