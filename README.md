# Composer Platform Packages Plugin

Composer Platform Packages Plugin is a Composer plugin that simplifies the distribution of platform-specific packages,
such as binaries, across different operating systems and architectures. It allows you to automatically download and
manage platform-dependent packages with zero configuration overhead.

## Features

- **Multi-Platform Support**: Easily specify different package sources for various platforms and architectures
- **Automatic Download**: Seamlessly download platform-specific packages during Composer installation
- **Version Management**: Control package versions with built-in versioning support
- **Composer Integration**: Uses Composer's native mechanisms for package management, including caching

## Use Cases

This plugin is perfect for libraries that need:

- Cross-platform binary distributions
- Machine learning models
- Platform-specific runtime dependencies
- Native extension binaries

## Requirements

- PHP 8.1 or higher
- Composer 2.0 or higher

## Installation

You can install Composer Platform Packages Plugin using Composer:

```bash
composer require codewithkyrian/composer-platform-packages
```

## Usage

Once installed, you can add platform-specific packages to your `composer.json` using the `platform-packages` extra
configuration:

```json
{
  "name": "org/library",
  "version": "1.0.0",
  "type": "library",
  "require": {
    "codewithkyrian/composer-platform-packages": "^1.0"
  },
  "extra": {
    "platform-packages": {
      "package-name": {
        "version": "1.0.0",
        "platforms": {
          "platform-identifier": "package-url"
        }
      }
    }
  }
}
```

You can specify multiple platform-specific packages in the `platform-packages` configuration. They will be batched and downloaded in parallel.

## Platform Identifiers

Platform Identifiers are used to specify the platform-specific package URL. Platform identifiers can be:

- **Base platform names**: The supported base platform names are `linux`, `darwin`, `win` and `raspberrypi`.
- **Specific architectures**: Formed by joining the platform identifier with the architecture identifier (`-`), e.g.
  `darwin-arm64`, `darwin-x86_64`, `win-32`, `win-64`, `linux-aarch64`.
- **Universal**: Use `all` to cover every platform

## URL Configuration

You can configure the package URLs using the `platforms` key in the `platform-packages` extra configuration. All URLs
must be reachable, and must point to a valid archive file.

The plugin automatically detects the archive type based on the file extension, and falls back to HTTP header detection
if the archive type cannot be determined. Supported archive types are: `zip`, `tar`, `tar.gz`, `tar.bz2`, `tar.xz`,
`tgz`, `tbz2`, `txz`. You can also specify a custom archive type using the `type` key in the `platforms` configuration.

```json
{
  "platform-packages": {
    "ffmpeg": {
      "version": "4.4.2",
      "type": "tar.xz",
      "platforms": {
        "linux": "https://example.com/ffmpeg-linux-{version}",
        "darwin-arm64": "https://example.com/ffmpeg-darwin-arm64-{version}"
      }
    }
  }
}
```

## Version Management

Versioning is smart and adaptable to your needs.

- **Automatic Versioning**: It automatically uses and updates the version of the platform-specific package based on the
  parent package version.
- **Explicit Versioning**: You can explicitly specify a custom version for the platform-specific package using the
  `version` key
- **Root Projects**: For root projects without a specific version, it defaults to `dev-master`
- **Version Placeholders**: Use version placeholders in the `platforms` configuration to control the url of the
  platform-specific package based on it's version (optional).

Explicitly specifying a version will override the automatic versioning, and in most cases is recommended. This is
because it allows you to update your base package without having to update the platform-specific packages. Composer
won't re-download the platform-specific package if it's version doesn't change, saving you time and bandwidth.

## Example Configuration

```json
{
  "name": "org/library",
  "type": "library",
  "require": {
    "codewithkyrian/platform-package-installer": "^1.0"
  },
  "extra": {
    "platform-packages": {
      "ffmpeg": {
        "version": "4.4.2",
        "platforms": {
          "linux": "https://example.com/ffmpeg-linux-{version}.tar.xz",
          "darwin-arm64": "https://example.com/ffmpeg-darwin-arm64-{version}.tar.xz",
          "win-64": "https://example.com/ffmpeg-win64-{version}.tar.xz"
        }
      }
    }
  }
}
```

## Accessing Installed Packages

Once installed, you can access the installed platform-dependent packages information in your library code using the
`PlatformVersions` class:

```php
// Get the installation path of a platform package
$path = PlatformVersions::getInstallPath('org/library', 'ffmpeg'); // string
// Check if a platform package is installed
$isInstalled = PlatformVersions::isInstalled('org/library', 'ffmpeg'); // bool
// Get the installed version
$version = PlatformVersions::getVersion('org/library', 'ffmpeg'); // string
```

## Package Uninstallation

When you uninstall your main package, associated platform-specific packages are automatically removed, keeping your
system clean.

## Tests

The `tests` folder contains a suite of tests that verify the behavior of the plugin. To run the tests, you need
to install the development dependencies using the `composer install --dev` command. Then, run the tests using either
the `composer test` command or the `./vendor/bin/pest` command.

## License

This project is licensed under the MIT License. See
the [LICENSE](https://github.com/codewithkyrian/composer-platform-packages/blob/main/LICENSE) file for more information.

## Contributing

Pull requests are welcome. For major changes, please open an issue first to discuss what you would like to change.
