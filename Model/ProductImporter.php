<?php

namespace Skwirrel\Pim\Model;

use Magento\Catalog\Model\Product\Visibility;
use Magento\Framework\App\ObjectManager;
use Skwirrel\Pim\Model\Extractor\AttributeValues;
use Skwirrel\Pim\Model\Extractor\ProductPrice;

class ProductImporter
{
    protected $productData;

    /**
     * @var \Magento\Eav\Setup\EavSetup
     */
    protected $eavSetup;
    protected $existingAttributes;
    /**
     * @var \Skwirrel\Pim\Helper\Data
     */
    private $helper;
    /**
     * @var \Magento\Catalog\Model\ProductFactory
     */
    private $productFactory;
    /**
     * @var \Magento\Catalog\Model\ProductRepository
     */
    private $productRepository;
    /**
     * @var \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory
     */
    private $productCollectionFactory;
    /**
     * @var \Skwirrel\Pim\Model\Mapping
     */
    private $mapping;
    /**
     * @var \Skwirrel\Pim\Model\Extractor\AttributeValues
     */
    private $attributeValuesExtractor;
    /**
     * @var \Magento\Catalog\Model\Product\Attribute\Repository
     */
    private $attributeRepository;
    /**
     * @var \Magento\Eav\Model\Entity\Attribute\SetFactory
     */
    private $attributeSetFactory;
    /**
     * @var \Skwirrel\Pim\Model\Extractor\ProductPrice
     */
    private $productPriceExtractor;
    /**
     * @var \Magento\Catalog\Model\Product\Gallery\Processor
     */
    private $galleryProcessor;
    /**
     * @var \Magento\Catalog\Model\ResourceModel\Category\CollectionFactory
     */
    private $categoryCollectionFactory;
    /**
     * @var \Skwirrel\Pim\Model\Converter\Product
     */
    private $productConverter;
    /**
     * @var \Magento\ConfigurableProduct\Model\Product\Type\ConfigurableFactory
     */
    private $configurableFactory;
    /**
     * @var \Skwirrel\Pim\Model\ConfigurableBuilderFactory
     */
    private $configurableBuilderFactory;
    /**
     * @var \Magento\Catalog\Model\Product\Gallery\GalleryManagement
     */
    private $galleryManagement;
    /**
     * @var \Magento\Catalog\Api\CategoryLinkManagementInterface
     */
    private $linkManagement;


    /**
     * ProductImporter constructor.
     * @param \Skwirrel\Pim\Helper\Data $helper
     * @param \Magento\Catalog\Model\ProductFactory $productFactory
     * @param \Magento\Catalog\Model\ProductRepository $productRepository
     * @param \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $productCollectionFactory
     * @param \Skwirrel\Pim\Model\Extractor\AttributeValues $attributeValuesExtractor
     * @param \Magento\Catalog\Model\Product\Attribute\Repository $attributeRepository
     * @param \Magento\Eav\Model\Entity\Attribute\SetFactory $attributeSetFactory
     * @param \Skwirrel\Pim\Model\Mapping $mapping
     */
    public function __construct(
        \Skwirrel\Pim\Helper\Data $helper,
        Mapping $mapping,
        AttributeValues $attributeValuesExtractor,
        ProductPrice $productPriceExtractor,
        \Magento\Catalog\Model\ProductFactory $productFactory,
        \Magento\Catalog\Model\ProductRepository $productRepository,
        \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $productCollectionFactory,
        \Magento\Catalog\Model\Product\Attribute\Repository $attributeRepository,
        \Magento\Eav\Model\Entity\Attribute\SetFactory $attributeSetFactory,
        \Magento\Catalog\Model\Product\Gallery\Processor $galleryProcessor,
        \Magento\Eav\Setup\EavSetupFactory $eavSetupFactory,
        \Magento\Setup\Module\DataSetup $dataSetup,
        \Magento\Catalog\Model\ResourceModel\Category\CollectionFactory $categoryCollectionFactory,
        \Skwirrel\Pim\Model\Converter\Product $productConverter,
        \Magento\ConfigurableProduct\Model\Product\Type\ConfigurableFactory $configurableFactory,
        \Skwirrel\Pim\Model\ConfigurableBuilderFactory $configurableBuilderFactory,
        \Magento\Catalog\Model\Product\Gallery\GalleryManagement $galleryManagement,
        \Magento\Catalog\Api\CategoryLinkManagementInterface $linkManagement



    ) {

        $this->helper = $helper;
        $this->productFactory = $productFactory;
        $this->productRepository = $productRepository;
        $this->productCollectionFactory = $productCollectionFactory;
        $this->mapping = $mapping;
        $this->attributeValuesExtractor = $attributeValuesExtractor;
        $this->attributeRepository = $attributeRepository;
        $this->attributeSetFactory = $attributeSetFactory;
        $this->productPriceExtractor = $productPriceExtractor;
        $this->galleryProcessor = $galleryProcessor;
        $this->eavSetup = $eavSetupFactory->create(['setup' => $dataSetup]);


        $mapping->load();
        $this->categoryCollectionFactory = $categoryCollectionFactory;
        $this->productConverter = $productConverter;
        $this->configurableFactory = $configurableFactory;
        $this->configurableBuilderFactory = $configurableBuilderFactory;
        $this->galleryManagement = $galleryManagement;
        $this->linkManagement = $linkManagement;
    }

    public function import($productData)
    {
        $attributes = $this->mapping->getAttributes();
        $attributeMap = [];

        foreach ($attributes as $attribute) {
            $attributeMap[$attribute->getSourceName()] = $attribute->getMagentoName();
        }

        $productData = json_decode(json_encode($productData), true);

        $this->productData = $productData;

        $convertedData = $this->productConverter->convertProduct($productData);

        $features = $this->productConverter->convertProductFeatures($productData);


        $createdProducts = [];

        $isConfigurable = false;
        foreach ($convertedData as $item) {
            if ($item['parent_id'] != 0) {
                $isConfigurable = true;
            }
        }

        $configurableProductId = false;
        if ($isConfigurable) {
            foreach ($convertedData as $item) {
                $skwirrelId = $item['skwirrel_id'];
                $magentoProduct = $this->findMagentoProductBySkwirrelId($skwirrelId);
                if ($magentoProduct) {
                    // get configurable product by child id
                    $configurableProductIds = $this->configurableFactory->create()->getParentIdsByChild($magentoProduct->getId());
                    if (count($configurableProductIds) > 0) {
                        $configurableProductId = $configurableProductIds[0];
                        break;
                    }
                }

            }
        }

        $configurableProductData = [];

        $parentId = false;
        foreach ($convertedData as $item) {


            $skwirrelId = $item['skwirrel_id'];
            print_r(['doing skwirrel Id :' . $skwirrelId . ' - parent :' . $item['parent_id']]);

            $categoryIds = $this->resolveCategoryIds($item['skwirrel']);

            if ($item['parent_id'] != 0) {

                $parentId = $item['parent_id'];


                if (!isset($configurableProductData[$parentId])) {
                    $configurableProductData[$item['parent_id']] = [
                        'data' => $item['skwirrel'],
                        'attributes' => [],
                        'simples' => [],
                        'attribute_set_id' => '',
                        'category_ids' => $categoryIds,
                        'description' => $item['attributes']['description'],
                        'short_description' => $item['attributes']['short_description'],
                    ];

                    $attributeSet = $this->getAttributeSet($item['skwirrel']);
                    $attributeSetId = 4;

                    if ($attributeSet) {
                        $attributeSetId = $attributeSet->getId();
                    }
                    $configurableProductData[$parentId]['attribute_set_id'] = $attributeSetId;


                }
                $configurableProductData[$parentId]['simples'][$skwirrelId] = $item['sku'];

            }

            $magentoProduct = $this->findMagentoProductBySkwirrelId($skwirrelId);

            if (!$magentoProduct) {

                print_r('cannot find product : ' . $skwirrelId);
                $magentoProduct = $this->createProduct($item);
                $createdProducts[] = $createdProducts;
            }

            if ($magentoProduct) {

                $attributeData = [
                    'attachments' => ''
                ];

                $languageSpecificAttributes = [];
                foreach ($item['attributes'] as $key => $attributeValue) {
                    $magentoAttributeName = $key;
                    if (isset($attributeMap[$key])) {
                        $magentoAttributeName = $attributeMap[$key];
                    }


                    $attributeData[$magentoAttributeName] = $this->findProductAttributeValue($magentoAttributeName, $attributeValue);
                    if ($parentId) {
                        $configurableProductData[$parentId]['attributes'][$magentoAttributeName] = $attributeData[$magentoAttributeName];
                    }
                }

                foreach ($attributeData as $attributeCode => $attributeValue) {
                    if (is_array($attributeValue)) {

                        $languageSpecificAttributes[$attributeCode] = $attributeValue;
                    }

                    $magentoProduct->setData($attributeCode, $attributeValue);
                }

                if ($isConfigurable) {
                    $magentoProduct->setVisibility(Visibility::VISIBILITY_NOT_VISIBLE);
                }

                $magentoProduct->setData('category_ids', $categoryIds);

                $magentoProduct->setPrice($item['price']);

                $images = $this->resolveProductImages($item['skwirrel']);
                $magentoProduct->save();

                $this->linkManagement->assignProductToCategories(
                    $magentoProduct->getSku(),
                    $categoryIds
                );


                $this->handleProductImages((int)$magentoProduct->getId(), $images, $isConfigurable,true);


                if (!$parentId) {
                    $this->handleLanguageSpecificAttributes($magentoProduct, $languageSpecificAttributes);

                }
            }
        }

        if ($isConfigurable) {

            foreach ($configurableProductData as $configurableId => $configurable) {

                //print_r(['config product id', $configurableProductId]);

                if (!$configurableProductId) {
                    $configurableProductInstance = [
                        'sku' => $configurable['data']['manufacturer_product_code'],
                        'attribute_set_id' => $configurable['attribute_set_id'],
                        'name' => $configurable['data']['name'],
                        'category_ids' => $configurable['category_ids'],
                        'description' => $configurable['description'],
                        'short_description' => $configurable['short_description'],
                        'stock_data' => [
                            'use_config_manage_stock' => 1,
                            'manage_stock' => 1,
                            'is_in_stock' => 1,
                            'qty' => 100
                        ],
                    ];
                } else {
                    //print_r(['loading product :', $configurableProductId]);
                    $configurableProductInstance = $this->productFactory->create()->load($configurableProductId);
                    $configurableProductInstance->setTypeId('configurable');
                }


                $languageSpecificAttributes = [];

                /**
                 * @var $builder \Skwirrel\Pim\Model\ConfigurableBuilder
                 */
                $builder = $this->configurableBuilderFactory->create();

                $builder->setConfigurableAttributeCodes([$configurable['data']['configurable_attribute_code']]);
                $configurableSkuParts = [];

                foreach ($configurable['simples'] as $simpleId => $simpleSku) {
                    $configurableSkuParts[] = trim($simpleSku);
                    $builder->addSimpleProductBySku($simpleSku);
                }

                $configurableProduct = $builder->build($configurableProductInstance);
                $configurableProduct->setVisibility(Visibility::VISIBILITY_BOTH);

                $images = $this->resolveProductImages($configurable['data']);

                $this->handleProductImages((int)$configurableProduct->getId(), $images);
                foreach ($configurable['attributes'] as $attributeCode => $attributeValue) {
                    if (is_array($attributeValue)) {
                        $languageSpecificAttributes[$attributeCode] = $attributeValue;
                        continue;
                    }

                    $configurableProduct->setData($attributeCode, $attributeValue);
                }

                $configurableProduct->save();

                $this->handleLanguageSpecificAttributes($configurableProduct, $languageSpecificAttributes);

            }

        }


    }

    private function handleLanguageSpecificAttributes($productModel, $languageSpecificAttributes)
    {
        foreach ($this->mapping->getWebsites() as $website) {
            foreach ($website['storeviews'] as $storeview) {
                $locale = $storeview['locale'];
                $product = $this->productRepository->getById($productModel->getId(), true, $storeview['storeviewid']);

                foreach ($languageSpecificAttributes as $attributeCode => $values) {
                    if (isset($values[$locale]) && $values[$locale] != '') {
                        $product->setData($attributeCode, $values[$locale]);
                    }
                }
                $product->save();

            }
        }

    }

    private function getSkuFromTradeItem($tradeItem)
    {
        foreach ([$tradeItem['supplier_trade_item_code'], 'item_' . $tradeItem['trade_item_id']] as $value) {
            if (trim($value) != '') {
                return trim($value);
            }
        }
        return '';
    }


    private function findProductAttributeValue($magentoAttributeName, $attributeValue, $retry = false)
    {


        try{

            $attribute = $this->attributeRepository->get($magentoAttributeName);
        }
        catch(\Exception $e){

            return $attributeValue;
        }

        if ($attribute->getBackendType() != 'int' && $attribute->getFrontendInput() != 'select') {

            if (is_array($attributeValue)) {
                return $attributeValue;
            }
        }

        try {

            if ($attribute->getBackendType() == 'int' && $attribute->getFrontendInput() == 'select') {

                if (is_array($attributeValue)) {
                    $optionValue = $attributeValue[0];
                } else {
                    $optionValue = $attributeValue;
                }

                $options = $attribute->getOptions();
                foreach ($options as $option) {
                    if ($option->getLabel() == $optionValue) {
                        return $option->getValue();
                    }
                }

                if (trim($optionValue) != '' && !$retry) {
                    $newOptions['option']['value']['option_0'][0] = $optionValue;

                    foreach ($this->mapping->getWebsites() as $website) {
                        foreach ($website['storeviews'] as $storeview) {
                            $newOptions['option']['value']['option_0'][$storeview['storeviewid']] = $optionValue;
                        }
                    }

                    $attribute->addData($newOptions);
                    $attribute->save();
                    return $this->findProductAttributeValue($magentoAttributeName, $attributeValue, true);

                }

            } else {
                if (is_array($attributeValue)) {
                    return $attributeValue;
                }

            }

        } catch (\Exception $e) {
        }
        return $attributeValue;
    }

    public function handleProductImages($product, $images, $isConfigurable = false, $existing = false)
    {

        if (is_int($product) || is_string($product)) {
            $product = $this->productRepository->getById($product, true, 0);
        }

        $productGallery = ObjectManager::getInstance()->create('Magento\Catalog\Model\ResourceModel\Product\Gallery');

        $existingGalleryImages = [];
        $added = false;
        $removed = false;

        $model = $this->productRepository->getById($product->getId(), true);
        if ($isConfigurable) {
            $model->setMediaGallery(['images' => [], 'values' => []]);
            $model->setMediaGalleryEntries([]);
            $model->setData('thumbnail', 'no_selection');
            $this->productRepository->save($model);
            return;
        }

        if (!$model->hasGalleryAttribute()) {
            $model->setMediaGallery(['images' => [], 'values' => []]);
        }

        $entries = $model->getMediaGalleryEntries();
        if ($entries && is_array($entries)) {
            foreach ($entries as $i => $entry) {
                $removed = true;
                $productGallery->deleteGallery($entry->getId());
                $this->galleryProcessor->removeImage($model, $entry->getFile());
            }
        }


        foreach ($images as $image) {
            //create copy to import directory
            $imageId = md5(basename($this->helper->createImageImportFile($image, false)));
            if (!isset($existingGalleryImages[$imageId])) {
                $added = true;
                $importFilename = $this->helper->createImageImportFile($image, true);
                $this->galleryProcessor->addImage($model, $importFilename, ['image', 'small_image', 'thumbnail'], true, false);
            }
        }

        if($added || $removed){
            try{

                $model->save();
            }
            catch (\Exception $e){
                print_r('error: '.$e->getMessage()."\n\n".$e->getTraceAsString());
            }
        }
    }

    protected function resolveProductImages($productData)
    {
        $attachments = isset($productData['_attachments']) ? (array)$productData['_attachments'] : [];
        $images = [];
        foreach ($attachments as $attachment) {

            if ($attachment['product_attachment_type_code'] !== Mapping::ATTACHMENT_TYPE_IMAGE) {
                continue;
            }

            $filename = $this->storeAttachmentImage($productData['product_id'], $attachment['source_url']);
            if ($filename) {
                $images[] = $filename;
            }
        }
        return $images;
    }

    private function resolveAttributeData($product, $attributeValues)
    {
        $mappedAttributes = $this->getMappedAttributeNames();
        $attributeData = [];

        foreach ($attributeValues as $featureCode => $attributeValue) {
            $attributeCode = isset($mappedAttributes[$featureCode]) ? $mappedAttributes[$featureCode] : $featureCode;
            $attributeData[$attributeCode] = $this->resolveAttributeValue($attributeCode, $attributeValue);
        }

        return $attributeData;
    }

    public function resolveAttributeValue($attributeCode, $attributeValue, $retry = false)
    {
        $attribute = $this->attributeRepository->get($attributeCode);
        if (!$attribute) {
            return $attributeValue;
        }

        /**
         * Return values as array if not a select type
         */
        if ($attribute->getBackendType() != 'int' && $attribute->getFrontendInput() != 'select') {

            if (is_array($attributeValue)) {
                return $attributeValue;
            }
        }

        try {

            /**
             * Handle select type attribute values
             */

            if ($attribute->getBackendType() == 'int' && $attribute->getFrontendInput() == 'select') {

                if (is_array($attributeValue)) {
                    $optionValue = $attributeValue[0];
                } else {
                    $optionValue = $attributeValue;
                }

                $options = $attribute->getOptions();
                foreach ($options as $option) {
                    if ($option->getLabel() == $optionValue) {
                        return $option->getValue();
                    }
                }

                if (trim($optionValue) != '' && !$retry) {

                    $newOptions = [];
                    $newOptions['option']['value']['option_0'][0] = $optionValue;

                    foreach ($this->mapping->getWebsites() as $website) {
                        foreach ($website['storeviews'] as $storeview) {
                            $newOptions['option']['value']['option_0'][$storeview['storeviewid']] = $optionValue;
                        }
                    }

                    $attribute->addData($newOptions);
                    $attribute->save();
                    return $this->resolveAttributeValue($attributeCode, $attributeValue, true);

                }

            }

        } catch (\Exception $e) {
            $this->logger->error(sprintf('Attribute with code %s could not be found', $attributeCode));
        }
        return $attributeValue;
    }

    private function findMagentoProductBySkwirrelId($skwirrelId)
    {
        $collection = $this->productCollectionFactory->create();
        $collection->addAttributeToFilter(Mapping::SKWIRREL_ID_ATTRIBUTE_CODE, ['eq' => $skwirrelId])
            ->addAttributeToSelect([Mapping::SKWIRREL_ID_ATTRIBUTE_CODE, 'name', 'sku'])
            ->load();
        $product = $collection->fetchItem();
        return $product;

    }

    private function getMappedAttributeNames()
    {
        $map = [];
        foreach ($this->mapping->getAttributes() as $attribute) {
            $map[$attribute->getSourceName()] = $attribute->getMagentoName();
        }
        return $map;
    }

    private function createProduct($item)
    {

        $productData = $item;
        unset($productData['parent_id']);
        unset($productData['attributes']);
        unset($productData['skwirrel']);

        print_r(['create prod:' . $productData['skwirrel_id']]);
        $attributeSet = $this->getAttributeSet($item['skwirrel']);

        $attributeSetId = 4;

        if ($attributeSet) {
            $attributeSetId = $attributeSet->getId();
        }


        $product = $this->productFactory->create();
        $product->setAttributeSetId($attributeSetId);

        $product->setData(Mapping::SKWIRREL_ID_ATTRIBUTE_CODE, $productData['skwirrel_id']);

        $product->setSku($productData['sku']);
        $product->setName($productData['name']);

        foreach ($this->getDefaultProductData() as $key => $value) {
            $product->setData($key, $value);
        }

        if ($item['parent_id'] !== 0) {
            $product->setData('visibility', 1);
        }

        return $this->productRepository->save($product);
    }

    private function getAttributeSet($productData)
    {

        $classCode = $productData['_etim']['etim_class_code'];
        $attributeSetName = $this->resolveAttributeSetName($classCode);
        if (!$attributeSetName) {
            $translations = (array)$productData['_etim']['_etim_class_translations'];

            if (isset($translations[Mapping::SYSTEM_LANGUAGE_CODE])) {
                $attributeSetName = $translations[Mapping::SYSTEM_LANGUAGE_CODE]['etim_class_description'];
            } else {
                $translation = array_shift($translations);
                $attributeSetName = $translation['etim_class_description'];
            }
        }

        if ($attributeSetName) {
            return $this->getAttributeSetByName($attributeSetName);
        }

    }

    protected function getAttributeSetByName($setName)
    {

        /** @var \Magento\Eav\Model\Entity\Attribute\Set $attributeSet */
        $attributeSet = $this->attributeSetFactory->create();

        $entityTypeId = $this->eavSetup->getEntityTypeId(\Magento\Catalog\Model\Product::ENTITY);
        $setCollection = $attributeSet->getResourceCollection()
            ->addFieldToFilter('entity_type_id', $entityTypeId)
            ->addFieldToFilter('attribute_set_name', $setName)
            ->load();
        $attributeSet = $setCollection->fetchItem();
        return $attributeSet;

    }


    private function resolveAttributeSetName($classCode)
    {
        foreach ($this->mapping->getAttributeSets() as $set) {
            if ($set['class_id'] == $classCode) {
                return $set['magento_name'];
            }
        }
    }

    private function resolveProductSku($productData)
    {
        return $productData->manufacturer_product_code;
    }

    private function resolveProductName($productData)
    {
        return $this->resolveProductSku($productData);
    }

    public function deleteProductByExternalId($deleteId)
    {

        $magentoProduct = $this->findMagentoProductBySkwirrelId($deleteId);
        if ($magentoProduct) {
            $this->productRepository->delete($magentoProduct);
        }

    }

    private function createProductName($namePattern, $data)
    {
        $mappedAttributes = $this->getMappedAttributeNames();
        foreach ($mappedAttributes as $source => $alias) {
            if (isset($data[$source])) {
                $data[$alias] = $data[$source];
            }
        }

        $name = $namePattern;
        $pattern = '/\%([a-z\_]+)\%/i';
        if (preg_match_all($pattern, $namePattern, $matches)) {
            foreach ($matches[1] as $key) {
                $value = isset($data[$key]) ? $data[$key] : '';
                $name = str_replace('%' . $key . '%', $value, $name);
            }
        }
        return $name;
    }

    private function storeAttachmentImage($productId, $sourceUrl)
    {
        $attachmentPath = $this->helper->getImportDataDirectory() . '/attachments_' . $productId;
        if (!file_exists($attachmentPath)) {
            mkdir($attachmentPath, 0777, true);
        }
        try {
            $fileName = basename($sourceUrl);
            $fileContent = file_get_contents($sourceUrl);
            file_put_contents($attachmentPath . '/' . $fileName, $fileContent);
            return $attachmentPath . '/' . $fileName;

        } catch (\Exception $e) {

        }
        return false;
    }

    private function resolveCategoryIds($productData)
    {
        $categories = isset($productData['_categories']) ? (array)$productData['_categories'] : [];
        $ids = [];
        foreach ($categories as $category) {
            if ($id = $this->resolveCategoryId($category['product_category_id'])) {
                $ids[] = $id;
            }
        }
        return $ids;
    }

    private function resolveCategoryId($productCategoryId)
    {
        $collection = $this->categoryCollectionFactory->create();
        $collection
            ->addAttributeToSelect([Mapping::SKWIRREL_ID_ATTRIBUTE_CODE, 'name', 'entity_id'])
            ->addAttributeToFilter(Mapping::SKWIRREL_ID_ATTRIBUTE_CODE, ['eq' => $productCategoryId])
            ->load();

        $item = $collection->fetchItem();
        if ($item) {
            return $item->getId();
        }
    }

    private function getDefaultProductData()
    {
        return [
            'status' => \Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED,
            'visibility' => 4,
            'stock_data' => [
                'use_config_manage_stock' => 1,
                'manage_stock' => 1,
                'is_in_stock' => 1,
                'qty' => 1
            ],

        ];
    }


}