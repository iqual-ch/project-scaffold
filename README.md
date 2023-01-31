# Composer Project Scaffold Plugin 🚧

This Composer plugin provides the ability to add and update project scaffolding files in your project.

Using this plugin it is possible to manage scaffolding files in a composer project. The scaffolding files can be located in a separate composer package which can define assets to be added, replaced or merged into the project. When such a project asset package is added to a project interactively, including on project creation (with `composer create-project`) and during composer updates, the user can be prompted questions to fill in variables which can then be used for dynamically templated scaffolding assets.

The idea behind the plugin being, that git-commited project files which are essential to the development experience (e.g. `.lando.yml`, `.devcontainer`, `.ddev`, `.env`, etc.) or repository (e.g. `README.md`, `.gitignore`, `.github/workflows`, etc.) can be managed with composer. This allows centrally managing and codifying generic project templates. While composer allows creating new project from an existing package using `composer create-project` this adds the capability of making the bootstrapping interactive with support for templating and also adds the ability to update existing projects.

> This plugin is heavily inspired by Drupal's [`drupal/core-composer-scaffold`](https://github.com/drupal/core-composer-scaffold) reusing some of it's code for a more "general" approach to the scaffolding issue. Credit to the contributors on that project. Similar approaches that are standalone tools include [Phabalicious](https://github.com/factorial-io/phabalicious) or [Phint](https://github.com/adhocore/phint).

## Quick Start 🚀

Require the Project Scaffold Plugin with composer in your project and allowing the plugin. It is recommended to only require the plugin as development dependency.

```bash
composer require iqual/project-scaffold --dev
```

To manage project scaffolding one (or more) composer package containing the assets has to be required. Once the plugin is required, the user will be prompted (if in an interactive CLI) for questions defined by the package. The answers will then be saved to the `extra.project-scaffold` section of the root project's `composer.json`. Once all required variables are available the assets will then be managed by the Project Scaffold Plugin according to the asset package's `assets` definition.

See [Creating a project asset package](./docs/asset-package.md) for a guide on how to create a project asset package.

## Features ⭐

* Scaffolding new projects (i.e. initializing or bootstrapping)
* Interactive prompts during project creation or during updates
* Updating existing project assets or composer projects
* Managing project assets in separate versioned packages
* Managing your environment with copmoser
* Adding, replacing (including deletion) and merging of assets
* Templating assets based on variables using twig
* Saving project variables in `extra.project-scaffold` of the project's composer file
* Automating project scaffolding using composer (e.g. `composer update` in your CI)

## Commands ⚡

To initialize a new project (i.e. always prompting for package questions) use:

```bash
composer project:init
```

To update an existing project by prompting for the package questions again, and re-applying the scaffolding, use:

```bash
composer project:update
```

To (re-)apply the project scaffold from all asset packages use:

```bash
composer project:scaffold
```

## Documentation 📕

* [Creating a project asset package](./docs/asset-package.md)