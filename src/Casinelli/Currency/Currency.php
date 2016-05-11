<?php

namespace Casinelli\Currency;

use Cache;
use Cookie;
use Input;
use Session;
use Casinelli\Currency\Entities\Currency as CurrencyEntity;

class Currency
{
    /**
     * Laravel application.
     *
     * @var \Illuminate\Foundation\Application
     */
    public $app;

    /**
     * Default currency.
     *
     * @var string
     */
    protected $code;

    /**
     * All currencies.
     *
     * @var array
     */
    protected $currencies = [];

    /**
     * Create a new instance.
     *
     * @param \Illuminate\Foundation\Application $app
     */
    public function __construct($app)
    {
        $this->app = $app;

        // Initialize Currencies
        $this->setCacheCurrencies();

        // Check for a user defined currency
        if (Input::get('currency') && array_key_exists(Input::get('currency'), $this->currencies)) {
            $this->setCurrency(Input::get('currency'));
        }
        elseif (Session::get('currency') && array_key_exists(Session::get('currency'), $this->currencies)) {
            $this->setCurrency(Session::get('currency'));
        }
        elseif (Cookie::get('currency') && array_key_exists(Cookie::get('currency'), $this->currencies)) {
            $this->setCurrency(Cookie::get('currency'));
        }
        else {
            $this->setCurrency($this->app['config']['currency.default']);
        }
    }

    public function format($number, $currency = null, $symbolStyle = '%symbol%', $inverse = false, $roundingType = '', $precision = null, $decimalPlace = null)
    {
        if (!$currency || !$this->hasCurrency($currency)) {
            $currency = $this->code;
        }

        $symbolLeft = $this->currencies[$currency]['symbol_left'];
        $symbolRight = $this->currencies[$currency]['symbol_right'];
        if (is_null($decimalPlace)) {
            $decimalPlace = $this->currencies[$currency]['decimal_place'];
        }
        $decimalPoint = $this->currencies[$currency]['decimal_point'];
        $thousandPoint = $this->currencies[$currency]['thousand_point'];

        if ($value = $this->currencies[$currency]['value']) {
            if ($inverse) {
                $value = $number * (1 / $value);
            }
            else {
                $value = $number * $value;
            }
        }
        else {
            $value = $number;
        }

        $string = '';

        if ($symbolLeft) {
            $string .= str_replace('%symbol%', $symbolLeft, $symbolStyle);

            if ($this->app['config']['currency.use_space']) {
                $string .= ' ';
            }
        }

        switch ($roundingType) {
            case 'ceil':
            case 'ceiling':
                if ($precision != null) {
                    $multiplier = pow(10, -(int)$precision);
                }
                else {
                    $multiplier = pow(10, -(int)$decimalPlace);
                }

                $string .= number_format(ceil($value / $multiplier) * $multiplier, (int)$decimalPlace, $decimalPoint, $thousandPoint);
                break;

            case 'floor':
                if ($precision != null) {
                    $multiplier = pow(10, -(int)$precision);
                }
                else {
                    $multiplier = pow(10, -(int)$decimalPlace);
                }

                $string .= number_format(floor($value / $multiplier) * $multiplier, (int)$decimalPlace, $decimalPoint, $thousandPoint);
                break;

            default:
                if ($precision == null) {
                    $precision = (int)$decimalPlace;
                }

                $string .= number_format(round($value, (int)$precision), (int)$decimalPlace, $decimalPoint, $thousandPoint);
                break;
        }

        if ($symbolRight) {
            if ($this->app['config']['currency.use_space']) {
                $string .= ' ';
            }

            $string .= str_replace('%symbol%', $symbolRight, $symbolStyle);
        }

        return $string;
    }

    public function normalize($number, $dec = false)
    {
        $value = $this->currencies[$this->code]['value'];

        if ($value) {
            $value = $number * $value;
        }
        else {
            $value = $number;
        }

        if (!$dec) {
            $dec = $this->currencies[$this->code]['decimal_place'];
        }

        return number_format(round($value, (int)$dec), (int)$dec, '.', '');
    }

    public function rounded($number, $decimalPlace = 0, $currency = null)
    {
        return $this->style($number, $currency, $decimalPlace);
    }

    public function getCurrencySymbol($right = false)
    {
        if ($right) {
            return $this->currencies[$this->code]['symbol_right'];
        }

        return $this->currencies[$this->code]['symbol_left'];
    }

    public function hasCurrency($currency)
    {
        return isset($this->currencies[$currency]);
    }

    public function setCurrency($currency)
    {
        $this->code = $currency;

        if (Session::get('currency') != $currency) {
            Session::set('currency', $currency);
        }

        if (Cookie::get($this->app['config']['currency.cookie_name']) != $currency) {
            $cookie = Cookie::make($this->app['config']['currency.cookie_name'], $currency, $this->app['config']['currency.cookie_days'] * 24 * 60);
            // Queues the cookie so it's automatically added to the next response
            \Cookie::queue($cookie);
        }
    }

    /**
     * Return the current currency code.
     *
     * @return string
     */
    public function getCurrencyCode()
    {
        return $this->code;
    }

    /**
     * Return the current currency if the
     * one supplied is not valid.
     *
     * @return array
     */
    public function getCurrency($currency = '')
    {
        if ($currency && $this->hasCurrency($currency)) {
            return $this->currencies[$currency];
        }
        else {
            return $this->currencies[$this->code];
        }
    }

    public function convert($number, $fromCurrencyCode, $toCurrencyCode)
    {
        $fromCurrency = $this->getCurrency($fromCurrencyCode);
        $toCurrency = $this->getCurrency($toCurrencyCode);

        return round($number / $fromCurrency['value'] * $toCurrency['value'], 2);
    }

    // Same as format, but without any value conversion
    public function style($number, $currency = null, $decimalPlace = null)
    {
        $symbolStyle = '%symbol%';

        if ($currency && $this->hasCurrency($currency)) {
            $symbolLeft = $this->currencies[$currency]['symbol_left'];
            $symbolRight = $this->currencies[$currency]['symbol_right'];
            if (is_null($decimalPlace)) {
                $decimalPlace = $this->currencies[$currency]['decimal_place'];
            }
            $decimalPoint = $this->currencies[$currency]['decimal_point'];
            $thousandPoint = $this->currencies[$currency]['thousand_point'];
        }
        else {
            $symbolLeft = $this->currencies[$this->code]['symbol_left'];
            $symbolRight = $this->currencies[$this->code]['symbol_right'];
            if (is_null($decimalPlace)) {
                $decimalPlace = $this->currencies[$this->code]['decimal_place'];
            }
            $decimalPoint = $this->currencies[$this->code]['decimal_point'];
            $thousandPoint = $this->currencies[$this->code]['thousand_point'];

            $currency = $this->code;
        }

        $value = $number;

        $string = '';

        if ($symbolLeft) {
            $string .= str_replace('%symbol%', $symbolLeft, $symbolStyle);

            if ($this->app['config']['currency.use_space']) {
                $string .= ' ';
            }
        }

        $precision = (int)$decimalPlace;

        $string .= number_format(round($value, (int)$precision), (int)$decimalPlace, $decimalPoint, $thousandPoint);

        if ($symbolRight) {
            if ($this->app['config']['currency.use_space']) {
                $string .= ' ';
            }

            $string .= str_replace('%symbol%', $symbolRight, $symbolStyle);
        }

        return $string;
    }

    /**
     * Initialize Currencies.
     */
    public function setCacheCurrencies()
    {
        $db = $this->app['db'];

        $this->currencies = Cache::rememberForever('casinelli.currency', function () use ($db) {
            $cache = [];

            foreach (CurrencyEntity::all() as $currency) {
                $cache[$currency->code] = [
                    'id'             => $currency->id,
                    'title'          => $currency->title,
                    'symbol_left'    => $currency->symbol_left,
                    'symbol_right'   => $currency->symbol_right,
                    'decimal_place'  => $currency->decimal_place,
                    'value'          => $currency->value,
                    'decimal_point'  => $currency->decimal_point,
                    'thousand_point' => $currency->thousand_point,
                    'code'           => $currency->code,
                ];
            }

            return $cache;
        });
    }
}
