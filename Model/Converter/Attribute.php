<?php
namespace Skwirrel\Pim\Model\Converter;

use Skwirrel\Pim\Api\ConverterInterface;
use Skwirrel\Pim\Model\Mapping;

class Attribute implements ConverterInterface
{

    protected $configPath;
    /**
     * @var Mapping
     */
    protected $mapping;
    /**
     * @var \Skwirrel\Pim\Model\Import\Attribute\TypeFactory
     */
    private $typeFactory;
    /**
     * @var \Psr\Log\LoggerInterface
     */
    private $logger;
    /**
     * @var \Skwirrel\Pim\Helper\Data
     */
    private $helper;

    protected $convertedData = [];
    /**
     * @var \Skwirrel\Pim\Console\Progress
     */
    private $progress;


    public function __construct(
        \Psr\Log\LoggerInterface $logger,
        \Skwirrel\Pim\Console\Progress $progress,
        \Skwirrel\Pim\Helper\Data $helper,
        \Skwirrel\Pim\Model\Import\Attribute\TypeFactory $typeFactory

    )
    {
        $this->typeFactory = $typeFactory;
        $this->logger = $logger;
        $this->helper = $helper;
        $this->progress = $progress;
    }

    /**
     * Initialize the converter
     *
     * @return ConverterInterface
     */
    public function init()
    {
        $this->configPath = Mapping::XML_MAGENTO_MAPPING_ENTITIES;

        return $this;
    }

    /**
     * @param Mapping $mapping
     * @return $this
     */
    public function setMapping(Mapping $mapping)
    {
        $this->mapping = $mapping;
        return $this;
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
        $entities = $this->mapping->getMappingXml()->getNode($this->configPath);

        foreach ($entities->children() as $entity) {
            $entityType = $entity->getName();
            if($entityType !== 'Product'){
                continue;
            }

            // Check if sku field is set
            if ($entity->getAttribute('skuField') != '') {
                $attribute = $this->typeFactory->create('String');
                $attribute->setEntityType($entityType)
                    ->setSkwirrelName($entity->getAttribute('skuField'))
                    ->setMagentoName('sku');

                $this->convertedData[] = $attribute;
            }

            // Attribute list
            foreach ($entity->children() as $attributeNode) {
                if ($attributeObject = $this->convertAttributeNodeToObject($entityType, $attributeNode)) {
                    $this->convertedData[] = $attributeObject;
                }
            }
        }
    }


    /**
     * @param $entityType
     * @param $attributeNode \SimpleXMLElement
     * @return array
     */
    function convertAttributeNodeToObject($entityType, $attributeNode)
    {

        $attributes = $attributeNode->attributes();
        $attributeData = [
            'entity_type' => $entityType,
            'magento_name' => isset($attributes['target_code']) ? (string)$attributes['target_code'] : (string)$attributes['source_code'],
            'source_code' => (string)$attributes['source_code'],
            'data_type' => (string)$attributes['data_type'],
            'is_global' => isset($attributes['is_global']) ? (string)$attributes['is_global'] : 0,
            'used_in_product_listing' => isset($attributes['used_in_listing']) ? (string)$attributes['used_in_listing'] : 0,
            'visible_on_front' => isset($attributes['visible_on_front']) ? (string)$attributes['visible_on_front'] : 0,
            'filterable' => isset($attributes['filterable']) ? (string)$attributes['filterable'] : 0,
            'searchable' => isset($attributes['searchable']) ? (string)$attributes['searchable'] : 0,
        ];

        $attribute = $this->typeFactory->create($attributeData['data_type']);
        $attribute->setEntityType($entityType)
            ->setSourceName( strtolower($attributeData['source_code']))
            ->setMagentoName($attributeData['magento_name']);


        unset(
            $attributeData['source_code'],
            $attributeData['magento_name'],
            $attributeData['data_type'],
            $attributeData['cvl'],
            $attributeData['default_value']
        );

        $attribute->addData($attributeData);
        return $attribute;

    }


    /**
     * Get the data converted in convertData()
     *
     * @return array
     */
    public function getConvertedData()
    {
        if (empty($this->convertedData)) {
            $this->init()->convertData();
        }
        return $this->convertedData;

    }
}