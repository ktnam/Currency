# Currency for Laravel 5

[![Latest Stable Version](https://poser.pugx.org/casinelli/currency/v/stable.png)](https://packagist.org/packages/casinelli/currency) [![Total Downloads](https://poser.pugx.org/casinelli/currency/downloads.png)](https://packagist.org/packages/casinelli/currency)

Handles currency for Laravel 5.

----------

## Installation

- [Currency on Packagist](https://packagist.org/packages/casinelli/currency)
- [Currency on GitHub](https://github.com/casinelli/laravel-currency)

To get the latest version of Currency simply require it in your `composer.json` file.

~~~
"casinelli/currency": "dev-master"
~~~

You'll then need to run `composer install` to download it and have the autoloader updated.

Once Currency is installed you need to register the service provider with the application. Open up `app/config/app.php` and find the `providers` key.

~~~php
'providers' => [

    Casinelli\Currency\CurrencyServiceProvider::class,

]
~~~

Currency also ships with a facade which provides the static syntax for creating collections. You can register the facade in the `aliases` key of your `app/config/app.php` file.

~~~php
'aliases' => [

    'Currency' => Casinelli\Currency\Facades\Currency::class,

]
~~~

Create configuration file and migration table using artisan

~~~
$ php artisan vendor:publish
~~~

## Artisan Commands

### Updating Exchange

By default exchange rates are updated from Finance Yahoo.com.

~~~
php artisan currency:update
~~~

To upate from OpenExchangeRates.org

~~~
php artisan currency:update --openexchangerates
~~~

 > Note: An API key is needed to use [OpenExchangeRates.org](http://OpenExchangeRates.org). Add yours to the config file.

To upate from Bank of China exchange rates webpage

~~~
php artisan currency:update --bocexchangerates
~~~

### Cleanup

Used to clean the Laravel cached exchanged rates and refresh it from the database. Note that cached exchanged rates are cleared after they are updated using one of the command above.

~~~
php artisan currency:cleanup
~~~

## Rendering

Using the Blade helper

~~~html
@currency(12.00, 'USD')
~~~

- The first parameter is the amount.
- *optional* The second parameter is the ISO 4217 currency code. If not set it will use the default set in the config file.

~~~php
echo Currency::format(12.00, 'USD');
~~~

For easy output of rounded values:

~~~php
echo Currency::rounded(12.80);  // Will output $12

// All the parameters
echo Currency::rounded(12.80, 0, 'USD');
~~~

## Change Log
