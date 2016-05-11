<?php

namespace Casinelli\Currency\Traits;

use Symfony\Component\DomCrawler\Crawler;

trait BocExchangeRateScrapper
{
    private function getCrawler($html)
    {
        $crawler = new Crawler();
        $crawler->addHtmlContent($html);
        return $crawler;
    }

    private function retrieveMiddleRates($html)
    {
        $crawler = $this->getCrawler($html);
        $xToCnyRates = [];
        $crawler->filterXPath('//body/table[2]/tr/td[2]/table[2]/tr/td/table/tr')->each(function ($tr, $i) use (&$xToCnyRates) {
            if ($i == 0)
                return true;
            $tds = $tr->children();
            $code = $tds->eq(0)->text();
            $middleRate = $tds->eq(5)->text();
            $xToCnyRates[$code] = $middleRate;
            $xToCnyRates[$code] = $middleRate;
        });
        return $xToCnyRates;
    }

    private function convertToUsdRates($xToCnyRates, $defaultCurrencyToCnyRate)
    {
        $rs = [];

        //all rates excepts for CNY
        foreach ($xToCnyRates as $code => $toCnyRate) {
            $rs[$code] = $defaultCurrencyToCnyRate / $xToCnyRates;
        }

        //cny
        $unit = config('currency.boc_unit');
        $rs['CNY'] = $defaultCurrencyToCnyRate / $unit;

        return $rs;
    }
}