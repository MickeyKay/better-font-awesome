# Better Font Awesome 2.1.0 release procedure

This procedure prepares and verifies BFA 2.1.0. It does not authorize publication. The first command that writes to the public WordPress.org SVN repository is the publication boundary and must not run without explicit repository-owner authorization. Git tag creation, GitHub release publication, WordPress.org Release Management confirmation, and any public artifact publication also require that authorization.

## Reviewed release base

- Merged integration PR: #52
- Merge commit: `6631a86059dd30d4efe438e4f804082034ebf7b5`
- Reviewed head: `28cd67c64a7b2b0f260a2f55f1a0707e6a1a4ed5`
- Identical Git tree: `a1d2ae6f260b2684b335f6876a861990a52e2af7`
- BFAL package: stable `2.1.0`
- BFAL source and distribution reference: `b845f8d2c105c34a9afe62e8470d67d0e3978164`

## Runtime writes and lifecycle

All metadata values and cron events are site-scoped. Network activation does not create network-global metadata. It iterates the sites in the current network, schedules each site independently, restores the original blog context, and schedules newly created sites only while BFA is network-active for that network.

- `better_font_awesome_release_record` is the durable, validated last-known-good Font Awesome 5 Free metadata record.
- `better_font_awesome_release_state` is durable refresh and failure state.
- `better_font_awesome_metadata_schema` is the durable migration schema marker.
- `better_font_awesome_refresh_schedule` is the temporary schedule ownership marker.
- `better_font_awesome_refresh_lock` is the temporary worker lease.
- `better_font_awesome_refresh_release_data` is the site-scoped WP-Cron event.
- `better-font-awesome_options` is the established site-scoped plugin settings option.
- Published posts and other content containing `[icon]` shortcodes remain ordinary WordPress content.

Deactivation clears the site's pending `better_font_awesome_refresh_release_data` events and deletes only `better_font_awesome_refresh_schedule` and `better_font_awesome_refresh_lock`. Network deactivation performs that cleanup separately for each site in the current network. Deactivation preserves the durable metadata record, refresh state, migration marker, established settings, compatibility transient, and published shortcode content.

The plugin has no uninstall hook and no `uninstall.php`. Uninstall therefore performs no plugin-specific option or content cleanup. WordPress removes the plugin files, while the settings, durable metadata, refresh state, migration marker, compatibility transient, and published content remain in the database.

Rollback to BFA 2.0.4 leaves the three newer durable metadata options inert because that release does not read them. Existing BFA settings and shortcode content remain usable. Retaining those options is harmless during rollback and preserves validated data for forward recovery when BFA 2.1.0 or later is restored.

## Official rollback artifact

The official rollback artifact is `https://downloads.wordpress.org/plugin/better-font-awesome.2.0.4.zip`. WordPress.org currently returns that same final URL without an additional redirect. The verified archive is 1,859,505 bytes with SHA-256 `fac4f51367ec56bf7ec75bfce15d4f52c2e3ae6ccbaa941acf7e61ae2f505194`, has root directory `better-font-awesome/`, reports BFA `2.0.4`, and includes BFAL `2.0.3`.

Before an authorized rollback, take a database and files backup and verify the checksum locally. The following commands target a confirmed non-production or explicitly authorized WordPress installation:

```sh
rollback_zip=/absolute/path/to/better-font-awesome.2.0.4.zip
wp --path=/absolute/path/to/wordpress db export /absolute/path/to/backups/before-bfa-rollback.sql
shasum -a 256 "$rollback_zip"
wp --path=/absolute/path/to/wordpress plugin deactivate better-font-awesome
wp --path=/absolute/path/to/wordpress plugin install "$rollback_zip" --force
wp --path=/absolute/path/to/wordpress plugin activate better-font-awesome
wp --path=/absolute/path/to/wordpress plugin get better-font-awesome --fields=name,status,version
```

Verify the expected settings and existing shortcode content before and after rollback. BFA 2.0.4 and BFAL 2.0.3 restore synchronous Font Awesome metadata HTTP on a transient cache miss, weaker metadata transport and validation, and transient-only caching. Rollback is an emergency availability path, not an equivalent reliability or security posture.

## WordPress.org SVN preparation

Use a clean release checkout and the exact authorized BFA release commit. Do not reuse an old SVN working copy.

```sh
release_source=/absolute/path/to/clean/better-font-awesome-release
svn_wc="$release_source/svn"
release_commit=REPLACE_WITH_AUTHORIZED_RELEASE_COMMIT

git clone https://github.com/MickeyKay/better-font-awesome.git "$release_source"
git -C "$release_source" checkout --detach "$release_commit"
test "$(git -C "$release_source" status --porcelain)" = ""
test "$(git -C "$release_source" rev-parse HEAD)" = "$release_commit"

test ! -e "$svn_wc"
svn checkout https://plugins.svn.wordpress.org/better-font-awesome "$svn_wc"
test "$(sed -n 's/^Stable tag:[[:space:]]*//p' "$svn_wc/trunk/readme.txt")" = "2.0.4"
test ! -e "$svn_wc/tags/2.1.0"

cd "$release_source"
npm ci
npm run composer:install
npm run i18n

npx grunt build-release-tree --release-version=2.1.0
sh bin/audit-release-tree.sh svn/trunk
test "$(sed -n 's/^Stable tag:[[:space:]]*//p' svn/trunk/readme.txt)" = "2.1.0"
test -f svn/trunk/better-font-awesome.php
test ! -f svn/trunk/better-font-awesome/better-font-awesome.php
svn copy svn/trunk svn/tags/2.1.0
diff -ru svn/trunk svn/tags/2.1.0
svn status svn
svn diff svn
```

Before publication, compare `svn/trunk` and `svn/tags/2.1.0` with both independently verified production trees. Confirm that plugin files are directly inside `trunk`, all expected assets remain under `assets`, and no tests, development files, `.git`, `.github`, `.codex`, `.context`, Conductor files, caches, `node_modules`, credentials, secrets, or unexpected paths are present.

After explicit repository-owner authorization, and only then, the public WordPress.org publication boundary is:

```sh
svn commit svn -m "Release Better Font Awesome 2.1.0"
```

Complete any WordPress.org Release Management confirmation required for the plugin after the SVN commit. Then verify the plugin page, metadata, and generated public ZIP, record the public ZIP SHA-256, and repeat activation and shortcode smoke checks against that exact download.

If publication validation fails and the current WordPress.org procedure permits rollback, change only `trunk/readme.txt` back to `Stable tag: 2.0.4`, review `svn status` and `svn diff`, obtain explicit authorization, commit that stable-tag rollback, and verify the public 2.0.4 download. Do not delete the 2.1.0 SVN tag as a rollback shortcut.

## GitHub tag and release

Existing stable releases use bare version tags such as `2.0.4`, with a GitHub release attached to the same tag. After the release PR is reviewed and merged, verify the exact merge tree and repeat the deterministic package and package-level checks on that commit. Only after explicit repository-owner authorization:

```sh
release_commit=REPLACE_WITH_AUTHORIZED_RELEASE_COMMIT
git fetch origin master --tags
test "$(git rev-parse origin/master)" = "$release_commit"
git tag 2.1.0 "$release_commit"
git push origin 2.1.0
gh release create 2.1.0 --verify-tag --title "2.1.0" --notes-file /absolute/path/to/authorized-release-notes.md
```

Verify the GitHub release tag resolves to the authorized commit. Do not attach or publish an artifact until its checksum and production-tree audit match the authorized release commit.
