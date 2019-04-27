<?php
namespace Skwirrel\Pim\Api;

interface ConverterInterface
{
    /**
     * Initialize the converter
     *
     * @return ConverterInterface
     */
    public function init();

    /**
     * This function converts the inRiver data to Magento 2 ready data and i run
     * from the construct by default. Should be an array of entities's data.
     * Entity data structure depends on its corresponding import model.
     *
     * @return void
     */
    public function convertData();

    /**
     * Get the data converted in convertData()
     *
     * @return array
     */
    public function getConvertedData();

}