<?php
namespace Skwirrel\Pim\Model\Converter\Etim;

use Skwirrel\Pim\Api\ConverterInterface;

class EtimAttribute implements ConverterInterface

{

    /**
     * Initialize the converter
     *
     * @return ConverterInterface
     */
    public function init()
    {
        // TODO: Implement init() method.
    }

    /**
     * This function converts the Skwirrel data to Magento 2 ready data and i run
     * from the construct by default. Should be an array of entities's data.
     * Entity data structure depends on its corresponding import model.
     *
     * @return void
     */
    public function convertData()
    {
        // TODO: Implement convertData() method.
    }

    /**
     * Get the data converted in convertData()
     *
     * @return array
     */
    public function getConvertedData()
    {
        // TODO: Implement getConvertedData() method.
    }

    public function convert($product)
    {

        if (!isset($product->_etim)) {
            die();
            return [];
        }

        $etimAttributes = $product->_etim->_etim_features;

        $attributes = [];
        foreach($etimAttributes as $code => $attribute){
            $attributes[$code] = $this->convertAttribute($attribute);
        }

        return $attributes;


    }

    protected function convertAttribute($attribute)
    {
        $config = [
            'input' => 'text',
            'type' => 'varchar',
            'global' => 0,
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
        ];


        $labels = [];
        foreach($attribute->_etim_feature_translations as $lang => $translation){
            $labels[$lang] = $translation->etim_feature_description;
        }

        $valueLabels = [];
        $value = '';

        switch($attribute->etim_feature_type){
            case 'A':
                $config['input'] = 'select';
                $config['type'] = 'int';
                $value = $attribute->etim_value_code;


                if(isset($attribute->_etim_value_translations)){
                    foreach($attribute->_etim_value_translations as $lang => $translation){
                        $valueLabels[$lang] = $translation->etim_value_description;
                    }
                }

                break;
            case 'L':
                $config['input'] = 'boolean';
                $config['type'] = 'int';
                $value = $attribute->logical_value;
                break;
            case 'N':
                $config['input'] = 'text';
                $config['type'] = 'decimal';
                $value = $attribute->numeric_value;
                break;
        }

        $data = [
            'config' => $config,
            'value_code' => $attribute->etim_value_code,
            'labels'  => $labels,
            'value_labels' =>$valueLabels,
            'value' => $value,
            'value_type'=>$attribute->etim_feature_type
        ];

        return $data;


    }
}