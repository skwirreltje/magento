<?php
namespace Skwirrel\Pim\Model;

use Magento\Framework\Simplexml\Config;
use Skwirrel\Pim\Helper\Data;
use Skwirrel\Pim\Api\MappingInterface;

class Mapping implements MappingInterface
{
    const XML_MAGENTO_MAPPING_PROCESSES = 'processes';
    const XML_MAGENTO_MAPPING_ENTITIES = 'entities';
    const XML_MAGENTO_MAPPING_WEBSITES = 'websites';
    const XML_MAGENTO_MAPPING_DEFAULTS = 'defaults';
    const XML_MAGENTO_MAPPING_LOCALES = 'locales';
    const XML_MAGENTO_MAPPING_ATTRIBUTE_SETS = 'entities/AttributeSet';

    const SKWIRREL_ID_ATTRIBUTE_CODE = 'skwirrel_id';
    const GROUP_NAME_GENERAL = 'general';
    const SYSTEM_LANGUAGE_CODE = 'en' ;
    const ATTACHMENT_TYPE_IMAGE = 'PPI';
    const ORDER_UNIT_ATTRIBUTE_PREFIX = 'order_unit_';

    /**
     * @var $importMapping Config
     */
    protected $importMapping;
    protected $processes = [];
    protected $attributeSets = [];
    protected $websites;

    /**
     * @var \Magento\Store\Model\WebsiteFactory
     */
    protected $websiteFactory;

    /**
     * @var \Skwirrel\Pim\Helper\Data
     */
    private $helper;
    /**
     * @var \Skwirrel\Pim\Model\Converter\Attribute
     */
    private $attributeConverter;

    public function __construct(
        Data $helper,
        \Skwirrel\Pim\Model\Converter\Attribute $attributeConverter,
        \Magento\Store\Model\WebsiteFactory $websiteFactory
    ) {
        $this->helper = $helper;
        $this->attributeConverter = $attributeConverter;
        $this->websiteFactory = $websiteFactory;
    }

    public function getProcesses()
    {
        if (!empty($this->processes)) {
            return $this->processes;
        }


        $processes = $this->importMapping->getNode(self::XML_MAGENTO_MAPPING_PROCESSES);
        foreach ($processes->children() as $process) {
            $entity = $process->getName();

            // Check if import adapter exists
            if (isset($process->import_adapter)) {
                $adapter = (string)$process->import_adapter;
            } else {
                $adapter = "Skwirrel\\Pim\\Model\\Import\\" . $entity;
            }

            $processChildren = [];
            if (isset($process->children)) {
                $processChildren = explode(',', $process->children);
            }

            // Check if entity has custom sku
            $skuAttribute = '';
            if (isset($process->sku_attribute)) {
                $skuAttribute = (string)$process->sku_attribute;
            }

            // generic options
            $options = [];
            if (isset($process->options)) {
                foreach ($process->options->children() as $optionKey => $optionNode) {
                    $options[$optionKey] = (string)$optionNode;
                }
            }

            $processMethod = 'append';
            $extends = [];
            if (isset($process->extends)) {
                $processExtends = explode(',', $process->extends);
                foreach ($processExtends as $extend) {
                    if (array_key_exists($extend, $processes)) {
                        $extends[] = $extend;
                    } else {
                    }
                }
            }

            // Add process data
            $this->processes[$entity] = [
                'entity' => $entity,
                'adapter' => $adapter,
                'extend' => [
                    'method' => $processMethod,
                    'entities' => $extends,
                ],
                'children' => $processChildren,
                'sku' => $skuAttribute,
                'options' => $options,
            ];
        }

        return $this->processes;
    }

    public function getSkwirrelId($id, $entityType){
        return $entityType.'_'.$id;
    }

    /**
     * @param $entityName
     * @return null|array
     */
    public function getProcess($entityName)
    {
        $processes = $this->getProcesses();
        if (!isset($processes[$entityName])) {
            return;
        }
        return $processes[$entityName];
    }

    public function load()
    {
        $mappingFile = $this->helper->getMappingFilepath();
        $mappingFileXml = new Config($mappingFile);
        $this->importMapping = $mappingFileXml;
    }

    public function getDefaultLanguage($channelId = null)
    {
        return (string)$this->importMapping->getNode(self::XML_MAGENTO_MAPPING_DEFAULTS . '/language');
    }

    public function getDefaultCategoryId()
    {
        return (string)$this->importMapping->getNode(self::XML_MAGENTO_MAPPING_DEFAULTS . '/category_id');
    }

    public function getDefaultWebsite()
    {
        $websites = $this->getWebsites();
        foreach ($websites as $website) {
            if ($website['default'] == 1) {
                return $website;
            }
        }

        return array_pop($websites);
    }

    public function getLanguages(){
        $locales = $this->importMapping->getNode(self::XML_MAGENTO_MAPPING_LOCALES);
        $languages = [
            $this->getDefaultLanguage()
        ];
        foreach($locales->children() as $locale){
            $languageId = (string) $locale->getAttribute('id');
            if(!in_array($languageId, $languages)){
                $languages[] = $languageId;
            }
        }

        return $languages;
    }

    public function getWebsites()
    {

        // If already generated return the designated array
        if (!empty($this->websites)) {
            return $this->websites;
        }


        // Get websites node and make sure it's not empty
        $websites = $this->importMapping->getNode(self::XML_MAGENTO_MAPPING_WEBSITES);
        if (!$websites->hasChildren()) {
            throw new \Exception(__('Websites mapping is required.'));
        }
        // Get locales node and make sure it's not empty
        $locales = $this->importMapping->getNode(self::XML_MAGENTO_MAPPING_LOCALES);
        if (!$locales->hasChildren()) {
            throw new \Exception(__('Locales mapping is required.'));
        }

        // Loop through all the websites and generate mapping array
        foreach ($websites->children() as $website) {
            $websiteCode = $website->getAttribute('id');
            $websiteLocale = (isset($website->locale)) ? (string)$website->locale : $this->getDefaultLanguage();

            $market = $website->getAttribute('market');
            if (!$market) {
                $market=$websiteCode;
            }

            // Load website entity
            $mageWebsite = $this->websiteFactory->create();
            $mageWebsite->load($websiteCode, 'code');
            if ($mageWebsite->hasCode()) {
                $stores = $mageWebsite->getStores();
                $storeviews = [];
                foreach ($locales[0]->children() as $storeview) {
                    $storeCode = $storeview->getAttribute('storeviewcode');
                    $storeLocale = $storeview->getAttribute('id');
                    foreach ($stores as $store) {
                        if ($store->getCode() == $storeCode) {
                            $storeviews[$storeLocale] = [
                                'locale' => $storeLocale,
                                'storeview' => $storeCode,
                                'storeviewid' => $store->getId(),
                            ];
                        }
                    }
                }

                $this->websites[$websiteCode] = [
                    'channel' => (string)$website->channel,
                    'website' => $websiteCode,
                    'market' => $market,
                    'storeviews' => $storeviews,
                    'locale' => $websiteLocale,
                    'root_category' => $mageWebsite->getDefaultGroup()->getRootCategoryId(),
                    'default' => ((isset($website->default)) ? 1 : 0),
                ];
            }
        }

        return $this->websites;
    }

    public function getAttributeSets(){
        // If already generated return the designated array
        if (!empty($this->attributeSets)) {
            return $this->attributeSets;
        }

        // Return empty array if none is mapped
        $attributeSetNodes = $this->importMapping->getNode(self::XML_MAGENTO_MAPPING_ATTRIBUTE_SETS);
        if (!$attributeSetNodes->hasChildren()) {
            return $this->attributeSets;
        }

        // Prepare variables
        $this->attributeSets = [];

        // Convert fieldsets to magento attribute sets
        foreach ($attributeSetNodes->children() as $attributeSetNode) {
            $this->attributeSets[] = $this->convertAttributeSet($attributeSetNode);
        }

        return $this->attributeSets;

    }


    public function getAttributeCodeForTradeItemUnit($unit){
        return strtolower(self::ORDER_UNIT_ATTRIBUTE_PREFIX.$unit);
    }

    public function getAttributes()
    {
        return $this->attributeConverter->setMapping($this)->getConvertedData();
    }

    public function getMappingXml()
    {
        return $this->importMapping;

    }

    private function convertAttributeSet($attributeSetNode)
    {
        $classId = (string) $attributeSetNode->getAttribute('etim_class');
        $set = [
            'class_id' => $classId,
            'magento_name' => (string) $attributeSetNode->getAttribute('magento_name')
        ];

        return $set;
    }
}