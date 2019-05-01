<?php
namespace Skwirrel\Pim\Api;

use Magento\Framework\Simplexml\Element;

/**
 * Interface for attribute type adapters
 *
 * @api
 */
interface ImportAttributeTypeInterface
{
    /**
     * Handle Skwirrel data and parse it into Magento data of attribute type,
     * return can be a single value or array of storeview specific data in
     * following format:
     *
     * array[storeviewid] Array with storeview id as key and store data as value
     *
     * @param \Magento\Framework\Simplexml\Element $data
     * @return string|array
     */
    public function parse(Element $data);

    /**
     * Parse Skwirrel data into Magento data of attribute type, return a single
     * value of given locale code
     *
     * @param mixed $data
     * @param string|null $locale
     * @return mixed
     */
    public function parseValue($data, $locale = null);

    /**
     * Prepare Magento attribute configurations for attribute type, return array
     * of configurations.
     *
     * @return array
     */
    public function prepareNew();
}
