<h1 align="center">Narrowspark Automatic Configurators</h1>

Narrowspark Automatic Configurations allow the automation of Composer packages configuration via the
[`Narrowspark Automatic`](../README.md) Composer plugin.

Configurators
----------------
Configurators define the different tasks executed when installing a dependency, such as running commands, copying files or adding new environment variables.

The package only contain the tasks needed to install and configure the dependency, because Narrowspark Automatic Configurators are smart enough to reverse those tasks when uninstalling and unconfiguring the dependencies.

Narrowspark Automatic comes with several types of tasks, which are called **configurators**: `copy`, `env`, `composer-scripts`, `gitignore`, and `post-install-output`.

### Copy Configurator `copy`

Copies files or directories from the Composer package contents to your application. It's defined as an associative array where the key is the original file/directory and the value is the target file/directory.

This example copies the ``bin/check.php`` script of the package into the binary
directory of the application:

```json
{
    "copy": {
        "bin/check.php": "%BIN_DIR%/check.php"
    }
}
```

The `%BIN_DIR%` string is a special value that it's turned into the absolute
path of the binaries directory. You can access any variable defined in
the `extra` section of your `composer.json` file:

```json
{
    "...": "...",
    "extra": {
        "bin-dir": "bin",
        "my-special-dir": "..."
    }
}
```

Now you can use `%MY_SPECIAL_DIR%` in your configurator.

### Env Configurator `env`

Adds the given list of environment variables to the `.env` and `.env.dist`
files stored in the root of your application project:

```json
{
    "env": {
        "APP_ENV": "dev"
    }
}
```

This package configuration is converted into the following content appended to the `.env`
and `.env.dist` files:

```bash
###> your-package-name-here ###
APP_ENV=dev
###< your-package-name-here ###
```

The `###> your-package-name-here ###` section separators are needed by Narrowspark Automatic
to detect the contents added by this dependency in case you uninstall it later.
> !!! Don't remove or modify these separators.

Composer Scripts Configurator `composer-scripts`

Registers scripts in the `auto-scripts` section of the `composer.json` file
to execute them automatically when running `composer install` and `composer update`.
The value is an associative array where the key is the script to execute (including all its arguments and options) and the value is the type of script (`php-script` for PHP scripts, ``script`` for any shell script):

```json
{
    "composer-scripts": {
        "echo \"hallo\";": "php-script",
        "bash -c \"echo hallo\"": "script"
    }
}
```

You can create your own script executor, create a new class inside your Package Repository in a `Automatic` folder and extend `Narrowspark\Automatic\Common\ScriptExtender\AbstractScriptExtender` the example below shows you how it should look:

```php
<?php
declare(strict_types=1);

use Narrowspark\Automatic\Common\ScriptExtender\AbstractScriptExtender;

final class ScriptExtender extends AbstractScriptExtender
{
    /**
     * {@inheritdoc}
     */
    public static function getType(): string
    {
        return 'your type';
    }

    /**
     * {@inheritdoc}
     */
    public function expand(string $cmd): string
    {
        return 'your modified cmd input';
    }
}
```

Git ignore Configurator `gitignore`

Adds patterns to the ``.gitignore`` file in your project. Define those
patterns as a simple array of strings (a `PHP_EOL` character is added after
each line):

```json
{
    "gitignore": [
        ".env",
        "/public/bundles/",
        "/var/",
        "/vendor/"
    ]
}
```

Similar to other configurators, the contents are copied into the `.gitignore`
file and wrapped with section separators (``###> your-package-name-here ###``)
that must not be removed or modified.

Post-install output Configurator `post-install-output`

Displays contents in the command console after the package has been installed.
Avoid outputting meaningless information and use it only when you need to show
help messages or the next step actions.

* The contents must be defined in the `automatic` section of your package composer.json or the `automatic.json` file (a `PHP_EOL` character is added after each line). [Symfony Console styles and colors](https://symfony.com/doc/current/console/coloring.html) are supported too:

```json
{
    "post-install-output": [
        "<bg=blue;fg=white>              </>",
        "<bg=blue;fg=white> What's next? </>",
        "<bg=blue;fg=white>              </>"
    ]
}
```

This outout will be only shown on the `composer install` and `composer update` command.

How to create Configurators
----------------

You can choose between 2 ways, how to create configurators.

### Creating Configurators Repository

> This is a good way to reuse your configurators on other projects.

Narrowspark Automatic Configurators must be stored on their own repositories, outside of your Composer package repository.

Narrowspark Automatic checks all packages for the `automatic-configurator` package type and register it to Automatic.

After the registration, it will search for all classes found in your composer.json `autoload` section. The classes are added to the `configurators` section in your `automatic.lock` file.

The following example shows you, how your `composer.json` can look:

```json
{
    "name": "narrowspark/configurators",
    "type": "automatic-configurator",
    "license": "MIT",
    "require": {
        "php": "^7.2",
        "ext-mbstring": "*"
    },
    "require-dev": {
        "narrowspark/automatic-common": "^0.4.0"
    },
    "autoload": {
        "psr-4": {
            "Narrowspark\\Automatic\\Configurator\\": "src/"
        },
        "exclude-from-classmap": [
            "tests/"
        ]
    },
    "minimum-stability": "dev",
    "prefer-stable": true
}
```

### Creating Package Configurators

Narrowspark Automatic Configurators must be stored inside your Composer package repository in a `Automatic` folder.

Add a new key `custom-configurators` to the `automatic.json` file or to `extra automatic ` section in your composer.json file to register new Package Configurator(s).

This example shows you, how to add a new Package Configurator in your `composer.json` file:

```json
{
    "require-dev": {
        "narrowspark/automatic-common": "^0.4.0"
    },
    "automatic": {
        "custom-configurators" : {
            "name of the configurator": "Your\\Package\\Configurator"
        }
    }
}
```

`narrowspark/automatic-common` is required for creating a Configurator, please add it to the `dev-require` section in your composer.json file.

After you choose a way, you can create your Configurator(s).

> NOTE: You can't overwrite registered configurators.

To create a configurator you need to extend the `Narrowspark\Automatic\Common\Configurator\AbstractConfigurator` class.

The example below shows you, how your configurator class should look after the `Narrowspark\Automatic\Common\Configurator\AbstractConfigurator` was extend:

```php
<?php
declare(strict_types=1);

use Narrowspark\Automatic\Common\Configurator\AbstractConfigurator;
use Narrowspark\Automatic\Common\Contract\Package as PackageContract;

final class YourConfigurator extends AbstractConfigurator
{
    /**
     * {@inheritdoc}
     */
    public static function getName(): string
    {
        return 'the configurator name';
    }
    
    /**
     * {@inheritdoc}
     */
    public function configure(PackageContract $package): void
    {
        // your code
    }
    
    /**
     * {@inheritdoc}
     */
    public function unconfigure(PackageContract $package): void
    {
        // your code
    }
}
```