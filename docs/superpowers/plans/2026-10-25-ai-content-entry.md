# AI Content Entry Implementation Plan

> **For agentic workers:** Use superpowers:executing-plans to implement this plan task by task when the user resumes implementation. All checkboxes below describe future work.

**Status:** Planned only. The user explicitly postponed implementation on 2026-10-25. Resume in `C:\Projects\onsmakina.com\webphp-personal`; do not modify the ONS Makina repository.

**Goal:** Let an AI discover and enter CMS content through a local MCP server, with reusable validation and previewable bulk JSON imports.

**Architecture:** Panel HTTP requests, the JSON import command, and MCP tools will share application-level content operations. MCP must not expose arbitrary SQL or direct database/file access. Each copied website keeps its own database, configuration, uploads, and authorization.

**Tech stack:** PHP 8.4, PDO SQLite, existing panel authorization and validation. Start with local MCP stdio. Select a maintained official MCP SDK and a compatible runtime during implementation; do not implement the MCP protocol manually. A separate adapter dependency is optional for CMS users; Composer must not become a CMS installation requirement.

**Spec:** The scope, contracts, and acceptance criteria below are the design specification. Proposed new paths are planning decisions, not existing features.

## Scope and constraints

- Preserve the working Personal example, manual frontend templates, existing panel, vendor packages, and live SQLite data.
- First release: discover content types/fields, list/read/create/update pages and data records, and manage their translations.
- Bulk input starts with JSON. CSV is a later adapter after the JSON contract is stable; it must reuse the same operations.
- First MCP transport is local stdio. Remote HTTP, OAuth, public deployment, and centralized multi-site management are outside this release.
- Media is a follow-up phase. The first release must reject unsupported image/file mutations explicitly, rather than accepting arbitrary paths or URLs.
- Do not expose user/role administration, settings containing secrets, SQL execution, schema changes, deletion, remote downloads, or general file access as tools.
- Preserve table/field/resource allowlists, roles, soft deletion, translations, string IDs, and HTML output policy. Panel session and CSRF checks remain intact.
- Automation uses a distinct, locally configured least-privilege principal linked to an active CMS user. Never use an authentication bypass, the fixed demo administrator, or model-supplied role/user IDs.
- Private adapter configuration and credentials stay outside Git and tool responses. MCP stdout contains protocol messages only; diagnostics go to stderr with secrets masked.
- Existing HTTP list responses keep `data/itemsCount`; the service/MCP result envelope may be separate and documented.
- No live-content write tests. Extend the existing disposable-fixture suite where possible; add only a focused MCP protocol test if it needs a separate runtime.

## Proposed file responsibilities

| Path | Responsibility |
|---|---|
| `panel/include/content_service.php` | Shared content discovery, reads, validation, authorized writes, and translation operations |
| `panel/api/security.php`, `panel/api/database.php`, `panel/api/lang.php` | Existing integration points to inspect and delegate to the service without changing public contracts |
| `tools/content-import.php` | CLI-only JSON preview and apply commands |
| `tools/content-bridge.php` | CLI-only structured bridge between the MCP adapter and shared PHP operations |
| `mcp/` | Official-SDK adapter, locked dependencies, example client setup, and protocol tests |
| `config/automation.example.php` | Non-secret example principal, resource, action, and path restrictions; real settings remain private |
| `tests/http-workflows.py` | Existing fixture-backed regression and shared-operation/import checks |
| `tools/create-site.php`, `tools/verify.php` | Explicit export and verification policy for optional automation components |

Inspect the actual call graph before extraction. Keep transport parsing, session/CSRF checks, and HTTP response formatting at the HTTP boundary; move business validation and authorization into reusable operations. Do not create a second set of CRUD rules.

## Review focus

1. A principal loses its role or is disabled between preview and apply: recheck current access at apply time.
2. A record changes after preview: reject the stale plan without partial writes.
3. An import mixes languages, temporary references, and invalid fields: report item-level validation errors and commit nothing.
4. Model instructions embedded in existing content: return them as untrusted data, never turn them into permissions or executable commands.
5. A new starter omits private configuration or optional adapter files: the panel must still install and run normally with automation disabled.

## Task 1: Shared authorized content operations

- [ ] Map the actual API write/read/translation callers and inspect existing validation before changing code. Use graft if this repository has a valid index; otherwise inspect targeted application files. Do not use another site's graph as this site's source of truth.
- [ ] Extend the existing fixture suite with panel-versus-service cases for allowed fields, forbidden resources, disabled users, role changes, translations, invalid IDs, and soft-deleted records. Confirm the new integration cases fail before adding the service.
- [ ] Implement `panelContentExecute(array $principal, string $operation, array $input): array` in `panel/include/content_service.php`. Supported operations initially: `describe`, `list`, `get`, `create`, `update`, `translate`. Resolve current user permissions server-side on each operation; reject every unsupported operation and field.
- [ ] Delegate applicable HTTP content handlers to this service while preserving session/CSRF enforcement, error masking, pagination, existing response shapes, and translation behavior. Do not broaden generic API access to match MCP scope.
- [ ] Run changed-file PHP lint, `python tests/http-workflows.py`, and `php tools/verify.php`. Commit the extraction only after the existing panel and new service cases pass.

## Task 2: JSON import with preview and atomic apply

- [ ] Add fixture cases for a valid multi-record import, mixed invalid fields/languages, duplicate external keys, missing references, stale previews, changed permissions, rollback, and accidental repeated apply. Verify failures before implementation.
- [ ] Define a versioned JSON contract with `schema_version: 1`, `items`, stable item `key`, allowlisted `resource`, `action` (`create` or `update`), optional target string `id`, `fields`, and translations. References between newly created records use item keys and are resolved only within the validated batch. Do not accept SQL, arbitrary table names, or arbitrary file paths.
- [ ] Implement CLI-only `php tools/content-import.php preview --file=<json>` to return field errors or an opaque plan ID with a change summary. Store the normalized plan outside the web root's public access, scoped to the installation/principal, with input hash, affected-record fingerprints, and a 15-minute expiry. No CMS records change during preview.
- [ ] Implement `php tools/content-import.php apply --plan=<id>` using that exact stored plan. Recheck access, expiry, input integrity, and record fingerprints, then apply all writes in one SQLite transaction. Record completion atomically with the writes so retry cannot duplicate creates. Return the original result for a completed plan; reject invalid or stale plans. Any supporting table requires a versioned migration and fresh-install schema update, never a seed reimport.
- [ ] Keep preview/apply as explicit operations; do not add a hidden confirmation prompt. Document how a caller presents the summary and obtains approval when required by its host client.
- [ ] Verify the fixture cases, lint, package/database checks, and unchanged live database. Commit the importer and contract documentation together.

## Task 3: Optional local MCP adapter

- [ ] Verify current official MCP SDK/runtime and client configuration documentation before selecting the adapter language. Prefer an already available runtime, pin dependencies, and document the optional installation separately from the PHP CMS. Record the choice and license/source.
- [ ] Add protocol tests for initialization, tool discovery, schema validation, malformed requests, structured errors, stdout cleanliness, forbidden operations, and a real fixture-backed create/read/update/translation cycle.
- [ ] Implement `tools/content-bridge.php` as CLI-only. It loads an explicit installation's private automation configuration, derives the principal itself, and delegates to the service/import operations. Launch PHP with an argument array and an explicit working directory; never interpolate model input into shell commands.
- [ ] Expose narrowly scoped tools: `cms_describe`, `cms_list`, `cms_get`, `cms_create`, `cms_update`, `cms_translate`, `cms_import_preview`, and `cms_import_apply`. Document JSON schemas, limits, and error codes. Apply bounded pagination, batch sizes, input lengths, and execution time; never return passwords, tokens, SQL, or filesystem paths in errors.
- [ ] Register read/write tool annotations accurately, but enforce permissions in PHP rather than trusting annotations or the model. The apply tool requires a valid preview plan; ordinary create/update tools are explicit writes under the configured principal.
- [ ] Run official-SDK protocol tests and the existing fixture suite. Verify the PHP application works with no MCP runtime installed. Document a tested local-client configuration without embedding credentials, then commit.

## Task 4: Reuse and handoff

- [ ] Define explicit clean-site export behavior: include shared PHP operations and safe examples; keep automation disabled by default. Include the optional MCP adapter only through a documented export choice. Never copy credentials, preview plans, runtime dependencies/caches, sessions, database files, or demo content.
- [ ] Extend generated-starter fixture checks to verify disabled-by-default automation, private-file HTTP denial, isolated credentials/database selection, and normal panel operation without the adapter.
- [ ] Update `README.md`, `AGENTS.md`, `docs/mimari.md`, and `docs/yeni-site.md` with exact tested commands, tool examples, private-config location, recovery/backup procedure, limitations, and dependency requirements. Keep project documentation English and website content Turkish.
- [ ] Run the relevant checks once, inspect the diff and Git status, and commit the completed work. Do not push unless requested.

## Follow-up backlog (not part of initial implementation)

- CSV-to-JSON adapter with a preview of column/language mapping.
- Record-scoped media listing, local file attachment, thumbnail previews, and upload validation through existing media authorization. No shared-root browsing, arbitrary paths, active documents, or remote URL import.
- Remote Streamable HTTP only if a concrete deployment need arises; design authentication, origin validation, transport security, and deployment restrictions separately.

## Resume checklist

1. Open `C:\Projects\onsmakina.com\webphp-personal` as the workspace, not the ONS Makina folder.
2. Read `AGENTS.md`, `docs/mimari.md`, and this plan; run `git status --short`.
3. Confirm the user is now asking to implement. This planning session explicitly stops before implementation.
4. Start Task 1 using disposable SQLite fixtures. No need to reread all vendor sources or recreate the demo.

## Protocol references

- [MCP transports](https://modelcontextprotocol.io/specification/2025-11-25/basic/transports)
- [MCP tools](https://modelcontextprotocol.io/specification/2025-11-25/server/tools)
- [MCP authorization](https://modelcontextprotocol.io/specification/2025-11-25/basic/authorization)

These references informed the design; recheck the current official SDK and specification during implementation.
