<?php
namespace Skwirrel\Pim\Model\Etim\Converter;

use Skwirrel\Pim\Console\Command\LogicalValue;
use Skwirrel\Pim\Console\Command\NumericalValue;
use Skwirrel\Pim\Console\Command\SelectValue;

class Feature
{
    function convert($feature)
    {
        $feature = (array)$feature;

        $config = [
            'magento' => [
                'input' => 'text',
                'type' => 'varchar',
                'is_global' => 0,
                'class' => '',
                'backend' => '',
                'source' => '',
                'visible' => true,
                'required' => false,
                'searchable' => false,
                'filterable' => false,
                'comparable' => false,
                'user_defined' => true,
                'is_user_defined' => true,
                'visible_on_front' => false,
                'used_in_product_listing' => false,
                'is_unique' => false,
            ]
        ];


        $typeConfig = $this->getTypeConfig($feature);
        if ($typeConfig) {
            $config['magento'] = array_replace($config['magento'], $typeConfig);
        }
        $config['_value']  = $this->getFeatureValue($feature);
        return $config;


    }

    private function parseInput($feature)
    {

    }

    private function parseType($feature)
    {
    }

    private function getTypeConfig($feature)
    {

        switch ($feature['etim_feature_type']) {
            case 'L':
                $config = ['type' => 'int', 'input' => 'boolean'];
                return $config;
            case 'A':

                $config = ['type' => 'int', 'input' => 'select', 'is_global' => 1, '_value_code' => $feature['etim_value_code']];
                return $config;
            case 'R':
            case 'N':
                $config = ['type' => 'decimal', 'input' => 'text'];
                return $config;
                break;
            default:
                print_r($feature);
                return false;

        }
        return false;
    }

    private function getFeatureValue($feature)
    {
        switch ($feature['etim_feature_type']) {
            case 'L':
                return new LogicalValue((int) $feature['logical_value']);

            case 'A':

                if (isset($feature['_etim_value_translations'])) {
                    $translations = $feature['_etim_value_translations'];
                    $currentTranslation = null;
                    $lang = 'en';
                    foreach ($translations as $translation) {
                        if (!$currentTranslation) {
                            $currentTranslation = $translation->etim_value_description;
                        }

                        if ($translation->language == $lang) {
                            $currentTranslation = $translation->etim_value_description;
                        }

                    }
                    if ($currentTranslation) {

                        return  new SelectValue($currentTranslation);
                    }


                }
            break;
            case 'R':
            case 'N':
                return new NumericalValue($feature['numeric_value']);
                break;
            default:
                print_r($feature);
                return;

        }
        return false;
    }
}

