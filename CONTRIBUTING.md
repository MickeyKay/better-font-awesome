# How to contribute

First of all, thanks for your interest in contributing 🎉👍. Getting started is pretty simple:

```
// Clone the repo locally
git clone https://github.com/MickeyKay/better-font-awesome.git

// Set up the repo for development (install dependencies)
cd better-font-awesome && npm run develop

// Create your development branch
git checkout -b issue-1234-bugfix

// Compile/build changes
npm run build

// Run tests to ensure everything is working as expected
npm run test

// Run linting checks to ensure coding standards are met
npm run lint
npm run lint-fix // Optionally fix errors that can be handled automatically.

// Commit your changes
git commit -m 'Do thing X to fix thing Y'
```

That's it! Once your changes are complete, just push your branch and file a PR with a detailed description.

### A note on the `Better Font Awesome Library` dependency
This plugin is dependent on the [Better Font Awesome Library](https://github.com/MickeyKay/better-font-awesome-library) for much of its core functionality. If you need to make changes to the underlying library's functionality, please make changes and file PR's into that repo.

## Testing

As mentioned above, the plugin includes a basic test suite which can be run via:
```
npm run test
```

As you develop new features, you get major bonus points for adding tests along the way!

## Submitting changes

Please file a [GitHub Pull Request](https://github.com/MickeyKay/better-font-awesome/pull/new/master) with a clear list of the changes you've made (read more about [pull requests](http://help.github.com/pull-requests/)). Please follow [WordPress coding standards](https://make.wordpress.org/core/handbook/best-practices/coding-standards/) and as best as possible ensure your commits are atomic (one feature per commit).

Always write a clear log message for your commits. One-line messages are fine for small changes, but bigger changes should look like this:

    $ git commit -m "A brief summary of the commit
    >
    > A paragraph describing what changed and its impact."


## Thank you
Seriously. Thank you. I very much appreciate your contributions to Better Font Awesome ♥️.