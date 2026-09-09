# Native block style inheritance (unpublished)

Settings > Better Font Awesome includes **Default block icon style**, initially Solid, with Solid and Regular choices. Newly inserted native Icon blocks use **Site default**. An existing block can opt in using its Style control, or choose an explicit available style. The control identifies the effective style, including fallback: requested default if available, otherwise Solid, Regular, then Brands. Brand-only icons remain Brands. Changing icons keeps inheritance selected.

Changing the setting affects inherited blocks on subsequent uncached renders without rewriting posts. Explicit overrides and legacy blocks retain their appearance. Refresh ordinary page caches after changing the setting; reopen an already open editor to load the updated default and catalog. Missing icons and unavailable explicit styles retain their saved values and existing rendering behavior until explicitly changed. Inherited missing icons retain their name and use the requested default until the icon is available again.

## Saved content and initialization

The existing `iconStyle` schema default remains `solid`, so omitted legacy attributes still mean Solid. Inheritance is saved explicitly as `"iconStyle":"site-default"`. A default inserter variation supplies this value only when inserting a new native block through the WordPress inserter, including slash insertion. Parsing, reopening, and duplicating blocks preserve their attributes. Programmatic `createBlock()` calls without attributes retain the schema's legacy Solid behavior; callers that want inheritance must supply `iconStyle: 'site-default'`. There is no mount-time conversion or content migration.

The server reads the current site's `better-font-awesome_options.default_block_icon_style` setting, validated through the existing Settings API and AJAX handlers. Both the renderer and editor resolve inheritance from the active validated BFAL catalog. Each request’s block controller reuses the validated catalog and a name-to-styles lookup, rebuilding them when BFAL exposes changed catalog data; the site default is still read on every render. Shortcodes, Classic Editor insertion, raw HTML, and third-party icons are unaffected. Automatic and local delivery, deliberate earlier BFAL ownership, metadata HTTP, cron, and asset loading retain their established behavior.

## Verification and rollback

Focused PHP, JavaScript, and browser fixtures cover legacy omission, settings saves and rejection, site isolation, insertion, duplication, explicit opt-in/override, icon changes, fallback, missing icons, undo/redo, save/reload, and setting changes without content rewrites. Hosted CI covers the compatibility matrix. Owner manual acceptance and release review remain gates; this implementation is unpublished, and Pro integration is subsequent work.

Rollback the plugin code and generated assets together. No database migration needs reversal. Older BFA treats `site-default` as an unsupported style and renders Solid; inherited Regular and Brands icons can therefore change appearance on rollback. Explicit overrides and pre-feature content remain intact. The stored inheritance marker and site option can be retained for re-upgrade. If exact inherited appearance must survive a downgrade, explicitly choose supported styles on affected blocks before downgrading.
