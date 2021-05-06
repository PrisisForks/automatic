<h1 align="center">Narrowspark Automatic Security Audit</h1>
<p align="center">
    <a href="https://github.com/narrowspark/automatic/releases"><img src="https://img.shields.io/packagist/v/narrowspark/automatic.svg?style=flat-square"></a>
    <a href="https://php.net/"><img src="https://img.shields.io/badge/php-%5E8.0.0-8892BF.svg?style=flat-square"></a>
    <a href="https://codecov.io/gh/narrowspark/automatic"><img src="https://img.shields.io/codecov/c/github/narrowspark/automatic/master.svg?style=flat-square"></a>
    <a href="#"><img src="https://img.shields.io/badge/style-level%20max-brightgreen.svg?style=flat-square&label=phpstan"></a>
    <a href="https://github.com/semantic-release/semantic-release"><img src="https://img.shields.io/badge/%20%20%F0%9F%93%A6%F0%9F%9A%80-semantic--release-e10079.svg?style=flat-square"></a>
    <a href=".github/CODE_OF_CONDUCT.md"><img src="https://img.shields.io/badge/Contributor%20Covenant-2.0-4baaaa.svg?style=flat-square"></a>
    <a href="https://opensource.org/licenses/MIT"><img src="https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square"></a>
</p>

> **Note** This package is part of the [Narrowspark automatic](https://github.com/narrowspark/automatic).

## Installation

Use [Composer](https://getcomposer.org/) to install this package:

```sh
composer require narrowspark/automatic-security-audit --dev
```

## Usage

The checker will be executed when you launch `composer require` , `composer install` or `composer update`.
If you have alerts in your composer.lock, `composer audit` will print them.

## Versioning

This library follows semantic versioning, and additions to the code ruleset are performed in major releases.

## Changelog

Please have a look at [`CHANGELOG.md`](https://github.com/narrowspark/automatic/blob/master/CHANGELOG.md).

## Contributing

Please have a look at [`CONTRIBUTING.md`](https://github.com/narrowspark/automatic/blob/master/.github/CONTRIBUTING.md).

## Code of Conduct

Please have a look at [`CODE_OF_CONDUCT.md`](https://github.com/narrowspark/automatic/blob/master/.github/CODE_OF_CONDUCT.md).

## Credits

- [Daniel Bannert](https://github.com/prisis)
- [All Contributors](https://github.com/narrowspark/automatic/graphs/contributors)
- Narrowspark Automatic has been inspired by [symfony/flex](https://github.com/symfony/flex)

## License

This package is licensed using the MIT License.

Please have a look at [`LICENSE.md`](LICENSE.md).
