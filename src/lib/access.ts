/**
 * Who may generate data.
 *
 * Two gates, deliberately not one. The capability — `manage_options` unless a filter
 * changes it — is code-controlled. The allowed roles are an administrator's choice,
 * stored site-wide, and only an administrator can change them: a role granted through
 * this setting must not be able to grant more roles, or the setting escalates itself.
 */

export interface AccessState {
  /** The capability that grants access, `manage_options` unless filtered. */
  capability: string;
  /** True when `storeseeder_capability` changed it from the default. */
  filtered: boolean;
  /** The role that always has access and is never listed as a choice. */
  adminRole: string;
  /** Roles that may be granted, slug => display name. */
  roles: Record<string, string>;
  /** Slugs currently granted. */
  allowedRoles: string[];
  /** Whether the current user may change any of this. */
  canManage: boolean;
}

function api(): { restUrl: string; nonce: string } {
  return {
    restUrl: window.storeseederApi?.restUrl ?? "",
    nonce: window.storeseederApi?.restNonce ?? "",
  };
}

/**
 * Read an access payload without trusting its shape — the same reasoning as
 * `parseSampleDataStatus`: a changed endpoint should read as empty, not throw past the
 * caller's error handling.
 */
function parse(body: unknown): AccessState {
  const source =
    null !== body && "object" === typeof body
      ? (body as Record<string, unknown>)
      : {};

  const roles: Record<string, string> = {};
  if (null !== source.roles && "object" === typeof source.roles) {
    for (const [slug, name] of Object.entries(
      source.roles as Record<string, unknown>,
    )) {
      if ("string" === typeof name) roles[slug] = name;
    }
  }

  return {
    capability:
      "string" === typeof source.capability ? source.capability : "manage_options",
    filtered: true === source.filtered,
    adminRole:
      "string" === typeof source.adminRole ? source.adminRole : "administrator",
    roles,
    allowedRoles: Array.isArray(source.allowedRoles)
      ? source.allowedRoles.filter((r): r is string => "string" === typeof r)
      : [],
    canManage: true === source.canManage,
  };
}

export async function fetchAccess(): Promise<AccessState> {
  const { restUrl, nonce } = api();
  const res = await fetch(`${restUrl}access`, {
    headers: { "X-WP-Nonce": nonce },
  });

  if (!res.ok) throw new Error(`HTTP ${res.status}`);

  return parse(await res.json());
}

export async function saveAllowedRoles(roles: string[]): Promise<AccessState> {
  const { restUrl, nonce } = api();
  const res = await fetch(`${restUrl}access`, {
    method: "POST",
    headers: { "Content-Type": "application/json", "X-WP-Nonce": nonce },
    body: JSON.stringify({ roles }),
  });

  if (!res.ok) throw new Error(`HTTP ${res.status}`);

  return parse(await res.json());
}
