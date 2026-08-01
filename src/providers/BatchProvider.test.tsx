import React from "react";
import { beforeEach, describe, expect, it, jest } from "@jest/globals";
import { act, render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";

import { BatchProvider, useBatch, type BatchItem } from "./BatchProvider";

/**
 * The queue behind "Add to batch".
 *
 * Written after it shipped having thrown away the run it was given. `BatchItem` held only a route and
 * a count, so queueing a generator discarded every resource parameter, the seed and the metadata
 * switch — a products row configured at $200–$900 ran at the schema default, and the whole parameter
 * column worked on Generate and not on the button beside it. The suite was green throughout, because
 * nothing tested this file.
 *
 * So these assert the *body* that reaches the endpoint, not that a row appears in a list. What a row
 * carries is only interesting because of what gets sent.
 */
jest.mock("@wordpress/api-fetch", () => ({
  __esModule: true,
  default: jest.fn(() => Promise.resolve({ message: "ok" })),
}));

jest.mock("@/providers/StatsProvider", () => ({
  useStats: () => ({ recordRun: jest.fn() }),
}));

jest.mock("@/providers/ToastProvider", () => ({
  useToast: () => ({ toast: jest.fn() }),
}));

jest.mock("@/providers/PlatformProvider", () => ({
  usePlatform: () => ({ target: "woo-commerce" }),
}));

/** A row to queue. `route` defaults to products, since most cases only vary the configuration. */
type QueueSpec = Omit<BatchItem, "route"> & { route?: string };

/** Drives the provider through its own hook — the contract consumers actually use. */
function Harness({ queue }: { queue: QueueSpec[] }) {
  const { batch, add, runAll } = useBatch();

  return (
    <div>
      <span data-testid="rows">{batch.length}</span>
      <span data-testid="counts">{batch.map((b) => b.count).join(",")}</span>
      <button
        type="button"
        onClick={() =>
          queue.forEach((q) =>
            add(q.route ?? "products", q.count, q.params, q.seed, q.meta),
          )
        }
      >
        queue
      </button>
      <button type="button" onClick={() => void runAll()}>
        run
      </button>
    </div>
  );
}

function mount(queue: QueueSpec[]) {
  return render(
    <BatchProvider>
      <Harness queue={queue} />
    </BatchProvider>,
  );
}

async function apiFetchMock() {
  return (await import("@wordpress/api-fetch")).default as unknown as ReturnType<typeof jest.fn>;
}

describe("BatchProvider", () => {
  beforeEach(async () => {
    (await apiFetchMock()).mockClear();
  });

  it("sends the parameters the row was queued with", async () => {
    const apiFetch = await apiFetchMock();

    mount([
      {
        count: 25,
        params: { price_range: { min: 200, max: 900 }, product_type: "digital" },
        seed: "",
        meta: false,
      },
    ]);

    await userEvent.click(screen.getByText("queue"));
    await act(async () => {
      await userEvent.click(screen.getByText("run"));
    });

    expect(apiFetch).toHaveBeenCalledWith(
      expect.objectContaining({
        path: "/storeseeder/v1/products/generate",
        method: "POST",
        data: expect.objectContaining({
          count: 25,
          price_range: { min: 200, max: 900 },
          product_type: "digital",
        }),
      }),
    );
  });

  it("sends the seed as a number", async () => {
    const apiFetch = await apiFetchMock();

    mount([{ count: 5, params: {}, seed: "42", meta: false }]);

    await userEvent.click(screen.getByText("queue"));
    await act(async () => {
      await userEvent.click(screen.getByText("run"));
    });

    expect(apiFetch.mock.calls[0][0]).toMatchObject({ data: { seed: 42 } });
  });

  it("omits the seed when it was left blank", async () => {
    const apiFetch = await apiFetchMock();

    mount([{ count: 5, params: {}, seed: "", meta: false }]);

    await userEvent.click(screen.getByText("queue"));
    await act(async () => {
      await userEvent.click(screen.getByText("run"));
    });

    // Not `seed: NaN`, which is what an unguarded parseInt of "" would have sent.
    expect(apiFetch.mock.calls[0][0]).not.toHaveProperty("data.seed");
  });

  it("sends the metadata switch rather than always false", async () => {
    const apiFetch = await apiFetchMock();

    mount([{ count: 5, params: {}, seed: "", meta: true }]);

    await userEvent.click(screen.getByText("queue"));
    await act(async () => {
      await userEvent.click(screen.getByText("run"));
    });

    expect(apiFetch.mock.calls[0][0]).toMatchObject({ data: { include_meta: true } });
  });

  it("merges two identical queues of the same generator", async () => {
    mount([
      { count: 10, params: { product_type: "digital" }, seed: "", meta: false },
      { count: 15, params: { product_type: "digital" }, seed: "", meta: false },
    ]);

    await userEvent.click(screen.getByText("queue"));

    expect(screen.getByTestId("rows")).toHaveTextContent("1");
    expect(screen.getByTestId("counts")).toHaveTextContent("25");
  });

  /**
   * The other half of the same defect. Merging on the route alone summed the counts of two
   * differently-configured queues into one row and ran whichever configuration arrived first, so
   * queueing cheap products and then expensive ones produced twice as many cheap ones.
   */
  it("keeps two differently-configured queues of one generator apart", async () => {
    const apiFetch = await apiFetchMock();

    mount([
      { count: 10, params: { price_range: { min: 5, max: 20 } }, seed: "", meta: false },
      { count: 15, params: { price_range: { min: 200, max: 900 } }, seed: "", meta: false },
    ]);

    await userEvent.click(screen.getByText("queue"));

    expect(screen.getByTestId("rows")).toHaveTextContent("2");
    expect(screen.getByTestId("counts")).toHaveTextContent("10,15");

    await act(async () => {
      await userEvent.click(screen.getByText("run"));
    });

    expect(apiFetch.mock.calls[0][0]).toMatchObject({
      data: { count: 10, price_range: { min: 5, max: 20 } },
    });
    expect(apiFetch.mock.calls[1][0]).toMatchObject({
      data: { count: 15, price_range: { min: 200, max: 900 } },
    });
  });

  it("runs rows in the order they were queued", async () => {
    const apiFetch = await apiFetchMock();

    mount([
      { route: "products", count: 5, params: {}, seed: "", meta: false },
      { route: "customers", count: 5, params: {}, seed: "", meta: false },
      { route: "orders", count: 5, params: {}, seed: "", meta: false },
    ]);

    await userEvent.click(screen.getByText("queue"));
    await act(async () => {
      await userEvent.click(screen.getByText("run"));
    });

    expect(apiFetch.mock.calls.map((c) => (c[0] as { path: string }).path)).toEqual([
      "/storeseeder/v1/products/generate",
      "/storeseeder/v1/customers/generate",
      "/storeseeder/v1/orders/generate",
    ]);
  });

  it("carries on after a row fails", async () => {
    const apiFetch = await apiFetchMock();

    apiFetch
      .mockImplementationOnce(() => Promise.reject(new Error("nope")))
      .mockImplementation(() => Promise.resolve({ message: "ok" }));

    mount([
      { route: "products", count: 5, params: {}, seed: "", meta: false },
      { route: "customers", count: 5, params: {}, seed: "", meta: false },
    ]);

    await userEvent.click(screen.getByText("queue"));
    await act(async () => {
      await userEvent.click(screen.getByText("run"));
    });

    // Both attempted, and the queue emptied rather than stalling on the failure.
    expect(apiFetch).toHaveBeenCalledTimes(2);
    expect(screen.getByTestId("rows")).toHaveTextContent("0");
  });
});
