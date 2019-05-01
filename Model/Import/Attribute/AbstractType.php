<?php
/**
 * Created by PhpStorm.
 * User: robtheeuwes
 * Date: 17-4-19
 * Time: 22:42
 */

namespace Skwirrel\Pim\Model\Import\Attribute;


use Magento\Framework\DataObject;
use Magento\Framework\Simplexml\Element;
use Skwirrel\Pim\Api\ImportAttributeTypeInterface;
use Skwirrel\Pim\Api\MappingInterface;

class AbstractType extends DataObject implements ImportAttributeTypeInterface
{

    protected $websites;
    protected $defaultLanguage;
    protected $frontend = 'text';
    protected $type  = 'varchar';
    protected $global = \Magento\Catalog\Model\ResourceModel\Eav\Attribute::SCOPE_STORE;

    protected $class;
    protected $backend;
    protected $source;

    protected $labels = [];
    protected $defaultValue = "";
    protected $isConfigurable = false;

    protected $entityType = '';
    protected $magentoName = '';

    protected $sourceName;


    public function __construct(
        MappingInterface $mapping
    ) {
        $this->websites = $mapping->getWebsites();
        $this->defaultLanguage = $mapping->getDefaultLanguage();
        $this->addData([
            'input' => $this->frontend,
            'type' => $this->type,
            'global' => $this->global,
            'class' => $this->class,
            'backend' => $this->backend,
            'source' => $this->source,
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
        ]);
    }

    public function getEntityType()
    {
        return $this->entityType;
    }

    public function setEntityType($entityType)
    {
        $this->entityType = $entityType;
        return $this;
    }

    public function getMagentoName()
    {
        return $this->magentoName;
    }

    public function setMagentoName($magentoName)
    {
        $this->magentoName = $magentoName;
        return $this;
    }

    public function getSourceName()
    {
        return $this->sourceName;
    }

    public function setSourceName($name)
    {
        $this->sourceName = $name;
        return $this;
    }


    public function prepareNew()
    {
        return $this->getData();
    }

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
    public function parse(Element $data)
    {
        // TODO: Implement parse() method.
    }

    /**
     * Parse Skwirrel data into Magento data of attribute type, return a single
     * value of given locale code
     *
     * @param mixed $data
     * @param string|null $locale
     * @return mixed
     */
    public function parseValue($data, $locale = null)
    {
        // TODO: Implement parseValue() method.
    }


    public function isConfigurable()
    {
        return $this->isConfigurable;
    }

}