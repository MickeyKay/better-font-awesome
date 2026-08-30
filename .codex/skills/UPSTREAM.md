# Vendored WordPress agent skills

These project-scoped skills are copied from the official
[WordPress/agent-skills](https://github.com/WordPress/agent-skills) repository.
They are committed so Codex sessions and contributors working in this repository
use the same WordPress development guidance.

- Upstream revision: `d87ee6916e740c7960b6959220c0481a41b320c7`
- Upstream branch at import: `trunk`
- Imported: 2026-08-29
- License: GPL-2.0-or-later, reproduced in `LICENSE`

## Included skills

- `blueprint`
- `wp-block-development`
- `wp-performance`
- `wp-phpstan`
- `wp-playground`
- `wp-plugin-development`
- `wp-plugin-directory-guidelines`
- `wp-project-triage`
- `wp-wpcli-and-ops`

`wp-project-triage` is required by the block and plugin development workflows.
`blueprint` is required for the Blueprint workflows delegated by `wp-playground`.

## Carried project patches

The project carries focused changes to the two `wp-project-triage` detector scripts:

- Ignore `.codex`, `.conductor`, and `.context` directories during repository scans.
- Recognize normal PHPDoc-style plugin and theme headers whose lines begin with `*`.

These patches prevent recursive local-tooling scans and allow the detectors to identify this plugin's standard WordPress header. Re-evaluate and preserve them when refreshing the vendored skills unless upstream has incorporated equivalent behavior.

## Project compatibility

Repository instructions in `AGENTS.md` take precedence over the generic skill
defaults. In particular, Better Font Awesome supports WordPress 6.5 or newer
and PHP 7.4 or newer. Do not adopt WordPress 7.0-only APIs solely because an
upstream skill lists WordPress 7.0 as its current target.

## Updating

Review upstream changes and select a single commit before replacing these
directories. Keep all included skills on the same upstream revision, reapply or
retire the documented project patches as appropriate, update the revision and
import date above, and run the repository validation suite before committing the
update.
