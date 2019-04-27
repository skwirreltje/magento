<?php
namespace Skwirrel\Pim\Model\Import;

use Skwirrel\Pim\Model\Converter\Etim\EtimAttribute;
use Skwirrel\Pim\Model\Mapping;

class AttributeSet extends AbstractImport
{

    const FEATURE_TYPE_SELECT = 'A';
    const FEATURE_TYPE_LOGICAL = 'L';
    const FEATURE_TYPE_NUMERIC = 'N';

    protected $existingAttributes = [];
    protected $mappedSets;

    /**
     * @var \Magento\Catalog\Model\ResourceModel\Product\Attribute\Collection
     */
    private $attributeCollection;
    /**
     * @var \Skwirrel\Pim\Model\Import\Attribute\TypeFactory
     */
    private $typeFactory;
    /**
     * @var \Magento\Framework\Filesystem\Directory\ReadFactory
     */
    private $directoryReadFactory;


    protected $parsedProductData = [];
    /**
     * @var \Magento\Eav\Setup\EavSetup
     */
    private $eavSetup;
    /**
     * @var \Magento\Setup\Module\DataSetup
     */
    private $dataSetup;
    /**
     * @var \Magento\Catalog\Model\ResourceModel\Eav\AttributeFactory
     */
    private $attributeFactory;
    /**
     * @var \Magento\Eav\Model\Entity\Attribute\SetFactory
     */
    private $attributeSetFactory;

    public function __construct(
        \Psr\Log\LoggerInterface $logger,
        \Skwirrel\Pim\Console\Progress $progress,
        \Skwirrel\Pim\Api\MappingInterface $mapping,
        \Skwirrel\Pim\Helper\Data $helper,
        \Skwirrel\Pim\Api\ConverterInterface $converter,
        \Skwirrel\Pim\Model\Import\Attribute\TypeFactory $typeFactory,
        \Magento\Catalog\Model\ResourceModel\Product\Attribute\CollectionFactory $attributeCollectionFactory,
        \Magento\Catalog\Model\ResourceModel\Eav\AttributeFactory $attributeFactory,

        \Magento\Framework\Filesystem\Directory\ReadFactory $directoryReadFactory,
        \Magento\Eav\Setup\EavSetupFactory $eavSetupFactory,
        \Magento\Eav\Model\Entity\Attribute\SetFactory $attributeSetFactory,
        \Magento\Catalog\Setup\CategorySetupFactory $categorySetupFactory,
        \Magento\Setup\Module\DataSetup $dataSetup

    )
    {
        parent::__construct($logger, $progress, $mapping, $helper, $converter);
        $this->attributeCollection = $attributeCollectionFactory->create();

        $this->typeFactory = $typeFactory;
        $this->directoryReadFactory = $directoryReadFactory;
        $this->eavSetup = $eavSetupFactory->create(['setup' => $dataSetup]);
        $this->dataSetup = $dataSetup;
        $this->attributeFactory = $attributeFactory;
        $this->attributeSetFactory = $attributeSetFactory;
    }


    function import()
    {

        $entityTypeId = $this->eavSetup->getEntityTypeId(\Magento\Catalog\Model\Product::ENTITY);
        $this->mappedSets = $this->getConvertedData();

        $process = $this->getProcess();

        $createIfNotMapped = isset($process['options']['create_unmapped']) ? (bool) $process['options']['create_unmapped'] : false;


        $defaultSetId = $this->eavSetup->getDefaultAttributeSetId(\Magento\Catalog\Model\Product::ENTITY);

        $mappedAttributes = $this->mapping->getAttributes();
        $parsedProducts = $this->getConvertedProductData();
        $groupName = 'general';

        $attributeSetIndex = [];


        foreach ($parsedProducts as $classId => $parsedProduct) {
            $currentAttributeSetId = $defaultSetId;

            if(!isset($attributeSetIndex[$classId])){
                $attributeSetName = $this->findAttributeSetNameByClassId($classId);
                if(!$attributeSetName){

                    if($createIfNotMapped == false){
                        // try to find fallback
                        if( ($fallbackSetName = $this->findAttributeSetNameByClassId('*'))){
                            $attributeSetName = $fallbackSetName;
                            $this->logger->info('Using fallback '.$fallbackSetName.' for '.$classId);
                        }
                        else{
                            //print_r('fuck this:'.$classId);
                            continue;
                        }
                    }
                    else{
                        $attributeSetName = $this->getAttributeSetNameFromProduct($parsedProduct);
                    }
                }
                if($attributeSetName){
                    $attributeSetIndex[$classId] = $attributeSetName;
                }
            }
            else{
                $attributeSetName = $attributeSetIndex[$classId];
            }

            if(!$attributeSetName){
                continue;
            }



            print_r([$attributeSetName]);
            $attributeSet = $this->processAttributeSet($attributeSetName);
            $currentAttributeSetId = $attributeSet->getId();


            foreach ($parsedProduct['attributes'] as $attributeCode => $attribute) {
                $magentoAttributeCode = $attributeCode;
                foreach ($mappedAttributes as $mappedAttribute) {
                    $sourceName = $mappedAttribute->getSourceName();
                    if ($sourceName == $attributeCode) {
                        $magentoAttributeCode = $mappedAttribute->getMagentoName();
                    }
                }
                print_r([$attributeSetName => $magentoAttributeCode]);

                $this->eavSetup->addAttributeToSet(
                    \Magento\Catalog\Model\Product::ENTITY,
                    $currentAttributeSetId,
                    $groupName,
                    $magentoAttributeCode
                );
            }

        }


    }

    protected function processAttributeSet($setName)
    {
        /** @var \Magento\Eav\Model\Entity\Attribute\Set $attributeSet */
        $attributeSet = $this->attributeSetFactory->create();
        $entityTypeId = $this->eavSetup->getEntityTypeId(\Magento\Catalog\Model\Product::ENTITY);
        $setCollection = $attributeSet->getResourceCollection()
            ->addFieldToFilter('entity_type_id', $entityTypeId)
            ->addFieldToFilter('attribute_set_name', $setName)
            ->load();
        $attributeSet = $setCollection->fetchItem();

        if (!$attributeSet) {
            $attributeSet = $this->attributeSetFactory->create();
            $attributeSet->setEntityTypeId($entityTypeId);
            $attributeSet->setAttributeSetName($setName);
            $attributeSet->save();
            $defaultSetId = $this->eavSetup->getDefaultAttributeSetId(\Magento\Catalog\Model\Product::ENTITY);
            $attributeSet->initFromSkeleton($defaultSetId);
            $attributeSet->save();

        }
        return $attributeSet;
    }

    protected function addAttributeOptions($attributeCode, $options)
    {
        $attribute = $this->attributeFactory->create();
        $attribute->loadByCode(\Magento\Catalog\Model\Product::ENTITY, $attributeCode);
        $attribute->addData($options);
        $attribute->save();
    }

    public function addAttribute($attributeName, $attributeData)
    {
        $this->eavSetup->addAttribute(
            \Magento\Catalog\Model\Product::ENTITY,
            $attributeName,
            $attributeData
        );

    }

    private function getConvertedProductData()
    {
        $importPath = $this->helper->getImportDataDirectory();
        $reader = $this->directoryReadFactory->create($importPath);
        foreach ($reader->read() as $fileName) {
            $filePath = $importPath . DIRECTORY_SEPARATOR . $fileName;
            if (strpos($fileName, 'product_') !== false && substr($filePath, -4) == 'json') {

                $productData = json_decode(file_get_contents($filePath), true);

                if (!isset($productData['_etim']) || !isset($productData['_etim']['_etim_features'])) {
                    continue;
                }

                $this->convertProduct($productData);
            }
        }
        return $this->parsedProductData;

    }

    private function convertProduct($productData)
    {
        $etim = $productData['_etim'];

        $etimCode = $etim['etim_class_code'];
        if (!isset($this->parsedProductData[$etimCode])) {
            $this->parsedProductData[$etimCode] = [
                'code' => $etimCode,
                'translations' => $etim['_etim_class_translations'],
                'attributes' => [],
            ];
        }

        $features = $productData['_etim']['_etim_features'];
        foreach ($features as $code => $feature) {
            $magentoCode = strtolower($code);
            if (!isset($this->parsedProductData[$etimCode]['attributes'][$magentoCode])) {
                $this->parsedProductData[$etimCode]['attributes'][$magentoCode] = $this->parseProductFeature($feature);
            }
        }
    }

    /**
     * @param $feature
     * @return array
     */
    private function parseProductFeature($feature)
    {
        $config = [
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
            'pim_data' => [
                'magento_name' => $feature['etim_feature_code'],
                'feature_code' => strtolower($feature['etim_feature_code']),
                'feature_type' => $feature['etim_feature_type'],
                'value_labels' => [],
                'labels' => [],
                'has_options' => false,
                'value_code' => '',
                'raw_data' => $feature,
                'options' => []
            ]
        ];


        foreach ($feature['_etim_feature_translations'] as $lang => $translation) {
            $config['pim_data']['labels'][$lang] = $translation['etim_feature_description'];
        }

        switch ($feature['etim_feature_type']) {
            case self::FEATURE_TYPE_SELECT:
                $config['input'] = 'select';
                $config['type'] = 'int';
                $config['is_global'] = true;

                $config['pim_data']['value_code'] = $feature['etim_value_code'];
                $config['pim_data']['has_options'] = true;

                if (isset($feature['_etim_value_translations'])) {
                    foreach ($feature['_etim_value_translations'] as $lang => $translation) {
                        $config['pim_data']['value_labels'][$lang] = $translation['etim_value_description'];
                    }
                }

                break;
            case self::FEATURE_TYPE_LOGICAL:
                $config['input'] = 'boolean';
                $config['type'] = 'int';
                break;
            case self::FEATURE_TYPE_NUMERIC:
                $config['input'] = 'text';
                $config['type'] = 'decimal';
                break;
        }

        return $config;
    }

    private function findAttributeSetNameByClassId($classId)
    {

        foreach($this->mappedSets as $mappedSet){
            if($mappedSet['class_id'] == $classId){
                return $mappedSet['magento_name'];
            }
        }
        return false;
    }

    private function getAttributeSetNameFromProduct($parsedProduct)
    {
        $defaultLanguage = $this->mapping->getDefaultLanguage();
        if(isset($parsedProduct['translations'][$defaultLanguage])){
            return $parsedProduct['translations'][$defaultLanguage]['etim_class_description'];
        }
        else{
            $translation = array_values($parsedProduct['translations']);
            if(isset($translation[0])){
                return $translation[0]['etim_class_description'];
            }
        }
        return false;

    }


}