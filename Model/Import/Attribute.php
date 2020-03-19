<?php

namespace Skwirrel\Pim\Model\Import;

use Skwirrel\Pim\Model\Converter\Etim\EtimAttribute;
use Skwirrel\Pim\Model\Mapping;
use Symfony\Component\Console\Helper\ProgressBar;

class Attribute extends AbstractImport
{
    const FEATURE_TYPE_SELECT = 'A';
    const FEATURE_TYPE_LOGICAL = 'L';
    const FEATURE_TYPE_NUMERIC = 'N';

    protected $existingAttributes = [];


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
    protected $attributeCodeIndex = [];
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
        \Magento\Setup\Module\DataSetup $dataSetup

    ) {
        parent::__construct($logger, $progress, $mapping, $helper, $converter);

        $this->attributeCollection = $attributeCollectionFactory->create();

        $this->typeFactory = $typeFactory;
        $this->directoryReadFactory = $directoryReadFactory;
        $this->eavSetup = $eavSetupFactory->create(['setup' => $dataSetup]);
        $this->dataSetup = $dataSetup;
        $this->attributeFactory = $attributeFactory;


    }

    function import()
    {
        $existingAttributes = $this->getExistingAttributes();

        $websites = $this->mapping->getWebsites();
        $mappedAttributes = $this->getConvertedData();

        $parsedAttributes = $this->getConvertedProductData();
        $this->checkAttachmentAttribute();

        $mapped = [];
        foreach ($mappedAttributes as $mappedAttribute) {

            $sourceName = $mappedAttribute->getSourceName();
            if (isset($parsedAttributes[$sourceName])) {
                $parsedAttributes[$sourceName] = array_replace($parsedAttributes[$sourceName], $mappedAttribute->prepareNew());
                $parsedAttributes[$sourceName]['pim_data']['magento_name'] = $mappedAttribute->getMagentoName();
                $mapped[$sourceName] = $sourceName;
            }
        }


        $process = $this->getProcess();
        $createIfNotMapped = isset($process['options']['create_unmapped']) ? (bool)$process['options']['create_unmapped'] : true;

        $attributeCollection = $this->attributeCollection->load();

        foreach ($parsedAttributes as $featureCode => $attribute) {

            if ($createIfNotMapped == false && !isset($mapped[$featureCode])) {
                continue;
            }
            $attributeCode = $attribute['pim_data']['magento_name'];

            $labelTranslations = [];
            if (count($attribute['pim_data']['labels']) > 0) {

                foreach ($attribute['pim_data']['labels'] as $locale => $value) {

                    if ($locale == $this->mapping->getDefaultLanguage() || !isset($labelTranslations[0])) {
                        $labelTranslations = array_replace($labelTranslations, [0 => trim($value)]);
                    }

                    foreach ($websites as $website) {
                        foreach ($website['storeviews'] as $storeview) {
                            if ($storeview['locale'] == $locale) {
                                $labelTranslations[$storeview['storeviewid']] = trim($value);
                            }
                        }
                    }

                }
            } else {
                $labelTranslations = [0 => $attributeCode];
            }

            if (!isset($existingAttributes[$attributeCode])) {

                $data = $attribute;
                unset($data['pim_data']);

                $data['label'] = $labelTranslations[0];

                if ($data['filterable'] == '1' && $data['input'] == 'select') {
                    $data['filterable'] = 2;
                }

                if ($data['is_unique'] == 1) {
                    $data['unique'] = 1;
                }

                $this->addAttribute($attributeCode, $data);

                if ($attribute['pim_data']['has_options']) {
                    $options = $this->formatOptions($attribute);

                    $this->addAttributeOptions($attributeCode, $options);
                }

            } else {

                $magentoAttribute = $attributeCollection->getItemById($existingAttributes[$attributeCode]['id']);

                if ($magentoAttribute) {
                    $magentoAttribute->addData([
                        'frontend_label' => $labelTranslations
                    ]);

                    if ($attribute['pim_data']['has_options']) {
                        $optionsData = $magentoAttribute->setStoreId(0)->getSource()->getAllOptions(false);
                        $existingOptions = [];
                        foreach ($optionsData as $option) {
                            $existingOptions[$option['value']] = $option['label'];
                        }

                        $addOptions = [];

                        $options = $this->formatOptions($attribute);
                        foreach ($options['option']['value'] as $key => $values) {
                            if (!in_array($values[0], $existingOptions)) {
                                $addOptions[$key] = $values;
                            } else {
                                $idKey = array_search($values[0], $existingOptions);
                                $addOptions[$idKey] = $values;
                            }
                        }
                        if (count($addOptions)) {
                            $addOptions = ['option' => ['value' => $addOptions]];
                            $this->addAttributeOptions($attributeCode, $addOptions);
                        }
                    }

                    $magentoAttribute->save();
                }

            }

        }

        $this->checkAttachmentAttribute();


    }

    function formatOptions($attribute)
    {
        $options = [
            'option' => [
                'value' => []
            ]
        ];
        $websites = $this->mapping->getWebsites();

        foreach ($attribute['pim_data']['options'] as $key => $option) {
            $optionId = 'option_' . $key;

            $defaultLanguage = $this->mapping->getDefaultLanguage();
            if (isset($option[$defaultLanguage])) {
                $options['option']['value'][$optionId][0] = $option[$defaultLanguage];
            }

            foreach ($option as $locale => $value) {

                foreach ($websites as $website) {
                    foreach ($website['storeviews'] as $storeview) {
                        if ($storeview['locale'] == $locale) {
                            if(!isset($options['option']['value'][$optionId][0])){
                                $options['option']['value'][$optionId][0] = trim($value);
                            }
                            $options['option']['value'][$optionId][$storeview['storeviewid']] = trim($value);
                        }
                    }
                }
            }
        }
        return $options;

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

    public function getConvertedData()
    {
        return $this->mapping->getAttributes();
    }


    private function getExistingAttributes()
    {
        if (empty($this->existingAttributes)) {
            $attributeCollection = $this->attributeCollection->load();
            $this->existingAttributes = [];
            foreach ($attributeCollection as $index => $attribute) {
                $this->existingAttributes[$attribute->getAttributeCode()] = ['id' => $index, 'checksum' => $attribute->getChecksum()];
            }

        }

        return $this->existingAttributes;
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

        $features = $this->convertProductFeatures($productData);

        $classId = $productData['_etim']['etim_class_code'];
        foreach ($features as $code => $feature) {

            $attributeCode = strtolower($code);

            if (!isset($this->parsedProductData[$attributeCode])) {
                $this->parsedProductData[$attributeCode] = $this->parseProductFeature($feature);
            } else {
                $attribute = $this->parsedProductData[$attributeCode];
                $featureType = $attribute['pim_data']['feature_type'];

                if ($featureType == self::FEATURE_TYPE_SELECT) {
                    $parsed = $this->parseProductFeature($feature);

                    $valueCode = $parsed['pim_data']['value_code'];
                    if (trim($valueCode) != '') {
                        $this->parsedProductData[$attributeCode]['pim_data']['options'][$valueCode] = $parsed['pim_data']['value_labels'];
                    }
                }
            }
        }

        // parse trade items into configurable attributes
        $this->convertProductTradeItems($productData);

    }

    function convertProductFeatures($productData)
    {
        $features = $productData['_etim']['_etim_features'];
        if (isset($productData['_custom_class'])) {
            $customFeatures = isset($productData['_custom_class']['_custom_features']) ? $productData['_custom_class']['_custom_features'] : [];

            foreach ($customFeatures as $customFeature) {
                $featureCode = 'custom_' . $customFeature['custom_feature_id'];

                $featureTranslations = [];
                foreach ($customFeature['_custom_feature_translations'] as $lang => $translation) {
                    $featureTranslations[$lang] = [
                        'language' => $lang,
                        'etim_feature_description' => $translation['custom_feature_description']
                    ];
                }
                $valueTranslations = [];
                if (isset($customFeature['_custom_value_translations'])) {
                    foreach ($customFeature['_custom_value_translations'] as $lang => $translation) {
                        $valueTranslations[$lang] = [
                            'language' => $lang,
                            'etim_value_description' => $translation['custom_value_description']
                        ];
                    }

                }

                $features[$featureCode] = [
                    'etim_feature_code' => $featureCode,
                    'etim_feature_type' => $customFeature['custom_feature_type'],
                    'etim_value_code' => $customFeature['custom_value_id'],
                    'numeric_value' => $customFeature['numeric_value'],
                    'logical_value' => $customFeature['logical_value'],
                    '_etim_feature_translations' => $featureTranslations,
                    '_etim_value_translations' => $valueTranslations,
                ];


            }

        }
        return $features;
    }


    private function convertProductTradeItems($productData)
    {
        $tradeItems = $productData['_trade_items'];
        foreach ($tradeItems as $tradeItem) {
            $attributeCode = $this->mapping->getAttributeCodeForTradeItemUnit($tradeItem['use_unit_uom']);

            if (!isset($this->parsedProductData[$attributeCode])) {
                $this->parsedProductData[$attributeCode] = [
                    'input' => 'select',
                    'type' => 'int',
                    'label' => 'Order unit',
                    'is_global' => 1,
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
                        'feature_code' => $attributeCode,
                        'magento_name' => $attributeCode,
                        'has_options' => true,

                        'value_labels' => [],
                        'options' => [],
                        'labels' => [0 => 'Order unit']
                    ]
                ];
            }

            $valueCode = strtolower($tradeItem['quantity_of_use_units'] . '_' . $tradeItem['use_unit_uom']);
            $values = [];
            foreach ($this->mapping->getLanguages() as $language) {
                $values[$language] = $tradeItem['quantity_of_use_units'] . ' ' . $tradeItem['use_unit_uom'];
            }
            $this->parsedProductData[$attributeCode]['pim_data']['options'][$valueCode] = $values;
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
            'visible_on_front' => true,
            'used_in_product_listing' => false,
            'is_unique' => false,
            'pim_data' => [
                'magento_name' => strtolower($feature['etim_feature_code']),
                'feature_code' => $feature['etim_feature_code'],
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
                $valueCode = $feature['etim_value_code'];
                if (trim($valueCode) !== '') {
                    $config['pim_data']['options'][$valueCode] = $config['pim_data']['value_labels'];
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

    private function checkAttachmentAttribute()
    {
        $attr = $this->eavSetup->getAttribute(\Magento\Catalog\Model\Product::ENTITY, 'attachments');
        if ($attr) {
            return;
        }

        $data = [
            'input' => 'textarea',
            'type' => 'text',
            'is_global' => 1,
            'label' => 'Attachments',
            'class' => '',
            'backend' => '',
            'source' => '',
            'visible' => false,
            'required' => false,
            'searchable' => false,
            'filterable' => false,
            'comparable' => false,
            'user_defined' => true,
            'is_user_defined' => true,
            'visible_on_front' => false,
            'used_in_product_listing' => true,
            'is_unique' => false,
            'group' => 'General',
            'frontend' => ''
        ];
        $this->eavSetup->addAttribute(
            \Magento\Catalog\Model\Product::ENTITY,
            'attachments',
            $data
        );


    }


}