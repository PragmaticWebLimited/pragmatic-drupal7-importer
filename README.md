# Drupal 7 Importer

__A project by [Pragmatic](https://pragmatic.agency).__

<p>
  <img alt="Version 0.1.0-dev" src="https://img.shields.io/badge/version-0.1.0--dev-blue.svg?cacheSeconds=86400" />
  <img alt="License: GPL 3.0 only" src="https://img.shields.io/badge/License-GPL--3.0--only-yellow.svg" />
</p>

> Developer framework for importing data from Drupal 7 into WordPress.

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


## Installation

1. Add this plugin to your project with Composer: `composer require pragmaticweb/pragmatic-drupal7-importer`.
1. Activate the plugin as usual, and use it via WP-CLI.

The plugin assumes a database called `wordpress_test` exists and is accessible using the credentials in your WordPress' `wp-config.php` file. If you will be working on PHPUnit tests, you will need to manually make sure the database exists.

Use the filter `pragmatic.drupal7_importer.map_drupal_roles_to_wp.roles_mapping` to map how Drupal user roles map to WordPress user roles.

## Development Process
### Contributions

This plugin mostly follows the standard [Git Flow](http://jeffkreeftmeijer.com/2010/why-arent-you-using-git-flow/) model.

Development happens in feature branches, which are merged into `develop`, and then eventually merged into `master` for deployment to production. When making changes, a feature branch should be created that branches from `develop` (and the corresponding pull request should use `develop` as the target branch).

#### Philosophy

PHP files should either declare symbols (classes, functions, etc) or run code (function calls, etc), but not both. Generally speaking, only one file per plugin should run code.

An exception is allowed for require statements only if there isn't a better way to include those files. As an example, a namespace file may require the namespace file for sub-namespaces, since PHP doesn't have a way to autoload namespaced functions.

For namespaced functions which are primarily action and filter callbacks, the `add_action/filter` calls should generally live in a `bootstrap()` function in the namespace. This allows the file to be loaded without adding hooks immediately, but still allows the hook declarations to live with the callbacks.

As an example, for a plugin called "pragmatic-coffee" with a namespace of `Pragmatic\Coffee`, the directory structure should look like this:

- plugin.php – Declares autoloader and runs bootstrap functions.
- inc/ – Contains PHP code for `Pragmatic\Coffee` namespace.
  - functions.php – Declares functions and constants in the `Pragmatic\Coffee` namespace.
  - class-cup.php – Declares the `Pragmatic\Coffee\Cup` class.
  - beans.php – Declares functions and constants in the `Pragmatic\Coffee\Beans` namespace. (Good approach for simpler plugins)
- sweeteners/ – Contains sub-namespace `Pragmatic\Coffee\Sweeteners`. (Good approach for larger plugins)
  - functions.php – Declares functions and constants in the `Pragmatic\Coffee\Sweeteners` namespace.
  - class-sugar.php – Declares the `Pragmatic\Coffee\Sweeteners\Sugar` class.

### Plugin Structure
This plugin maintains a basic file structure. The key areas are:

* `plugin.php` – The main plugin loader.
* `inc/` – PHP.
* `tests/` – Test files.

Within the `inc/` directory, files should be organised hierarchically based on namespaces. If a namespace only contains functions, the filename should be `functions.php`.

If a namespace also contains classes, group those classes together with the namespace file in a `{sub-namespace}/` directory. The main functions file for this namespace would be`{sub-namespace}/functions.php`, while classes should be in a file prefixed with `class-` with a "slugified" class name.

### Scripts and Tooling

* `composer run tests` -- run all of the following tasks:
	* `composer run test:phpunit` -- run PHPUnit tests.
* `composer run lint` -- crun all of the following tasks:
	* `composer run lint:phpcs` -- check PHP quality with a PHP linter and, PHPCS.
