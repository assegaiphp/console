<div align="center">
    <a href="https://assegaiphp.com/" target="blank"><img src="https://assegaiphp.com/images/logos/logo-cropped.png" width="200" alt="Assegai Logo"></a>
</div>

# Assegai Console

## Requirements
- PHP 8.2 (minimum)
- Composer 2.x.x

## Description

The AssegaiPHP Console is a command-line interface tool that makes it easy to create, develop, and maintain Assegai applications. It provides various features, such as creating a new project, running the application in development mode, and building and packaging it for production deployment.

The AssegaiPHP Console includes built-in support for the collection of [schematics] available at @assegaiphp/schematics, allowing for easy initialization, development, and maintenance of AssegaiPHP applications through scaffolding, development mode serving, and production distribution building and bundling.

## Installation
### Windows
Before we create a new Assegai application on your Windows machine, make sure to install Docker Desktop. Next, you should ensure that Windows Subsystem for Linux 2 (WSL2) is installed and enabled. WSL allows you to run Linux binary executables natively on Windows 10. Information on how to install and enable WSL2 can be found within Microsoft's developer environment documentation.

> After installing and enabling WSL2, you should ensure that Docker Desktop is configured to use the WSL2 backend.

Next, you are ready to create your first Assegai project. Launch Windows Terminal and begin a new terminal session for your WSL2 Linux operating system. Next, you can use a simple terminal command to create a new Assegai project. For example, to create a new Assegai application in a directory named "example-app", you may run the following command in your terminal:

```
$ composer require assegaiphp/console
```

## Usage

Learn more in the [official documentation](https://assegaiphp.com/guide/cli/overview).

## Stay in touch

* Author - [Andrew Masiye](https://twitter.com/feenix11), [Daniel Kaluba](https://twitter.com/ZombieKlassic)
* Website - [https://atatusoft.com](https://atatusoft.com/)
* X - [@assegaiphp](https://twitter.com/assegaiphp)

## License

Assegai Console is [MIT Licensed](LICENSE)

[schematics]: https://github.com/angular/angular-cli/tree/master/packages/angular_devkit/schematics