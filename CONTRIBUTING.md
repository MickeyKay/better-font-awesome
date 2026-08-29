# How to contribute

First of all, thanks for your interest in contributing 🎉👍. You will need Docker, Node.js 20 or newer, and npm.

```sh
# Clone the repo locally.
git clone https://github.com/MickeyKay/better-font-awesome.git

# Install dependencies and start the local WordPress environments.
cd better-font-awesome
npm run develop

# Create your development branch.
git checkout -b issue-1234-bugfix

# Run the PHP coding standards, static analysis, and PHPUnit suite.
npm run check

# Regenerate translation and readme artifacts when their sources change.
npm run i18n
npm run build

# Stop the local environment when you are finished.
npm run env:stop

# Commit your changes.
git commit -m 'Do thing X to fix thing Y'
```

Use `npm run lint:fix` only for intentional coding-standard fixes. Do not commit `vendor/`, `node_modules/`, local WordPress state, or credentials.

That's it! Once your changes are complete, just push your branch and file a PR with a detailed description.

### A note on the `Better Font Awesome Library` dependency
This plugin is dependent on the [Better Font Awesome Library](https://github.com/MickeyKay/better-font-awesome-library) for much of its core functionality. If you need to make changes to the underlying library's functionality, please make changes and file PR's into that repo.

## Testing

The complete local check entrypoint is:

```sh
npm run check
```

The PHPUnit suite can also be run independently:

```sh
npm run test
```

As you develop new features, you get major bonus points for adding tests along the way!

## Preparing a WordPress.org release tree

Generate translations, then build the canonical WordPress.org SVN tree with an explicit release version:

```sh
npm run release -- --release-version=2.0.5 --update-stable
```

Omit `--update-stable` when the WordPress.org stable tag should not move. A release version is always required and must be a semantic version such as `2.0.5` or `2.0.5-rc.1`. The task recreates `svn/trunk`, creates a new `svn/tags/<version>`, applies `.distignore`, and copies only the production BFAL files required at runtime. It aborts without changing source versions when the requested tag already exists.

CI uses `npx grunt build-release-tree` to build and run Plugin Check against the same `svn/trunk` output without creating a publishable tag.

## Submitting changes

Please file a [GitHub Pull Request](https://github.com/MickeyKay/better-font-awesome/pull/new/master) with a clear list of the changes you've made (read more about [pull requests](http://help.github.com/pull-requests/)). Please follow [WordPress coding standards](https://make.wordpress.org/core/handbook/best-practices/coding-standards/) and as best as possible ensure your commits are atomic (one feature per commit).

Always write a clear log message for your commits. One-line messages are fine for small changes, but bigger changes should look like this:

    $ git commit -m "A brief summary of the commit
    >
    > A paragraph describing what changed and its impact."


## Thank you
Seriously. Thank you. I very much appreciate your contributions to Better Font Awesome ♥️.
