<?php

namespace Casinelli\Currency\Commands;

use Cache;
use Casinelli\Currency\Entities\Currency;
use Casinelli\Currency\Traits\BocExchangeRateScrapper;
use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;

class CurrencyUpdateCommand extends Command
{
    use BocExchangeRateScrapper;

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'currency:update';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update exchange rates from Yahoo';

    /**
     * Application instance.
     *
     * @var \Illuminate\Foundation\Application
     */
    protected $app;

    /**
     * HTTP Proxy.
     *
     * @var string
     */
    protected $proxy;

    /**
     * Create a new command instance.
     *
     * @param $app \Illuminate\Foundation\Application
     */
    public function __construct($app)
    {
        $this->app = $app;
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function fire()
    {
        // Get Settings
        $defaultCurrency = $this->app['config']['currency.default'];

        try {
            // Get rates
            if ($this->input->getOption('openexchangerates'))
                $this->updateFromOpenExchangeRates($defaultCurrency);
            elseif ($this->input->getOption('bocexchangerates'))
                $this->updateFromBocExchangeRates($defaultCurrency);
            else
                $this->updateFromYahoo($defaultCurrency);
        }
        catch (\Exception $e) {
            $this->error($e->getMessage());
        }
    }

    private function updateFromYahoo($defaultCurrency)
    {
        $this->info('Updating currency exchange rates from Finance Yahoo...');

        $data = [];

        // Get all currencies
        foreach (Currency::all() as $currency) {
            $data[] = "{$defaultCurrency}{$currency->code}=X";
        }

        // Ask Yahoo for exchange rate
        if ($data) {
            //disable all currencies
            $this->disableAll();

            //download the currency data
            $content = $this->request('http://download.finance.yahoo.com/d/quotes.csv?s=' . implode(',', $data) . '&f=sl1&e=.csv');

            $lines = explode("\n", trim($content));

            // Update each rate
            foreach ($lines as $line) {
                $code = substr($line, 4, 3);
                $value = substr($line, 11, 6);
                if ($value)
                    $this->updateCurrency($code, $value);
            }

            Cache::forget('casinelli.currency');
        }

        $this->info('Update!');
    }

    private function updateFromOpenExchangeRates($defaultCurrency)
    {
        $this->info('Updating currency exchange rates from OpenExchangeRates.org...');

        if (!$api = $this->app['config']['currency.api_key'])
            throw new \Exception('An API key is needed from OpenExchangeRates.org to continue.');

        // Make request
        $content = json_decode($this->request("http://openexchangerates.org/api/latest.json?base={$defaultCurrency}&app_id={$api}"));

        // Error getting content?
        if (isset($content->error))
            throw new \Exception($content->description);

        //Disable all currencies
        $this->disableAll();

        // Update each rate
        foreach ($content->rates as $code => $value)
            $this->updateCurrency($code, $value);

        Cache::forget('casinelli.currency');

        $this->info('Updated!');
    }

    private function updateFromBocExchangeRates($defaultCurrency)
    {
        $this->info('Updating currency exchange rates from Bank of China website...');

        //load exchange rates webpage url from configuration file
        $url = $this->app['config']['currency.boc_url'];

        //make request
        $html = $this->request($url);

        //crawl the middle rates
        $xToCnyRates = $this->retrieveMiddleRates($html);
        if (!isset($xToCnyRates[$defaultCurrency]))
            throw new \Exception('Default currency rate not found.');

        //convert CNY based rates to USD based rates
        $defaultCurrencyToCnyRate = $xToCnyRates[$defaultCurrency];
        $rates = $this->convertToUsdRates($xToCnyRates, $defaultCurrencyToCnyRate);

        //disable all currencies
        $this->disableAll();

        //update the currency rates to database
        foreach ($rates as $code => $value)
            $this->updateCurrency($code, $value);

        Cache::forget('casinelli.currency');

        $this->info('Updated!');
    }

    private function request($url)
    {
        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 20);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.1) Gecko/20061204 Firefox/2.0.0.1');
        curl_setopt($ch, CURLOPT_HTTPGET, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 2);
        curl_setopt($ch, CURLOPT_MAXCONNECTS, 2);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        //Custom options
        $opts = $this->app['config']['currency.curl_opts']; //CURLOPT_CONNECTTIMEOUT=20;CURLOPT_MAXREDIRS=2;
        if ($opts) {
            $opts = explode(';', $opts); //0=CURLOPT_CONNECTTIMEOUT=20, 1=CURLOPT_MAXREDIRS=2
            foreach ($opts as $opt) {
                $arr = explode('=', $opt); //0=CURLOPT_CONNECTTIMEOUT, 1=20
                if (isset($arr[0], $arr[1]) && defined($arr[0])) {
                    curl_setopt($ch, constant($arr[0]), $arr[1]);
                }
            }
        }

        $response = curl_exec($ch);
        $error = $response ?: curl_error($ch);
        curl_close($ch);

        if (!$response)
            throw new \Exception($error);

        return $response;
    }

    private function disableAll()
    {
        return Currency::query()->update(['status' => Currency::STATUS_DISABLED]);
    }

    private function updateCurrency($code, $value)
    {
        return Currency::where('code', $code)
            ->update([
                'value'  => $value,
                'status' => Currency::STATUS_ENABLED,
            ]);
    }

    private function updateOrCreateCurrency($code, $value)
    {
        return Currency::updateOrCreate(
            [
                'code' => $code,
            ],
            [
                'code'   => $code,
                'value'  => $value,
                'status' => Currency::STATUS_ENABLED,
            ]
        );
    }

    /**
     * Get the console command options.
     *
     * @return array
     */
    protected function getOptions()
    {
        return [
            ['openexchangerates', 'o', InputOption::VALUE_NONE, 'Get rates from OpenExchangeRates.org'],
            ['bocexchangerates', 'b', InputOption::VALUE_NONE, 'Get rates from Bank of China website (www.boc.cn)'],
        ];
    }
}
