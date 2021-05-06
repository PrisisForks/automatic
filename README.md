<h1 align="center">Narrowspark Automatic</h1>
<p align="center">
    <a href="https://github.com/narrowspark/automatic/releases"><img src="https://img.shields.io/packagist/v/narrowspark/automatic.svg?style=flat-square"></a>
    <a href="https://php.net/"><img src="https://img.shields.io/badge/php-%5E8.0.0-8892BF.svg?style=flat-square"></a>
    <a href="https://codecov.io/gh/narrowspark/automatic"><img src="https://img.shields.io/codecov/c/github/narrowspark/automatic/master.svg?style=flat-square"></a>
    <a href="#"><img src="https://img.shields.io/badge/style-level%20max-brightgreen.svg?style=flat-square&label=phpstan"></a>
    <a href="https://github.com/semantic-release/semantic-release"><img src="https://img.shields.io/badge/%20%20%F0%9F%93%A6%F0%9F%9A%80-semantic--release-e10079.svg?style=flat-square"></a>
    <a href=".github/CODE_OF_CONDUCT.md"><img src="https://img.shields.io/badge/Contributor%20Covenant-2.0-4baaaa.svg?style=flat-square"></a>
    <a href="http://opensource.org/licenses/MIT"><img src="https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square"></a>
</p>

Narrowspark Automatic automates the most common tasks of applications, like installing and removing bundles or providers, copying files, boosting dependencies downloads, creating skeletons and other Composer dependencies based configurations.

## How Does Narrowspark Automatic Work

Narrowspark Automatic is a Composer plugin that modifies the behavior of the `require`, `update`, `create project`, and `remove` commands.
When installing or removing dependencies in an Automatic extended app, your Application can perform tasks before and after the execution of Composer tasks.

Consider the following example:

```bash
cd your-project
composer require viserio/console
```

If you execute this command in your Application that doesn’t support Narrowspark Automatic, this command will execute in the normal composer require behavior.

> **Note** The `automatic.json` and composer.json extra key `automatic` are used to configure Narrowspark Automatic with configurators, script executors, custom-configurators, and more.

When Narrowspark Automatic is installed in your Application, it will check if a `automatic.json` file or a composer.json extra key with `automatic` exists.
In the above example, Automatic decided which automated tasks need to be run after the installation.

> **Note** Narrowspark Automatic keeps tracks of the configuration, in a `automatic.lock` file, which must be committed to your code repository.

## Using Narrowspark Automatic in New Applications

Include Narrowspark Automatic as a required dependency to your app with this command:
`composer require narrospark/automatic`.

## Using Narrowspark Automatic for Skeleton Application

Narrowspark Automatic supports skeleton generation. For example this is your `composer.json` file:

```json
{
    "type": "project",
    "license": "MIT",
    "require": {
        "php": "^7.2",
        "ext-mbstring": "*",
        "narrowspark/automatic": "^0.3.5",
        "narrowspark/skeleton-generators": "^0.1.0"
    },
    "extra": {
        "app-dir": "app",
        "config-dir": "config",
        "database-dir": "database",
        "public-dir": "public",
        "resources-dir": "resources",
        "routes-dir": "routes",
        "storage-dir": "storage",
        "tests-dir": "tests"
    },
    "autoload": {
        "psr-4": {
            "App\\": "app/"
        },
        "exclude-from-classmap": [
            "tests/"
        ]
    },
    "autoload-dev": {
        "psr-4": {
            "App\\Tests\\": "tests/"
        }
    },
    "minimum-stability": "dev",
    "prefer-stable": true
}
```

Automatic search all packages for the package type: `automatic-skeleton`.
If packages are found with this type, all skeletons will be saved in the `automatic.lock` for the runtime.

This means you can execute the following command: `composer create-project your/project` to create an Automatic extended app, Automatic will ask which skeleton can be generated for your app.

Read the [skeleton documentation](docs/SKELETON.md) to learn everything about how to create skeletons for your own app.

Narrowspark Automatic tasks are defined in a `automatic.json` file or in the composer extra key `automatic` and can contain any number of other files and directories. For example, this is the `automatic.json` for `viserio/console`:

```json
{
    "configurators": {
        "providers": {
            "Viserio\\Component\\Console\\Provider\\ConsoleServiceProvider": ["global"],
            "Viserio\\Component\\Console\\Provider\\LazilyCommandsServiceProvider": ["global"]
        },
        "proxies": {
            "Viserio\\Component\\Console\\Proxy\\Console": ["global"]
        }
    },
    "script-extenders": [
        "Viserio\\Component\\Console\\Automatic\\CerebroScriptExtender"
    ]
}
```

The `providers` and `proxies` option tells Narrowspark Automatic in which environments this `provider`, `proxy` can be turn on automatically (all in this case).

The `script-extenders` option adds a new script executor to the Narrowspark Automatic `auto-scripts`.
Now you can run `viserio console` commands in the `auto-scripts` section of your `composer.json` app file.

The instructions defined in this `automatic.json` file are also used by Narrowspark Automatic when uninstalling dependencies (for example `composer remove viserio/console`) to undo all changes.
This means that Automatic can remove the Console Provider and Proxy from the app and remove the script executor from Narrowspark Automatic.

Read the [configuration documentation](docs/CONFIGURATORS.md) to learn everything about how to create configuration for your own packages.

## Automatic extends Composer

Narrowspark Automatic adds a parallel downloader with the feature to skip old dependencies tags for a download boost.

With the below example you can see how to add a skip tag to Narrowspark Automatic, with this it will skip all tags of `cakephp` that are older then `3.5`.

```json
{
    "extra": {
        "automatic": {
            "require": {
                "cakephp/cakephp": ">=3.5"
            }
        }
    }
}
```

You can skip `auto discovery` for packages with this Narrowspark Automatic key `dont-discover` in your composer.json extra `automatic` section. Use the package name to skip the `auto discovery`, like in this example:

```json
{
    "extra" : {
        "dont-discover": [
            "viserio/console"
        ]
    }
}
```

Auto discovery will now be skipped for `viserio/console`.

## Automatic Security Audit

Narrowspark Automatic shows you on every `composer install` and `composer update` known security vulnerabilities for your installed dependencies based on [FriendsOfPHP/security-advisories](https://github.com/FriendsOfPHP/security-advisories) database.

If you like to change the default HTTP `timeout = 20` for the downloader, add `audit` to your composer.json `extra` section, like in this example:

```json
{
    "extra" : {
        "audit": {
            "timeout": "30"
        }
    }
}
```

## Testing

You need to run:
``` bash
$ php vendor/bin/phpunit
```

## Versioning

This library follows semantic versioning, and additions to the code ruleset are performed in major releases.

## Changelog

Please have a look at [`CHANGELOG.md`](CHANGELOG.md).

## Contributing

Please have a look at [`CONTRIBUTING.md`](.github/CONTRIBUTING.md).

## Code of Conduct

Please have a look at [`CODE_OF_CONDUCT.md`](.github/CODE_OF_CONDUCT.md).

## Credits

- [Daniel Bannert](https://github.com/prisis)
- [All Contributors](https://github.com/narrowspark/automatic/graphs/contributors)
- Narrowspark Automatic has been inspired by [symfony/flex](https://github.com/symfony/flex)

## License

This package is licensed using the MIT License.

Please have a look at [`LICENSE.md`](LICENSE.md).
