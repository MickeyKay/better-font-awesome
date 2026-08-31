# Better Font Awesome release procedure

This reusable procedure prepares, verifies, publishes, and, if necessary, rolls back a Better Font Awesome maintenance release. It does not authorize publication. Git tag creation, GitHub release publication, the first command that writes to public WordPress.org SVN, WordPress.org Release Management confirmation, and any other public artifact publication require explicit repository-owner authorization.

Record exact values and completed evidence in the release PR and final release record. Do not hardcode one release's commits, dependency references, artifact identities, or completed gate state in this runbook.

## Release variables

Set and review these values before running any release command:

```sh
plugin_slug=better-font-awesome
release_version=REPLACE_WITH_RELEASE_VERSION
previous_version=REPLACE_WITH_PREVIOUS_STABLE_VERSION
release_commit=REPLACE_WITH_AUTHORIZED_RELEASE_COMMIT
release_source=/absolute/path/to/clean/better-font-awesome-release
svn_wc="$release_source/svn"
rollback_zip=/absolute/path/to/official-previous-release.zip
rollback_sha256=REPLACE_WITH_VERIFIED_ROLLBACK_SHA256
release_notes=/absolute/path/to/authorized-release-notes.md
```

- `release_version` is the semantic version being prepared and becomes the Git and SVN tag.
- `previous_version` is the currently published stable version and intended emergency rollback target.
- `release_commit` is the exact reviewed commit being built. After merge, replace it with the exact authorized merge commit and repeat verification.
- `rollback_zip` is the locally verified official WordPress.org ZIP for `previous_version`.
- `rollback_sha256` is the independently recorded checksum for `rollback_zip`.
- `release_source` is a new clean Git checkout dedicated to the release.
- `svn_wc` must be `$release_source/svn` because the repository build writes the canonical release tree there.
- `plugin_slug` is the WordPress.org plugin slug.

## Clean checkout and exact commit verification

Do not reuse a development checkout or an old SVN working copy.

```sh
test ! -e "$release_source"
git clone https://github.com/MickeyKay/better-font-awesome.git "$release_source"
git -C "$release_source" fetch origin master --tags
git -C "$release_source" checkout --detach "$release_commit"
test "$(git -C "$release_source" status --porcelain)" = ""
test "$(git -C "$release_source" rev-parse HEAD)" = "$release_commit"
test "$(git -C "$release_source" rev-parse HEAD^{tree})" = "$(git -C "$release_source" show -s --format=%T "$release_commit")"
```

Verify the release identity before installing dependencies:

```sh
test "$(sed -n 's/^ \* Version:[[:space:]]*//p' "$release_source/better-font-awesome.php")" = "$release_version"
test "$(sed -n 's/^Stable tag:[[:space:]]*//p' "$release_source/readme.txt")" = "$release_version"
test "$(node -p "require('$release_source/package.json').version")" = "$release_version"
grep -Fq "Project-Id-Version: Better Font Awesome $release_version" "$release_source/languages/better-font-awesome.pot"
```

Record the exact commit, tree, required BFAL version and reference, and clean-checkout results in the release PR.

## Generated documentation and source validation

Install exactly the locked development and production dependencies in the clean checkout, rebuild the generated README, and require a byte-stable result:

```sh
cd "$release_source"
npm ci
npm run composer:install
npm run build
git diff --exit-code -- README.md readme.txt
git diff --check
sh bin/composer.sh validate --strict
sh bin/composer.sh audit
npm audit --audit-level=high
npm run lint
npm run analyze
```

Run the current and rollback test modes required by the release checklist. Tests must use deterministic fixtures rather than the live Font Awesome service. Record command output and exact test counts in the release PR.

## Two deterministic production builds

Create two independent exports from the exact Git commit. Do not copy from the development worktree. The temporary paths below are examples and must be empty before use.

```sh
build_root=$(mktemp -d)
build_a="$build_root/source-a"
build_b="$build_root/source-b"
mkdir "$build_a" "$build_b"
git -C "$release_source" archive "$release_commit" | tar -x -C "$build_a"
git -C "$release_source" archive "$release_commit" | tar -x -C "$build_b"
```

Run the same locked install and canonical build in each export:

```sh
for build_source in "$build_a" "$build_b"; do
    cd "$build_source"
    npm ci
    npm run composer:install
    npm run build
    git --git-dir="$release_source/.git" --work-tree="$build_source" diff --exit-code "$release_commit" -- README.md readme.txt
    npx grunt build-release-tree --release-version="$release_version"
    sh bin/audit-release-tree.sh svn/trunk
    test "$(sed -n 's/^Stable tag:[[:space:]]*//p' svn/trunk/readme.txt)" = "$release_version"
done

diff -ru "$build_a/svn/trunk" "$build_b/svn/trunk"
```

Create two archives with the plugin slug as the single root directory. Normalize timestamps and feed files to ZIP in sorted order so the result is reproducible:

```sh
artifact_a="$build_root/artifact-a"
artifact_b="$build_root/artifact-b"
zip_a="$build_root/$plugin_slug-$release_version-a.zip"
zip_b="$build_root/$plugin_slug-$release_version-b.zip"
mkdir -p "$artifact_a/$plugin_slug" "$artifact_b/$plugin_slug"
cp -R "$build_a/svn/trunk/." "$artifact_a/$plugin_slug/"
cp -R "$build_b/svn/trunk/." "$artifact_b/$plugin_slug/"
find "$artifact_a" "$artifact_b" -exec touch -t 200001010000 {} +
(
    cd "$artifact_a"
    find "$plugin_slug" -type f -print | LC_ALL=C sort | zip -X -q "$zip_a" -@
)
(
    cd "$artifact_b"
    find "$plugin_slug" -type f -print | LC_ALL=C sort | zip -X -q "$zip_b" -@
)
cmp "$zip_a" "$zip_b"
```

Audit the extracted archive as well as the source tree:

```sh
extracted="$build_root/extracted"
mkdir "$extracted"
unzip -q "$zip_a" -d "$extracted"
test -d "$extracted/$plugin_slug"
test "$(find "$extracted" -mindepth 1 -maxdepth 1 -type d -print | wc -l | tr -d ' ')" = "1"
cd "$build_a"
sh bin/audit-release-tree.sh "$extracted/$plugin_slug"
test -z "$(find "$extracted/$plugin_slug" -type f -name '*.php' -exec php -l {} \; 2>&1 | grep -v 'No syntax errors detected')"
```

Record the exact source commit and tree, archive root, file count, byte size, SHA-256, and byte-identical comparison result:

```sh
find "$artifact_a/$plugin_slug" -type f | wc -l
wc -c < "$zip_a"
shasum -a 256 "$zip_a" "$zip_b"
```

Install and activate the exact ZIP on a clean supported WordPress site. Repeat the selected official upgrade path, settings and shortcode preservation checks, zero-metadata-HTTP request checks, explicit scheduled worker fixture, failure fallback, and deactivation or reactivation checks required by the release checklist.

## Runtime writes and lifecycle verification

All metadata values and cron events are site-scoped. Network activation does not create network-global metadata. It iterates sites in the current network, schedules each site independently, restores the original blog context, and schedules newly created sites only while BFA is network-active for that network.

- `better_font_awesome_release_record` is the durable, validated last-known-good Font Awesome 5 Free metadata record.
- `better_font_awesome_release_state` is durable refresh and failure state.
- `better_font_awesome_metadata_schema` is the durable migration schema marker.
- `better_font_awesome_refresh_schedule` is the temporary schedule ownership marker.
- `better_font_awesome_refresh_lock` is the temporary worker lease.
- `better_font_awesome_refresh_release_data` is the site-scoped WP-Cron event.
- `better-font-awesome_options` is the established site-scoped plugin settings option.
- Published posts and other content containing `[icon]` shortcodes remain ordinary WordPress content.

Deactivation must clear the site's pending worker events, schedule claims, and worker locks while preserving the durable metadata record, refresh state, migration marker, established settings, compatibility transient, and published shortcode content. Network deactivation must perform that cleanup separately for each site in the current network.

The plugin has no uninstall hook and no `uninstall.php`. Uninstall therefore performs no plugin-specific option or content cleanup. WordPress removes plugin files while settings, durable metadata, refresh state, migration markers, compatibility transients, and published content remain in the database.

Rollback to `previous_version` should leave unknown newer durable metadata options inert. Existing BFA settings and shortcode content must remain usable, and retaining validated data should preserve forward recovery when the current or a later release is restored.

## Official rollback artifact

Download the official previous release from WordPress.org, record its final URL, and verify it against the independently recorded checksum:

```sh
curl -fL "https://downloads.wordpress.org/plugin/$plugin_slug.$previous_version.zip" -o "$rollback_zip"
printf '%s  %s\n' "$rollback_sha256" "$rollback_zip" | shasum -a 256 -c -
unzip -l "$rollback_zip"
wc -c < "$rollback_zip"
```

Confirm the archive has exactly one `$plugin_slug/` root, reports `previous_version`, includes the expected prior BFAL version, and activates successfully. Record its final URL, file count, byte size, SHA-256, BFA version, and BFAL version in the release PR.

Before an authorized rollback, take database and files backups and verify the checksum again. The following commands must target a confirmed non-production or explicitly authorized WordPress installation:

```sh
wp --path=/absolute/path/to/wordpress db export /absolute/path/to/backups/before-bfa-rollback.sql
printf '%s  %s\n' "$rollback_sha256" "$rollback_zip" | shasum -a 256 -c -
wp --path=/absolute/path/to/wordpress plugin deactivate "$plugin_slug"
wp --path=/absolute/path/to/wordpress plugin install "$rollback_zip" --force
wp --path=/absolute/path/to/wordpress plugin activate "$plugin_slug"
wp --path=/absolute/path/to/wordpress plugin get "$plugin_slug" --fields=name,status,version
```

Verify expected settings and existing shortcode content before and after rollback. Document any weaker transport, validation, caching, or request-path behavior restored by the prior BFA and BFAL versions. Rollback is an emergency availability path, not an equivalent reliability or security posture.

## WordPress.org SVN preparation

Check out a new WordPress.org working copy inside the clean release checkout:

```sh
cd "$release_source"
test ! -e "$svn_wc"
svn checkout "https://plugins.svn.wordpress.org/$plugin_slug" "$svn_wc"
test "$(sed -n 's/^Stable tag:[[:space:]]*//p' "$svn_wc/trunk/readme.txt")" = "$previous_version"
test ! -e "$svn_wc/tags/$release_version"
```

Build the SVN trunk from the exact release checkout, audit it, and create the local SVN tag:

```sh
cd "$release_source"
npx grunt build-release-tree --release-version="$release_version"
sh bin/audit-release-tree.sh "$svn_wc/trunk"
test "$(sed -n 's/^Stable tag:[[:space:]]*//p' "$svn_wc/trunk/readme.txt")" = "$release_version"
test -f "$svn_wc/trunk/better-font-awesome.php"
test ! -f "$svn_wc/trunk/$plugin_slug/better-font-awesome.php"
svn copy "$svn_wc/trunk" "$svn_wc/tags/$release_version"
diff -ru "$svn_wc/trunk" "$svn_wc/tags/$release_version"
svn status "$svn_wc"
svn diff "$svn_wc"
```

Compare SVN trunk and the new tag with both independently verified production trees. Confirm that plugin files are directly inside `trunk`, all expected WordPress.org assets remain under `assets`, and no tests, development files, repository metadata, agent files, Conductor files, caches, `node_modules`, credentials, secrets, or unexpected paths are present.

After explicit repository-owner authorization, and only then, the public WordPress.org publication boundary is:

```sh
svn commit "$svn_wc" -m "Release Better Font Awesome $release_version"
```

Complete any WordPress.org Release Management confirmation required after the SVN commit. Then verify the plugin page, metadata, and generated public ZIP. Record the public ZIP SHA-256 and repeat activation and shortcode smoke checks against that exact download.

If publication validation fails and the current WordPress.org procedure permits rollback, change only `trunk/readme.txt` back to `Stable tag: $previous_version`, review `svn status` and `svn diff`, obtain explicit authorization, commit that stable-tag rollback, and verify the public previous-version download. Do not delete an SVN tag as a rollback shortcut.

## Git tag and GitHub release

Stable releases use bare semantic version tags, with a GitHub release attached to the same tag. After the release PR is reviewed and merged, set `release_commit` to the exact authorized merge commit, verify its tree, and repeat the deterministic package and package-level checks on that commit.

Only after explicit repository-owner authorization:

```sh
git fetch origin master --tags
test "$(git rev-parse origin/master)" = "$release_commit"
git tag "$release_version" "$release_commit"
git push origin "$release_version"
gh release create "$release_version" --verify-tag --title "$release_version" --notes-file "$release_notes"
```

Verify that the GitHub release tag resolves to `release_commit`. Do not attach or publish an artifact until its checksum and production-tree audit match the authorized release commit.

## Public download verification

After authorized publication and cache propagation, download the public release without reusing a local build:

```sh
public_zip=/absolute/path/to/public-$plugin_slug-$release_version.zip
curl -fL "https://downloads.wordpress.org/plugin/$plugin_slug.$release_version.zip" -o "$public_zip"
shasum -a 256 "$public_zip"
wc -c < "$public_zip"
unzip -l "$public_zip"
```

Confirm the archive root, file inventory, plugin and BFAL versions, public metadata, clean activation, and selected smoke checks. Record the final URL, byte size, SHA-256, and results in the GitHub release record. If any result differs from the authorized release tree, stop and follow the authorized rollback procedure.
