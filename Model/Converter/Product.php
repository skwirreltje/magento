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
     * @var \Skwirrel\Pim\Model\Converter\Brand
     */
    private $brandConverter;


    public function __construct(
        \Psr\Log\LoggerInterface $logger,
        \Skwirrel\Pim\Console\Progress $progress,

        \Skwirrel\Pim\Api\MappingInterface $mapping,
        \Skwirrel\Pim\Helper\Data $helper,
        \Magento\Framework\Filesystem\Directory\ReadFactory $directoryReadFactory,
        \Skwirrel\Pim\Model\Converter\Brand $brandConverter


    ) {
        parent::__construct($logger, $progress, $mapping, $helper);
        $this->directoryReadFactory = $directoryReadFactory;
        $this->brandConverter = $brandConverter;
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
        $this->progress->barStart('product_convert',count($files));
        foreach($files as $filePath){
            $productData = json_decode(file_get_contents($filePath), true);
            if (!isset($productData['_etim']) || !isset($productData['_etim']['_etim_features'])) {
                $this->progress->barAdvance('product_convert');
                continue;
            }

            $data[] = $this->convertProduct($productData);
            $this->progress->barAdvance('product_convert');

        }
        $this->progress->barFinish('product_convert');

        return $data;

    }

    private function convertProduct($productData)
    {
        $features = $productData['_etim']['_etim_features'];

        $sku = isset($productData['manufacturer_product_code']) ? $productData['manufacturer_product_code'] : 'skwirrel_' . $productData['product_id'];
        $data = [
            'sku' => $sku,
            'name' => $sku,
            'skwirrel_id' => $productData['product_id'],
            'type' => 'simple',
            'website_ids' => [1],
            'attribute_set_id' => 4,
            'status' => \Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED,
            'visibility' => 4,
            'price' => $this->parseProductPrice($productData),
            'stock_data' => [
                'use_config_manage_stock' => 1,
                'manage_stock' => 1,
                'is_in_stock' => 1,
                'qty' => 1
            ],

            'attributes' => [],
            'skwirrel' => $productData
        ];

        foreach ($features as $code => $feature) {
            $attributeCode = strtolower($code);
            $data['attributes'][$attributeCode] = $this->parseProductFeatureValue($feature);
        }

        foreach($this->brandConverter->getConvertedData() as $brand){
            if($productData['brand_id'] == $brand->brand_id){
                $data['attributes'][Brand::DEFAULT_ATTRIBUTE_CODE] = $brand->brand_name;
            }
        }

        return $data;
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

                    foreach($this->mapping->getWebsites() as $website){
                        foreach($website['storeviews'] as $storeview){
                            $locale = $storeview['locale'];
                            if(isset($feature['_etim_value_translations'][$locale]))
                            {
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

    private function parseProductPrice($productData)
    {
        $tradeItems = array_values($productData['_trade_items']);
        foreach ($tradeItems as $tradeItem) {
            foreach ($tradeItem['_trade_item_prices'] as $price) {
                return $price['gross_price'];
            }
        }
        return 0;
    }
}