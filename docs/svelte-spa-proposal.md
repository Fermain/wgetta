# Wgetta Svelte SPA: Proposal and Technical Plan

## Overview

Build a modern single-page admin app in Svelte (Vite) to replace the current PHP partials/legacy admin-ajax UI. Make a clean break to a JSON-based plan model and a REST API, improving ergonomics for: Discover → Rules (regex) → Manual refine → Run → Deploy.

## Goals

- Replace CSV plans with first-class JSON plans (versioned schema).
- Provide a clear, ergonomic, and fast UI for discovery, exclusions, manual refinement, execution, and deploy.
- Keep server-side safety guarantees (wget whitelisting/blacklisting, enforced directories).
- Remove legacy admin-ajax endpoints and partials; use REST API only.

## Non-Goals (initial phase)

- Server-side rendered frontend (SSR) or SvelteKit routing.
- Backward compatibility for existing CSV plans or legacy jobs.
- Multi-tenant role management beyond `manage_options` checks.

## Rationale: Svelte (Vite) vs SvelteKit

- SvelteKit prefers owning routing and the document; WP admin uses `wp-admin/admin.php?page=...`, which complicates Kit routing and asset base paths.
- We only need a single embedded app inside WP admin; file-based routing/SSR/endpoints are unnecessary.
- A Vite-built Svelte SPA yields a small, straightforward bundle and simple integration with `wp_enqueue_script`.

## High-Level Architecture

- UI: Svelte SPA (Vite)
  - Source: `admin/app/`
  - Build output: `admin/dist/` (fingerprinted assets)
- Backend (PHP):
  - REST API (namespace `wgetta/v1`) for discover, rules testing, plans CRUD, jobs/logs, and GitLab deploy.
  - Execution engine retained (`Wgetta_Job_Runner`) with command safety, job dirs, manifest, and zip archive.
  - Plan runner reads JSON (no CSV). Legacy partials and admin-ajax are removed.

## Admin Integration

- Add a single admin menu entry `Wgetta` that renders a minimal container:
  - Div mount `#wgetta-app`
  - Enqueue `admin/dist/assets/index.js` and `index.css` (if any)
  - Localize: `WGETTA = { apiBase: '/wp-json/wgetta/v1', nonce: wp_create_nonce('wp_rest'), siteUrl: home_url('/') }`

## Plan JSON Schema (v1)

```json
{
  "schemaVersion": 1,
  "id": "plan-20250101-120000",
  "name": "Marketing site v1",
  "createdAt": 1735723200,
  "updatedAt": 1735723500,
  "command": {
    "baseUrls": ["https://example.com/"],
    "options": [
      { "name": "-nv", "value": null, "editable": true },
      { "name": "--recursive", "value": null, "editable": true },
      { "name": "--level", "value": "2", "editable": true },
      { "name": "--user-agent", "value": "Wgetta/1.0", "editable": true }
    ]
  },
  "rules": [
    { "id": "r1", "pattern": "\\.(pdf|zip)$", "type": "posix", "enabled": true, "note": "Skip big files" },
    { "id": "r2", "pattern": "^https?://example.com/wp-admin/", "type": "posix", "enabled": true }
  ],
  "urls": [
    { "url": "https://example.com/", "included": true,  "source": "discover", "matchedRuleIds": [] },
    { "url": "https://example.com/file.pdf", "included": false, "source": "rule", "matchedRuleIds": ["r1"] },
    { "url": "https://example.com/blog/", "included": false, "source": "manual", "note": "Not needed" }
  ],
  "stats": { "total": 1234, "included": 876, "excluded": 358 }
}
```

Notes:

- `command.options[].editable` drives UI locks; discover/run injectors may add non-editable tokens (e.g., `--spider` for Discover, `-e robots=off`/`--force-directories` for Run).
- `urls` may be paged on the API; the SPA will page/virtualize.

## REST API (namespace: `wgetta/v1`)

- Security: all routes require `current_user_can('manage_options')` and a REST nonce.

Endpoints:

- `POST /discover`
  - Body: `{ command: { baseUrls: string[], options: Option[] } }`
  - Behavior: build argv; inject `--spider`; run job; extract URLs; return plan stub with urls (server stores full set, API may return a sample + totals)
- `POST /rules/test`
  - Body: `{ rules: Rule[], sampleSize?: number, planId?: string }`
  - Behavior: validate POSIX EREs server-side; compute match map over a server-held sample; return `{ includedCount, excludedCount, matches: { url: ruleIds[] } }`
- `POST /plans`
  - Create a plan JSON; returns `{ id }`
- `GET /plans`
  - List plans (id, name, counts, modified)
- `GET /plans/:id`
  - Returns the plan JSON (may page `urls`)
- `PUT /plans/:id`
  - Update fields (name, command, rules, urls inclusion toggles)
- `POST /plans/:id/run`
  - Queue execution using plan JSON; returns `{ jobId }`
- `GET /jobs/recent`
  - Returns recent job summaries (status, counts, zip link)
- `GET /jobs/:id/log?offset=...`
  - Tail stdout; returns `{ content, offset, status, summary? }`
- `GET /git/settings`, `PUT /git/settings`
- `POST /git/test`
- `POST /git/deploy` (body: `{ jobId }`)

## Command Templating & Safety

- UI renders tokens with lock icons when `editable=false`.
- Discover preview always shows `--spider` injected (locked).
- Plan run strips breadth/regex flags from the saved command to prevent divergence from explicit plan and injects:
  - `-e robots=off` (locked)
  - `--force-directories` (locked)
  - server-enforced `--directory-prefix=<job_dir>` (locked)
- Server continues using strict whitelist/blacklist validation and safe `proc_open` execution.

## UI/UX Design (Svelte)

- Stepper: Discover → Rules → Manual → Run → Deploy
  - Discover: command editor (structured tokens), run spider, counts, sample list.
  - Rules: rule chips with enable/disable, notes, presets; server-side test on sample; show totals and highlight matches.
  - Manual: hierarchical host/path tree with virtualization; bulk select/deselect; search filter; reasons badges (rule/manual/discover).
  - Run: live log console, status, summary metrics, recent runs table; CTA to Deploy.
  - Deploy: select job, push to GitLab, capture console output, show branch name used.
- Performance: server stores full URL set; SPA fetches pages or server-side filtered slices. Tree view is virtualized.

## Execution Semantics (unchanged fundamentals)

- Discover always runs with `--spider`.
- Plan execution runs one URL at a time with enforced flags. Initial version remains sequential; a small concurrency knob can be introduced later (guarded).
- Artifacts written: `manifest.txt`, `archive.zip`, `status.json`, `urls.json` (optional), `command.txt`.

## GitLab Deploy

- Reuse current deploy logic: shallow clone, copy files from manifest (and optional metadata), commit, push to branch (template with `{plan_name}`, `{job_id}`, `{date}`).
- Expose as a dedicated step in the SPA; keep a separate GitLab Settings panel.

## Security

- Maintain strict argv validation (allowed/disallowed option sets).
- All REST endpoints gated by capability + nonce.
- Mask tokens in user-visible logs. Use temporary repo dirs under uploads.

## Migration & Clean-up

- Clean break: JSON plans only; remove CSV and legacy admin-ajax code/partials.
- Replace admin menu with a single `Wgetta` entry that mounts the SPA.
- Keep job storage and runner directories intact under uploads; new jobs/plans use JSON.

## Milestones

1) Backend groundwork
   - Add REST routes: discover, rules/test, plans CRUD (without run/deploy yet)
   - Implement plan JSON schema and storage; argv normalizer; effective command previews
2) SPA skeleton
   - Svelte + Vite scaffolding; mount in admin; basic stepper and Discover UI
3) Rules & Manual
   - Rules editor with server-test; manual tree with virtualization and selection
4) Run & Jobs
   - Queue runs; live logs; summaries; recent runs
5) GitLab
   - Settings, test, deploy from SPA
6) Clean-up
   - Remove legacy menus/partials/admin-ajax; docs update

## Open Decisions

- Concurrency for plan execution (default sequential; later 2–4 workers).
- Exact set of non-editable enforced flags; expose as constants if needed.
- Pagination strategy for very large URL sets (page size; server-side filter endpoints).

## Risks & Mitigations

- Very large plans: mitigate with server-side paging/sampling and virtualized lists.
- Regex complexity: provide presets, inline validation, and per-rule notes; keep server-side validation via `grep -E`.
- WP environment variability: continue using expanded PATH; fail fast on missing deps at activation.

## Appendices

Example REST route signatures (illustrative):

```http
POST /wp-json/wgetta/v1/discover
Body: { command: { baseUrls: string[], options: {name:string,value:string|null,editable:boolean}[] } }
Resp: { planId: string, counts: { total:number }, sample: string[] }

POST /wp-json/wgetta/v1/rules/test
Body: { rules: Rule[], planId?: string, sampleSize?: number }
Resp: { includedCount: number, excludedCount: number, matches: Record<string, string[]> }

POST /wp-json/wgetta/v1/plans
PUT  /wp-json/wgetta/v1/plans/:id
GET  /wp-json/wgetta/v1/plans/:id
```

Effective command preview (server-assembled):

```json
{
  "discover": ["wget","--spider","-nv","--recursive","--level=2","https://example.com/"],
  "run": ["wget","-e","robots=off","--force-directories","https://example.com/"]
}
```
