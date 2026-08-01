import React from "react";
import { describe, expect, it, jest } from "@jest/globals";
import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";

import type { PlatformInfo, PlatformState } from "@/types";
import { PlatformProvider } from "@/providers/PlatformProvider";
import { PlatformSelect } from "./PlatformSelect";

/**
 * The topbar control deciding which store a run writes to — the most consequential choice
 * in the admin, since picking wrong writes rows into the wrong store and nothing about that
 * failure is visible afterwards.
 *
 * It reads its state from PlatformProvider, which seeds from the payload the server inlines,
 * so these render through the provider rather than mocking the hook. `setTarget` goes
 * through apiFetch, which is mocked because the assertion is about what the component sends,
 * not about the transport.
 */
jest.mock("@wordpress/api-fetch", () => ({
  __esModule: true,
  default: jest.fn(() => Promise.resolve({})),
}));

function platform(id: string, label: string, active = true): PlatformInfo {
  return { id, label, active, version: "1.0.0", supports: {}, fields: {} };
}

function mount(state: Partial<PlatformState>) {
  window.storeseederApi = {
    ...window.storeseederApi,
    platforms: {
      platforms: [],
      stored: "",
      resolved: null,
      ambiguous: false,
      ...state,
    },
  };

  return render(
    <PlatformProvider>
      <PlatformSelect />
    </PlatformProvider>,
  );
}

describe("PlatformSelect", () => {
  /**
   * A select with one option is a decision the user does not have. Showing it would imply
   * otherwise, and the sidebar and Settings both still name the target.
   */
  it("renders nothing with one platform active", () => {
    mount({
      platforms: [platform("fluent-cart", "Fluent Cart")],
      resolved: "fluent-cart",
    });

    expect(screen.queryByTestId("platform-select")).not.toBeInTheDocument();
  });

  it("renders nothing with no platform active", () => {
    mount({ platforms: [platform("fluent-cart", "Fluent Cart", false)] });

    expect(screen.queryByTestId("platform-select")).not.toBeInTheDocument();
  });

  describe("with two platforms active", () => {
    const two = {
      platforms: [
        platform("fluent-cart", "Fluent Cart"),
        platform("stub-cart", "Stub Cart"),
      ],
      resolved: null,
      ambiguous: true,
    };

    it("appears, and asks for attention while no choice is made", () => {
      mount(two);

      expect(
        screen.getByRole("combobox", { name: "Target platform" }),
      ).toHaveAttribute("data-needs-choice", "true");
    });

    it("is the app's own control, not a native select", () => {
      const { container } = mount(two);

      // A native <select> renders its list in the OS chrome: a light menu over a dark app,
      // with no room for the icon or the check mark the rest of the admin uses.
      expect(container.querySelector("select")).not.toBeInTheDocument();
      expect(container.querySelector(".fp-select.is-pill")).toBeInTheDocument();
    });

    it("offers Auto plus each active platform", async () => {
      mount(two);

      await userEvent.click(screen.getByRole("combobox"));

      expect(screen.getAllByRole("option").map((o) => o.textContent)).toEqual([
        "Auto",
        "Fluent Cart",
        "Stub Cart",
      ]);
    });

    it("keeps the first option named Auto, whatever it resolved to", async () => {
      mount({ ...two, resolved: "fluent-cart", ambiguous: false });

      await userEvent.click(screen.getByRole("combobox"));

      const options = screen.getAllByRole("option");

      expect(options[0]).toHaveAccessibleName("Auto");
      // And not renamed after the store it happens to be pointing at.
      expect(
        screen.queryByRole("option", { name: /Auto · / }),
      ).not.toBeInTheDocument();
    });

    /**
     * The half the earlier fix missed, found by the documentation screenshot run rather than by
     * this suite: it asserted the Auto option against `resolved`, and the defect was in `stored`.
     *
     * The Auto entry took its label from `targetLabel( state, selected )`, which returns the
     * *selected* platform's name whenever one is chosen. So picking Stub Cart relabelled the Auto
     * entry "Stub Cart" and the list read "Stub Cart · Fluent Cart · Stub Cart" — Auto still
     * present, no longer reachable by name, and one store apparently offered twice.
     */
    it("keeps the first option named Auto after a platform is chosen", async () => {
      mount({ ...two, stored: "stub-cart", resolved: "stub-cart", ambiguous: false });

      await userEvent.click(screen.getByRole("combobox"));

      expect(screen.getAllByRole("option").map((o) => o.textContent)).toEqual([
        "Auto",
        "Fluent Cart",
        "Stub Cart",
      ]);
    });

    it("sends the platform id when one is chosen", async () => {
      const apiFetch = (await import("@wordpress/api-fetch"))
        .default as unknown as ReturnType<typeof jest.fn>;

      mount(two);

      await userEvent.click(screen.getByRole("combobox"));
      await userEvent.click(screen.getByRole("option", { name: "Stub Cart" }));

      expect(apiFetch).toHaveBeenCalledWith({
        path: "/storeseeder/v1/platforms/target",
        method: "POST",
        data: { platform: "stub-cart" },
      });
    });

    /**
     * Found by this test, and fixed in production rather than mocked around: the provider
     * used to assign the REST body to state unchecked, so a body without `platforms` left
     * `state.platforms` undefined and the next render threw — a white screen from a
     * successful-looking request. The mock returns `{}`, which is exactly that case.
     */
    it("survives a response that is not a platform state", async () => {
      mount(two);

      await userEvent.click(screen.getByRole("combobox"));
      await userEvent.click(screen.getByRole("option", { name: "Stub Cart" }));

      // Still rendered, still showing the optimistic choice.
      expect(screen.getByRole("combobox")).toBeInTheDocument();
      expect(screen.getByRole("combobox")).toHaveTextContent("Stub Cart");
    });

    it("shows the chosen platform once stored", () => {
      mount({ ...two, stored: "stub-cart", resolved: "stub-cart", ambiguous: false });

      expect(screen.getByRole("combobox")).toHaveTextContent("Stub Cart");
      expect(screen.getByRole("combobox")).not.toHaveAttribute(
        "data-needs-choice",
      );
    });
  });
});
