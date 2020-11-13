---
title: Local development
current_menu: local-development
---

## PHP functions

The `vendor/bin/bref local` command invokes your [PHP functions](/docs/runtimes/function.md) locally. You can provide an event if your function expects one.

For example, given this function:

```php
return function (array $event) {
    return 'Hello ' . ($event['name'] ?? 'world');
};
```

```yaml
# ...

functions:
    hello:
        handler: my-function.php
        layers:
            - ${bref:layer.php-74}
```

You can invoke it with or without event data:

```bash
$ vendor/bin/bref local hello
Hello world

# With JSON event data
$ vendor/bin/bref local hello '{"name": "Jane"}'
Hello Jane

# With JSON in a file
$ vendor/bin/bref local hello --file=event.json
Hello Jane
```

The `bref local` command runs using the local PHP installation. If you prefer to run commands using the same environment as Lambda, you can use Docker.

Here is an example, feel free to adjust it to fit your needs:

```bash
docker run --rm -it -v $(PWD):/var/task:ro,delegated bref/php-74 vendor/bin/bref local hello
```

## HTTP applications

If you want to keep things simple, you can run your PHP application like you did without Bref. For example with your favorite framework:

- Laravel via `php artisan serve` or [Homestead](https://laravel.com/docs/5.7/homestead) or [Laravel Valet](https://laravel.com/docs/5.7/valet)
- Symfony via `php bin/console server:start` ([documentation](https://symfony.com/doc/current/setup/built_in_web_server.html))

If you are not using any framework, you can use PHP's built-in server:

```bash
php -S localhost:8000 index.php
# The application is now available at http://localhost:8000/
```

In order to run the application locally in an environment closer to production, you can use the [Bref Docker images](https://hub.docker.com/u/bref). For example for an HTTP application, create the following `docker-compose.yml`:

```yaml
version: "3.5"

services:
    web:
        image: bref/fpm-dev-gateway
        ports:
            - '8000:80'
        volumes:
            - .:/var/task
        depends_on:
            - php
        environment:
            HANDLER: index.php
    php:
        image: bref/php-74-fpm-dev
        volumes:
            - .:/var/task:ro
```

After running `docker-compose up`, the application will be available at [http://localhost:8000/](http://localhost:8000/).

The `HANDLER` environment variable lets you define which PHP file will be handling all HTTP requests. This should be the same handler that you have defined in `serverless.yml` for your HTTP function.

> Currently the Docker image support only one PHP handler. If you have multiple HTTP functions in `serverless.yml`, you can duplicate the service in `docker-compose.yml` to have one container per lambda function.

### Read-only filesystem

The code will be mounted as read-only in `/var/task`, just like in Lambda. However when developing locally, it is common to regenerate cache files on the fly (for example Symfony or Laravel cache). You have 2 options:

- mount the whole codebase as writable:

    ```yaml
        volumes:
            - .:/var/task
    ```
- mount a specific cache directory as writable (better):

    ```yaml
        volumes:
            - .:/var/task:ro
            - ./cache:/var/task/cache
    ```

### Assets

If you want to serve assets locally, you can define a `DOCUMENT_ROOT` environment variable:

```yaml
version: "3.5"

services:
    web:
        image: bref/fpm-dev-gateway
        ports:
            - '8000:80'
        volumes:
            - .:/var/task
        links:
            - php
        environment:
            HANDLER: public/index.php
            DOCUMENT_ROOT: public
    php:
        image: bref/php-74-fpm-dev
        volumes:
            - .:/var/task:ro
```

In the example above, a `public/assets/style.css` file will be accessible at `http://localhost:8000/assets/style.css`.

> Be aware that serving assets in production will not work like this out of the box. You will need [to use a S3 bucket](/docs/runtimes/http.md#assets).

### Xdebug

The docker container `bref/php-<version>-fpm-dev` comes with Xdebug pre-installed.

To enable it, create a `php/conf.dev.d/php.ini` file in your project containing:

```ini
zend_extension=xdebug.so
```

Now start the debug session by issuing a request to your application [in the browser](https://xdebug.org/docs/remote#starting).

#### Xdebug and MacOS

Docker for Mac uses a virtual machine for running docker. That means you need to use a special host name that is mapped to the host machine's IP address.

The host name to use depends on your version of Docker for Mac:

- v18.03.0-ce-mac59+ uses `host.docker.internal`
- v17.12.0-ce-mac46+ uses `docker.for.mac.host.internal`
- v17.06.0+ uses `docker.for.mac.localhost`

Edit the `php/conf.dev.d/php.ini` file:

```ini
zend_extension=xdebug.so

[xdebug]
xdebug.remote_enable = 1
xdebug.remote_autostart = 0
xdebug.remote_host = 'host.docker.internal'
```

### Blackfire

The development FPM container comes with the blackfire extension. When using docker compose you can add following service configuration for the blackfire agent:

```yaml
services:
  blackfire:
    image: blackfire/blackfire
    environment:
      BLACKFIRE_SERVER_ID: server-id
      BLACKFIRE_SERVER_TOKEN: server-token
```

In order to enable the probe you can create a folder `php/conf.dev.d` in your project and include an ini file enabling blackfire:

```ini
extension=blackfire
blackfire.agent_socket=tcp://blackfire:8707
```

For more details about using blackfire in a docker environment see the [blackfire docs](https://blackfire.io/docs/integrations/docker)

## Console applications

Console applications can be tested just like before: by running the command in your terminal.

For example with Symfony you can run `bin/console <your-command>` , or with Laravel run `php artisan <your-command>`.

If you want to run your console in an environment close to production, you can use the Bref Docker images. Here is an example of a `docker-compose.yml` file:

```yaml
version: "3.5"

services:
    console:
        image: bref/php-74
        volumes:
            - .:/var/task:ro
        entrypoint: php
```

Then commands can be run via:

```bash
docker-compose run console php bin/console <your-command>
```
