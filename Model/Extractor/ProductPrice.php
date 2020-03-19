<?php
namespace Skwirrel\Pim\Model\Extractor;

use Skwirrel\Pim\Model\Import\Attribute;
use Skwirrel\Pim\Model\Mapping;

class ProductPrice
{
    public function extract($product){

        $prices = [];

        $tradeItems = isset($product->_trade_items) ? (array) $product->_trade_items : [];
        foreach($tradeItems as $tradeItem){
            $tradeItemPrices = isset($tradeItem->_trade_item_prices) ? (array) $tradeItem->_trade_item_prices : [];
            foreach($tradeItemPrices as $tradeItemPrice){
                if($this->isValidPrice($tradeItemPrice)){
                    $prices[] =  $tradeItemPrice->gross_price;
                }
            }
        }

        return count($prices) ? $prices[0] : 0;

    }

    public function extractValue($feature){

        switch($feature->etim_feature_type){
            case Attribute::FEATURE_TYPE_LOGICAL:
                return $feature->logical_value;
            case Attribute::FEATURE_TYPE_NUMERIC:
                return $feature->numeric_value;
            case Attribute::FEATURE_TYPE_SELECT:
                return $this->extractSelectValue($feature);
        }
    }

    private function extractSelectValue($feature)
    {
        $defaultLanguage = 'en';
        $translations = $feature->_etim_value_translations;

        if(isset($translations->{$defaultLanguage})){
            return $translations->{$defaultLanguage}->etim_value_description;
        }

        $translation = array_values( (array) $translations);
        return isset($translation[0]) ? $translation[0]->etim_value_description : null;

    }

    private function isValidPrice($tradeItemPrice)
    {
        if(isset($tradeItemPrice->price_start_date) && $tradeItemPrice->price_start_date !== ''){
            if(strtotime($tradeItemPrice->price_start_date) > time()){
                return false;
            }
        }

        return true;
    }


}