# Content Entity Fields

__A project by [Pragmatic](https://pragmatic.agency).__

<p>
  <img alt="Version 0.1.0-dev" src="https://img.shields.io/badge/version-0.1.0--dev-blue.svg?cacheSeconds=86400" />
  <img alt="Requires Node version 12.14.0+" src="https://img.shields.io/badge/node-12.14.0-blue.svg" />
  <img alt="Requires NPM version 6.13.0+" src="https://img.shields.io/badge/npm-6.13.0-blue.svg" />
  <img alt="License: GPL 3.0 only" src="https://img.shields.io/badge/License-GPL--3.0--only-yellow.svg" />
</p>

> Content Entity Fields helps developers add custom fields to content types, utilising the best of WordPress' block editor.

---

* [Requirements](#requirements)
* [Installation](#installation)
* [Development Process](#development-process)

---

## Requirements
### Usage Requirements
Ensure you have the prerequisite software installed:

* [Composer](https://getcomposer.org/) 1.8+, installed globally.
* [PHP](https://php.net/) 7.3+.

### Plugin Contribution Requirements
To contribute features or bug fixes back to this plugin, you will also need:

* [Node](https://nodejs.org/) 12.14.0+, and NVM.


## Installation
Install this plugin with Composer: `composer require PragmaticWebLimited/content-entity-fields`. Activate the plugin in your WordPress in the usual manner.


## Development Process
### Contribute to this Plugin

This plugin mostly follows the standard [Git Flow](http://jeffkreeftmeijer.com/2010/why-arent-you-using-git-flow/) model.

Development happens in feature branches, which are merged into `develop`, and then eventually merged into `master` for deployment to production. When making changes, a feature branch should be created that branches from `develop` (and the corresponding pull request should use `develop` as the target branch).

### Plugin Structure
This plugin maintains a basic file structure. The key areas are:

* `plugin.php` – The main plugin loader.
* `inc/` – PHP.
* `src/` - Javascript and SCSS source files.
* `dist/` - Production assets and other static files.
* `tests/` – Test files.

Within the `inc/` directory, files should be organised hierarchically based on namespaces. If a namespace only contains functions, the filename should be `functions.php`.

If a namespace also contains classes, group those classes together with the namespace file in a `{sub-namespace}/` directory. The main functions file for this namespace would be`{sub-namespace}/functions.php`, while classes should be in a file prefixed with `class-` with a "slugified" class name.

### Scripts and Tooling
 * `composer run lint` -- run all of the following tasks:
	* `composer run lint:css` -- check CSS with Prettier.
	* `composer run lint:js` -- check JS with ESLint and Prettier.
	* `composer run lint:php` -- check PHP with a PHP linter and PHPCS.
 * `npm run build` -- build production-ready versions of JS and CSS.
 * `npm run start` -- serve development versions of JS and CSS.
