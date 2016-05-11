<?php

namespace Casinelli\Currency;

use Illuminate\Support\ServiceProvider;

class CurrencyServiceProvider extends ServiceProvider
{
    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = false;

    /**
     * Bootstrap the application events.
     */
    public function boot()
    {
        $this->publishes([
            __DIR__ . '/../../config/currency.php' => config_path('currency.php'),
        ], 'config');

        $this->publishes([
            __DIR__ . '/../../migrations' => base_path('/database/migrations'),
        ], 'migrations');
    }

    /**
     * Register the service provider.
     */
    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/currency.php', 'currency');

        // Register providers.
        $this->registerCurrency();

        // Register commands.
        $this->registerCurrencyCommands();

        //extend blade engine by adding @currency compile function
        $this->app['view.engine.resolver']->resolve('blade')->getCompiler()->extend(function ($view) {
            $html = '$1<?php echo Currency::format$2; ?>';

            return preg_replace("/(?<!\w)(\s*)@currency(\s*\(.*\))/", $html, $view);
        });

        // Assign commands.
        $this->commands(
            'currency.update',
            'currency.cleanup'
        );
    }

    /**
     * Register currency provider.
     */
    public function registerCurrency()
    {
        $this->app['currency'] = $this->app->share(function ($app) {
            return new Currency($app);
        });
    }

    /**
     * Register generator of Currency.
     */
    public function registerCurrencyCommands()
    {
        $this->app['currency.update'] = $this->app->share(function ($app) {
            return new Commands\CurrencyUpdateCommand($app);
        });

        $this->app['currency.cleanup'] = $this->app->share(function ($app) {
            return new Commands\CurrencyCleanupCommand();
        });
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return ['currency'];
    }
}
