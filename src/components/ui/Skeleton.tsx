import React from "react";

/**
 * A placeholder for content that has been asked for but has not arrived.
 *
 * One primitive rather than a shape per screen: the plugins page had already grown its
 * own hand-built placeholder cards, and the next three screens that needed one would
 * each have grown a different one.
 *
 * A skeleton is for *layout that is about to exist* — it stands in for the shape of the
 * thing, so nothing jumps when the data lands. Where the wait has no predictable shape
 * (a generation run), a spinner is the honest choice, and `.fp-spinner` stays for that.
 *
 * Everything is `aria-hidden`: to a screen reader the region is busy, and reading out a
 * dozen empty boxes is worse than silence. Callers mark the live region instead.
 */
export function Skeleton({
  width,
  height = 14,
  radius,
  className = "",
  style,
}: {
  width?: number | string;
  height?: number | string;
  radius?: number | string;
  className?: string;
  style?: React.CSSProperties;
}) {
  return (
    <span
      aria-hidden="true"
      className={`fp-skel ${className}`.trim()}
      style={{
        width: width ?? "100%",
        height,
        borderRadius: radius,
        ...style,
      }}
    />
  );
}

/**
 * Several lines of it, the last one short, the way a paragraph ends.
 */
export function SkeletonText({
  lines = 3,
  className = "",
}: {
  lines?: number;
  className?: string;
}) {
  return (
    <span className={`fp-skel-text ${className}`.trim()} aria-hidden="true">
      {Array.from({ length: lines }).map((_, i) => (
        <Skeleton
          key={i}
          height={11}
          width={i === lines - 1 ? "58%" : "100%"}
        />
      ))}
    </span>
  );
}
