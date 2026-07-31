import React from "react";
import { describe, expect, it } from "@jest/globals";
import { render, screen } from "@testing-library/react";

import { Skeleton, SkeletonText } from "./Skeleton";

/**
 * A skeleton stands in for layout that is about to exist, and it must be invisible to
 * assistive technology: reading out a dozen empty boxes is worse than silence, which is why
 * callers pair it with their own `sr-only` status text.
 */
describe("Skeleton", () => {
  it("is hidden from assistive technology", () => {
    const { container } = render(<Skeleton />);

    expect(container.firstChild).toHaveAttribute("aria-hidden", "true");
  });

  it("fills its container by default", () => {
    const { container } = render(<Skeleton />);

    expect(container.firstChild).toHaveStyle({ width: "100%", height: "14px" });
  });

  it("takes explicit dimensions, numbers as pixels", () => {
    const { container } = render(<Skeleton width={38} height={38} radius={10} />);

    expect(container.firstChild).toHaveStyle({
      width: "38px",
      height: "38px",
      borderRadius: "10px",
    });
  });

  it("accepts a string dimension for a percentage", () => {
    const { container } = render(<Skeleton width="58%" />);

    expect(container.firstChild).toHaveStyle({ width: "58%" });
  });

  it("keeps the caller's class alongside its own", () => {
    const { container } = render(<Skeleton className="fp-plugin-ic" />);

    expect(container.firstChild).toHaveClass("fp-skel", "fp-plugin-ic");
  });

  it("has no stray whitespace in its class when none is passed", () => {
    const { container } = render(<Skeleton />);

    expect((container.firstChild as HTMLElement).className).toBe("fp-skel");
  });
});

describe("SkeletonText", () => {
  it("renders the requested number of lines", () => {
    const { container } = render(<SkeletonText lines={4} />);

    expect(container.querySelectorAll(".fp-skel")).toHaveLength(4);
  });

  it("defaults to three lines", () => {
    const { container } = render(<SkeletonText />);

    expect(container.querySelectorAll(".fp-skel")).toHaveLength(3);
  });

  /**
   * The last line is short, the way a paragraph ends. Uniform full-width bars read as a
   * table, not as prose.
   */
  it("shortens the last line", () => {
    const { container } = render(<SkeletonText lines={3} />);
    const lines = container.querySelectorAll<HTMLElement>(".fp-skel");

    expect(lines[0]).toHaveStyle({ width: "100%" });
    expect(lines[2]).toHaveStyle({ width: "58%" });
  });

  it("is hidden from assistive technology as a whole", () => {
    render(<SkeletonText />);

    // No accessible content at all: the caller supplies the live status text.
    expect(screen.queryByRole("status")).not.toBeInTheDocument();
  });
});
