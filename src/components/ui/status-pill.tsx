import React from "react";

import { tone } from "@/lib/tone";

interface StatusPillProps {
	children: React.ReactNode;
}

export function StatusPill({ children }: StatusPillProps) {
	// Only a primitive child carries text to derive a tone from; anything else
	// would stringify to "[object Object]" and always land on the neutral tone.
	const text =
		"string" === typeof children || "number" === typeof children
			? String(children)
			: "";
	const k = tone(text);
	return (
		<span className={`fp-status tone-${k}`}>
			<span className="fp-dot" />
			{children}
		</span>
	);
}
