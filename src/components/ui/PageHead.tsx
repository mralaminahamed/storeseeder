import React from "react";

interface PageHeadProps {
  /** The page's name, as an `<h1>`. One per page. */
  title: React.ReactNode;
  /** One sentence on what the page is for. Optional — Settings needs one, a wizard step may not. */
  description?: React.ReactNode;
  /** Anything that belongs on the right: a primary action, a filter, a status pill. */
  children?: React.ReactNode;
}

/**
 * The heading every page opens with.
 *
 * Four pages had grown the same `fp-page-head > div > h1.fp-h1 + p.fp-sub` by hand, which is three
 * more chances than necessary to get it slightly different — and the fifth page got it *wrong*,
 * inventing `fp-page-title` and `fp-page-sub`, class names that match nothing in the stylesheet, so
 * the title rendered as unstyled body text. Nothing caught it: invented class names are valid
 * TypeScript, valid HTML, and invisible to every linter here.
 *
 * A component removes the opportunity. There is one heading shape, and the way to get it is to use
 * this rather than to remember two class names.
 */
export function PageHead({ title, description, children }: PageHeadProps) {
  return (
    <div className="fp-page-head">
      <div>
        <h1 className="fp-h1">{title}</h1>
        {description && <p className="fp-sub">{description}</p>}
      </div>
      {children}
    </div>
  );
}

export default PageHead;
