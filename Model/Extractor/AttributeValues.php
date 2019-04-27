<?php
namespace Skwirrel\Pim\Model\Extractor;

use Skwirrel\Pim\Model\Import\Attribute;
use Skwirrel\Pim\Model\Mapping;

class AttributeValues
{
    public function extract($product){

        $attributes = [];
        $features = $product->_etim->_etim_features;
        foreach($features as $featureCode => $feature){
            $attributes[strtolower($featureCode)] = $this->extractValue($feature);
        }

        return $attributes;

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


}