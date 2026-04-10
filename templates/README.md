<div align="center">
  <a href="https://assegaiphp.com/" target="blank"><img src="https://assegaiphp.com/images/logos/logo-cropped.png" width="200" alt="Assegai Logo"></a>
</div>

# AssegaiPHP Application

This project was scaffolded with `assegai new`.

It gives you a working Assegai app with:
- a root module in `src/AppModule.php`
- a controller and service in `src/`
- project settings in `assegai.json`
- environment-aware app config in `config/default.php`

## Requirements

- PHP 8.3+
- Composer 2.x

## Getting Started

If you created this app with `assegai new`, dependencies were already installed for you.

If you cloned the repository later, install them with:

```bash
composer install
```

Then start the app:

```bash
assegai serve
```

You can also use the project Composer script if you prefer:

```bash
composer run start
```

## Useful Commands

Run the test suite:

```bash
assegai test
```

Generate a new feature:

```bash
assegai generate resource movies
```

Configure a database:

```bash
assegai database:configure cinema_db
```

Add ORM support when you need the data layer:

```bash
assegai add orm
```

## Runtime Options

The default local runtime uses PHP's built-in server.

If you want to try the long-lived runtime path, you can use OpenSwoole:

```bash
assegai serve --runtime=openswoole
```

The OpenSwoole path is currently experimental. Treat it as an opt-in runtime for careful testing, not a required switch for every app.

## Project Layout

- `src/AppModule.php`: the root module for your app
- `src/AppController.php`: a starter controller
- `src/AppService.php`: a starter service
- `config/default.php`: shared app and database configuration
- `assegai.json`: Assegai project settings, scripts, runtime config, and CLI behavior
- `bootstrap.php`: entry point used by the local server

## Learn More

- Guide: [https://assegaiphp.com/guide](https://assegaiphp.com/guide)
- Support: [https://assegaiphp.com/support](https://assegaiphp.com/support)
- Website: [https://assegaiphp.com](https://assegaiphp.com)

## License

This project is released under the MIT License.
