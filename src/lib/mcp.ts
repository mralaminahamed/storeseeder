/**
 * What an AI client may do through MCP.
 *
 * Three switches with three different risks: whether the AI surface exists at all,
 * whether an agent may preview (reads nothing, writes nothing), and whether it may
 * generate (writes rows someone then has to clear out). Stored site-wide and changeable
 * only by an administrator, like the access roles they sit beside.
 *
 * The initial state is inlined at page load rather than fetched, because it cannot change
 * while the page is open unless this page changes it.
 */

import type { McpStatus } from "@/types";

function api(): { restUrl: string; nonce: string } {
  return {
    restUrl: window.storeseederApi?.restUrl ?? "",
    nonce: window.storeseederApi?.restNonce ?? "",
  };
}

/** The toggles, as the endpoint names them. */
export type McpToggle = "enabled" | "preview" | "generate";

/**
 * Read a status payload without trusting its shape — the same reasoning as
 * `parseSampleDataStatus`: a changed endpoint should read as unavailable, not throw past
 * the caller's error handling.
 */
export function parseMcpStatus(body: unknown): McpStatus {
  const source =
    null !== body && "object" === typeof body
      ? (body as Record<string, unknown>)
      : {};

  const num = (value: unknown): number =>
    "number" === typeof value && Number.isFinite(value) ? value : 0;

  return {
    available: true === source.available,
    abilities_api: true === source.abilities_api,
    adapter: true === source.adapter,
    abilities: num(source.abilities),
    tools: num(source.tools),
    enabled: true === source.enabled,
    preview: true === source.preview,
    generate: true === source.generate,
    can_manage: true === source.can_manage,
    route: "string" === typeof source.route ? source.route : "",
  };
}

export async function fetchMcp(): Promise<McpStatus> {
  const { restUrl, nonce } = api();
  const res = await fetch(`${restUrl}mcp`, {
    headers: { "X-WP-Nonce": nonce },
  });

  if (!res.ok) throw new Error(`HTTP ${res.status}`);

  return parseMcpStatus(await res.json());
}

/**
 * Flip one switch.
 *
 * One key per request, not the whole object: the server treats an absent key as "leave
 * it alone", so two administrators changing different switches cannot undo each other.
 */
export async function saveMcpToggle(
  toggle: McpToggle,
  value: boolean,
): Promise<McpStatus> {
  const { restUrl, nonce } = api();
  const res = await fetch(`${restUrl}mcp`, {
    method: "POST",
    headers: { "Content-Type": "application/json", "X-WP-Nonce": nonce },
    body: JSON.stringify({ [toggle]: value }),
  });

  if (!res.ok) throw new Error(`HTTP ${res.status}`);

  return parseMcpStatus(await res.json());
}
