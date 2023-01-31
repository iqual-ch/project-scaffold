# Creating a project asset package

To manage project assets, a new composer package has to be created (e.g. `iqual/example-project-assets`). The new package can then define assets, variables and questions for scaffolding into the project. The root project needs to allow this plugin for scaffolding and (optionally) set the file destination locations and scaffolding variables.

Example root project `composer.json`:

```json
{
  "name": "iqual/example-project",
  "type": "project",
  "require": {
    /* your required packages */
  },
  "require-dev": {
    "iqual/project-scaffold": "^1.0",
    "iqual/example-project-assets": "^1.0"
  },
  "config": {
    "allow-plugins": {
      "iqual/project-scaffold": true
    }
  },
  "extra": {
    "project-scaffold": {
      "name": "example-variable",
      "locations": {
        "project-root": ".",
        "app-root": "app",
        "web-root": "web"
      },
      "allowed-packages": ["iqual/example-project-assets"]
    },
  }
}

```

An example structure for a separate project asset package:

```
├── assets
│   ├── add
│   │   └── @web-root
│   │       └── module
│   │           └── example
│   │               └── exampleModule.php
│   ├── replace
│   │   ├── .devcontainer
│   │   │   └── devcontainer.json
│   │   ├── .github
│   │   │   └── workflows
│   │   │       └── testing.yml.twig
│   │   ├── @web-root
│   │   │   └── robots.txt.twig
│   │   ├── docker-compose.yml.twig
│   │   ├── Makefile
│   │   └── README.md.twig
│   └── merge
│       ├── .env.twig
│       └── .gitignore.twig
├── composer.json
└── questions.json
```

One of the templated assets could look like the follwing `README.md.twig` example:

```twig
# {{ name }} website

Example project for {{ name }}'s website running on PHP {{ runtime.php_version }}.
```

An example `composer.json` of the project asset package:

```json
{
    "name": "iqual/example-project-assets",
    "type": "library",
    "require": {
        "iqual/project-scaffold": "*"
    },
    "extra": {
        "project-scaffold": {
            "runtime": {
                "php_version": 8.1,
                "db_version": 10.6,
            },
            "workflows": {
                "testing": true,
            },
            "assets": {
                "add": "assets/add",
                "replace": "assets/replace",
                "merge": "assets/merge",
                "read": "questions.json"
            }
        }
    }
}
```

What variables are required and what the user should be prompted when the package is installed interactively can be defined in the `read` asset. Her's an example `questions.json`:

```json
{
    "required": [
        "name",
        "runtime.php_version",
        "runtime.db_version"
    ],
    "questions": {
        "name": {
            "question": "What is the <info>code name</info> of the project?",
            "default": "[root-package-name]",
            "filter": "/iqual\\/(.+?)/$1/",
            "validation": "[a-z][a-z0-9\\-]{0,28}[a-z0-9]"
        },
        "runtime.php_version": {
            "question": "What <info>PHP version</info> should be installed?",
            "options": [
                "8.0",
                "8.1",
                "8.2"
            ]
        },
        "runtime.db_version": {
            "question": "What <info>database version</info> should be installed?",
            "options": [
                "10.3"
            ]
        }
    }
}
```

Once this example project asset package is required in the root project the user will be prompted (if in an interactive CLI) for these questions. The answers by the user will then be saved to the `extra.project-scaffold` section of the root project's `composer.json`. Once all required variables are available the assets will then be managed by the Project Scaffold Plugin according to the asset package's `assets` definition.

In this example an `exampleModule.php` file will be added to `app/web/module/example/exampleModule.php` once. The `add` operation will not overwrite existing files.
Multiple folders and files will be replaced in the project root, for example a `README.md` will be created from `README.md.twig` template. The `replace` operation will always overwrite exsting files. Lastly a `.env` and `.gitignore` file will be merged into the project root. The `merge` operation will copy the original file's content if the destination doesn't exist yet, or replace the values of the variables in the destination file with the values from the source file (support for `.env`, `.gitignore`, `.json` and `.yml`).

If the asset package is updated and a new version is released, then the update will be applied to the managed files in the project once the project (or only the required package) is updated (i.e. using `composer update`).