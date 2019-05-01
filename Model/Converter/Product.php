<?php
namespace Skwirrel\Pim\Model\Converter;

use Skwirrel\Pim\Api\ConverterInterface;
use Skwirrel\Pim\Model\Import\Attribute;
use Skwirrel\Pim\Model\Import\Brand;
use Skwirrel\Pim\Model\Mapping;

class Product extends AbstractConverter
{

    protected $attributes = [];
    protected $configPath;
    protected $products = [];
    /**
     * @var \Skwirrel\Pim\Model\Import\Attribute\TypeFactory
     */
    private $typeFactory;

    /**
     * @var \Magento\Framework\Filesystem\Directory\ReadFactory
     */
    private $directoryReadFactory;

    /**
     * @var \Skwirrel\Pim\Model\Import\Brand
     */
    private $brandImporter;
    /**
     * @var \Skwirrel\Pim\Model\Import\Manufacturer
     */
    private $manufacturerImporter;


    public function __construct(
        \Psr\Log\LoggerInterface $logger,
        \Skwirrel\Pim\Console\Progress $progress,

        \Skwirrel\Pim\Api\MappingInterface $mapping,
        \Skwirrel\Pim\Helper\Data $helper,
        \Magento\Framework\Filesystem\Directory\ReadFactory $directoryReadFactory,
        \Skwirrel\Pim\Model\Import\Brand $brandImporter,
        \Skwirrel\Pim\Model\Import\Manufacturer $manufacturerImporter

    ) {
        parent::__construct($logger, $progress, $mapping, $helper);
        $this->directoryReadFactory = $directoryReadFactory;
        $this->brandImporter = $brandImporter;
        $this->manufacturerImporter = $manufacturerImporter;
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

    public function convertData()
    {
        $this->products = $this->getConvertedProductData();

    }

    private function getConvertedProductData()
    {
        $data = [];
        $importPath = $this->helper->getImportDataDirectory();
        $reader = $this->directoryReadFactory->create($importPath);

        $files = [];
        foreach ($reader->read() as $fileName) {
            $filePath = $importPath . DIRECTORY_SEPARATOR . $fileName;
            if (strpos($fileName, 'product_') !== false && substr($filePath, -4) == 'json') {
                $files[] = $filePath;

            }
        }
        $this->progress->info('Starting product conversion');
        $this->progress->barStart('product_convert', count($files));
        foreach ($files as $filePath) {
            $productData = json_decode(file_get_contents($filePath), true);
            if (!isset($productData['_etim']) || !isset($productData['_etim']['_etim_features'])) {
                $this->progress->barAdvance('product_convert');
                continue;
            }
            foreach ($this->convertProduct($productData) as $item) {
                $data[] = $item;
            }

            $this->progress->barAdvance('product_convert');

        }
        $this->progress->barFinish('product_convert');

        return $data;

    }

    public function convertProduct($productData)
    {
        $features = $productData['_etim']['_etim_features'];

        $products = [];
        $tradeItems = $productData['_trade_items'];
        if (count($tradeItems) == 0) {
            return $products;
        }

        $isPartOfConfigurable = count($tradeItems) > 1 ? true : false;

        foreach ($tradeItems as $tradeItem) {

            $sku = $this->getSkuFromTradeItem($tradeItem);
            $name = $this->getProductName($productData, $tradeItem);
            $data = [
                'sku' => $sku,
                'name' => $name,
                'skwirrel_id' => $this->mapping->getSkwirrelId($tradeItem['trade_item_id'], 'item'),
                'type' => 'simple',
                'website_ids' => [1],
                'attribute_set_id' => 4,
                'status' => \Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED,
                'visibility' => $isPartOfConfigurable ? 1 : 4,
                'price' => $this->parseTradeItemPrice($tradeItem),
                'stock_data' => [
                    'use_config_manage_stock' => 1,
                    'manage_stock' => 1,
                    'is_in_stock' => 1,
                    'qty' => 1
                ],

                'attributes' => [],
                'skwirrel' => $productData,
                'parent_id' => $isPartOfConfigurable ? $productData['product_id'] : 0
            ];

            foreach ($features as $code => $feature) {
                $attributeCode = strtolower($code);
                $data['attributes'][$attributeCode] = $this->parseProductFeatureValue($feature);
            }

            foreach ($this->brandImporter->getConvertedData() as $brand) {
                if ($productData['brand_id'] == $brand->brand_id) {
                    $data['attributes'][$this->brandImporter->getAttributeCode()] = $brand->brand_name;
                }
            }

            foreach ($this->manufacturerImporter->getConvertedData() as $item) {
                if ($productData['manufacturer_id'] == $item->manufacturer_id) {
                    $data['attributes'][$this->manufacturerImporter->getAttributeCode()] = $item->manufacturer_name;
                }
            }

            $tradeItemAttributeCode = $this->mapping->getAttributeCodeForTradeItemUnit($tradeItem['use_unit_uom']);
            $data['attributes'][$tradeItemAttributeCode] = $tradeItem['quantity_of_use_units'] . ' ' . $tradeItem['use_unit_uom'];

            $data['attributes']['short_description'] = $this->getProductTranslation($productData,'product_description');
            $data['attributes']['description'] = $this->getProductTranslation($productData,'product_long_description');
            $data['attributes']['meta_description'] = $this->getProductTranslation($productData,'product_marketing_text');

            $data['skwirrel']['configurable_attribute_code'] = $tradeItemAttributeCode;
            $data['skwirrel']['name'] = $name;
            $products[] = $data;

        }

        return $products;
    }


    /**
     * @param $feature
     * @return array
     */
    private function parseProductFeatureValue($feature)
    {

        switch ($feature['etim_feature_type']) {
            case Attribute::FEATURE_TYPE_SELECT:
                if (isset($feature['_etim_value_translations'])) {
                    $trans = array_values($feature['_etim_value_translations'])[0];
                    $values = [
                        0 => $trans['etim_value_description']
                    ];

                    foreach ($this->mapping->getWebsites() as $website) {
                        foreach ($website['storeviews'] as $storeview) {
                            $locale = $storeview['locale'];
                            if (isset($feature['_etim_value_translations'][$locale])) {
                                $values[$storeview['storeviewid']] = $feature['_etim_value_translations'][$locale]['etim_value_description'];
                            }
                        }
                    }
                    return $values;
                }
                break;
            case Attribute::FEATURE_TYPE_LOGICAL:
                return $feature['logical_value'];
                break;
            case Attribute::FEATURE_TYPE_NUMERIC:
                return $feature['numeric_value'];
                break;
        }

    }

    /**
     * Get the data converted in convertData()
     *
     * @return array
     */
    public function getConvertedData()
    {
        if (empty($this->products)) {
            $this->init()->convertData();
        }
        return $this->products;

    }

    private function parseTradeItemPrice($tradeItem)
    {
        $units = (int)$tradeItem['quantity_of_use_units'];

        foreach ($tradeItem['_trade_item_prices'] as $price) {
            $priceBase = (int)$price['price_base'];
            if ($priceBase == 0) {
                $priceBase = 1;
            }
            return ($units / $priceBase) * $price['gross_price'];
        }
        return 0;
    }

    private function getSkuFromTradeItem($tradeItem)
    {
        foreach ([$tradeItem['supplier_trade_item_code'], $tradeItem['supplier_trade_item_code'], 'product_' . $tradeItem['trade_item_id']] as $value) {
            if (trim($value) != '') {
                return trim($value);
            }
        }
        return '';
    }


    private function getProductTranslation($productData, $key)
    {
        $translations = array_values($productData['_product_translations']);
        $languages = $this->mapping->getLanguages();
        foreach($translations as $translation){
            if(in_array($translation['language'], $languages)){
                return $translation[$key];
            }
        }
        return '';

    }

    private function getProductName($productData, $tradeItem)
    {
        $languageCode = $this->mapping->getDefaultLanguage();
        $tradeItemTranslations = isset($tradeItem['_trade_item_translations']) ? $tradeItem['_trade_item_translations'] : [];
        if(count($tradeItemTranslations)){
            $translation = isset($tradeItemTranslations[$languageCode]) ? $tradeItemTranslations[$languageCode] : array_shift($tradeItemTranslations);
            return $translation['trade_item_description'];
        }

        return $this->getProductTranslation($productData,'product_description');

    }
}