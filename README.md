# FIPS data for States and Counties of the United States

## Installation

Ensure you have [composer](http://getcomposer.org) installed, then run the following command:

    composer require lyonstahl/fips

That will fetch the library and its dependencies inside your vendor folder.

## Requirements

-   [PHP 7.3+](https://www.php.net)
-   [Composer 2.0+](https://getcomposer.org)

## Usage

Start using either the `State` or `County` class to get the data you need.

```php
$state = State::fromName('California');
echo $state->fips; // 06
echo $state->abbreviation; // CA

$counties = $state->getCounties();
echo $counties[0]->name; // Alameda
```

```php
$county = County::fromFips('06037');
echo $county->name; // Los Angeles
echo $county->getState()->name; // California
```

Both classes have four common static methods:

-   `fromAny((string $value)` - Get a State or County object from any identifier. Function will attempt to guess the type of identifier.
-   `fromName(string $name)` - Get the State or County object from its name.
-   `fromAbbr(string $abbreviation)` - Get the State object from its abbreviation.
-   `fromFips(string $fips)` - Get the County object from its FIPS code.

Finally, both classes are connected, meaning you can get the State object from a County object and vice versa.

-   `State::` `getCounties()` - This will fetch all the counties for the state.
-   `County::` `$state` - State object is available as a property.

## Running for development with Docker

We have included a Dockerfile to make it easy to run the tests and debug the code. You must have Docker installed. The following commands will build the image and run the container:

1. `docker build -t lyonstahl/fips --build-arg PHP_VERSION=8 .`
2. `docker run -it --rm -v ${PWD}:/var/www/app lyonstahl/fips sh`

## Debugging with XDebug in VSCode

Docker image is configured with XDebug. To debug the code with VSCode, follow these steps:

1.  Install the [PHP Debug extension](https://marketplace.visualstudio.com/items?itemName=xdebug.php-debug) in VSCode
2.  Add a new PHP Debug configuration in VSCode:

        {
            "name": "XDebug Docker",
            "type": "php",
            "request": "launch",
            "port": 9003,
            "pathMappings": {
                "/var/www/app/": "${workspaceRoot}/"
            }
        }

3.  `docker run -it --rm -v ${PWD}:/var/www/app --add-host host.docker.internal:host-gateway lyonstahl/fips sh`
4.  Start debugging in VSCode with the 'XDebug Docker' configuration.

## Testing

This library ships with PHPUnit for development. Composer file has been configured with some scripts, run the following command to run the tests:

    composer test
