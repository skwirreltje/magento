<?php
namespace Skwirrel\Pim\Model;

use Magento\Catalog\Model\Product\Visibility;
use Skwirrel\Pim\Model\Extractor\AttributeValues;
use Skwirrel\Pim\Model\Extractor\ProductPrice;

class ProductImporter
{
    protected $productData;

    /**
     * @var \Magento\Eav\Setup\EavSetup
     */
    protected $eavSetup;
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
        \Skwirrel\Pim\Model\ConfigurableBuilderFactory $configurableBuilderFactory


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

        $createdProducts = [];

        $isConfigurable = false;
        foreach($convertedData as $item){
            if($item['parent_id'] != 0){
                $isConfigurable = true;
            }
        }

        $configurableProductId = false;
        if($isConfigurable){
            foreach($convertedData as $item){
                $skwirrelId = $item['skwirrel_id'];
                $magentoProduct = $this->findMagentoProductBySkwirrelId($skwirrelId);
                if($magentoProduct)
                {
                    // get configurable product by child id
                    $configurableProductIds = $this->configurableFactory->create()->getParentIdsByChild($magentoProduct->getId());
                    if(count($configurableProductIds) > 0){
                        $configurableProductId = $configurableProductIds[0];
                        break;
                    }
                }

            }
        }

        $configurableProductData = [];

        $parentId = false;
        foreach($convertedData as $item){

            $skwirrelId = $item['skwirrel_id'];

            if($item['parent_id'] != 0){

                $parentId = $item['parent_id'];

                if(!isset($configurableProductData[$parentId]))
                {
                    $configurableProductData[$item['parent_id']] = [
                        'data' => $item['skwirrel'],
                        'simples' => [],
                        'attribute_set_id' => '',
                        'category_ids' => $this->resolveCategoryIds($item['skwirrel']),
                        'description' => $item['attributes']['description'],
                        'short_description' => $item['attributes']['short_description'],
                    ];

                    $attributeSet = $this->getAttributeSet($productData);
                    $attributeSetId = 4;

                    if ($attributeSet) {
                        $attributeSetId = $attributeSet->getId();
                    }
                    $configurableProductData[$parentId]['attribute_set_id'] = $attributeSetId;


                }
                $configurableProductData[$parentId]['simples'][$skwirrelId] = $item['sku'];

            }

            $magentoProduct = $this->findMagentoProductBySkwirrelId($skwirrelId);

            if(!$magentoProduct){
                $magentoProduct = $this->createProduct($item);
                $createdProducts[] = $createdProducts;
            }

            if($magentoProduct){

                $attributeData = [];
                foreach($item['attributes'] as $key => $attributeValue){
                    $magentoAttributeName = $key;
                    if (isset($attributeMap[$key])) {
                        $magentoAttributeName = $attributeMap[$key];
                    }

                    $attributeData[$magentoAttributeName] = $this->findProductAttributeValue($magentoAttributeName, $attributeValue);

                }
                foreach($attributeData as $attributeCode => $attributeValue){
                    $magentoProduct->setData($attributeCode, $attributeValue);
                }

                if(!$isConfigurable){
                    $images = $this->resolveProductImages($item['skwirrel']);
                    $this->handleProductImages($magentoProduct, $images);

                }

                $magentoProduct->save();
            }

        }

        if($isConfigurable){

            foreach ($configurableProductData as $configurableId => $configurable) {

                if (!$configurableProductId) {
                    $configurableProductInstance = [
                        'sku' => 'skwirrel_product_'.$configurable['data']['product_id'],
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
                    $configurableProductInstance = $this->productFactory->create()->load($configurableProductId);
                    $configurableProductInstance->setTypeId('configurable');
                }


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

                $this->handleProductImages($configurableProduct, $images);

                $configurableProduct->save();
            }

        }


    }

    private function findProductAttributeValue($magentoAttributeName, $attributeValue, $retry = false)
    {
        try {
            $attribute = $this->attributeRepository->get($magentoAttributeName);
            if (!$attribute) {
                return $attributeValue;
            }

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
                    $newOptions = [
                        'option' => [
                            'value' => [
                                'option_0' => $attributeValue

                            ]
                        ]
                    ];

                    $attribute->addData($newOptions);
                    $attribute->save();
                    return $this->findProductAttributeValue($magentoAttributeName, $attributeValue, true);

                }

            }

        } catch (\Exception $e) {
        }
        return $attributeValue;
    }

    protected function handleProductImages($product, $images)
    {
        $existingGalleryImages = [];
        $imagesToKeep = [];

        $productModel = $this->productRepository->getById($product->getId());

        if (!$productModel->hasGalleryAttribute()) {
            $productModel->setMediaGallery(['images' => [], 'values' => []]);
        }

        $entries = $productModel->getMediaGalleryImages() ;
        if($entries){
            foreach ($entries as $entry) {
                $imageId = md5(basename($entry->getFile()));
                $existingGalleryImages[$imageId] = $entry->getFile();
            }
        }


        foreach ($images as $image) {
            //create copy to import directory
            $imageId = md5(basename($this->helper->createImageImportFile($image, false)));
            if (!isset($existingGalleryImages[$imageId])) {
                $importFilename = $this->helper->createImageImportFile($image, true);
                $this->galleryProcessor->addImage($productModel, $importFilename, ['image', 'small_image', 'thumbnail'], true, false);
                $imagesToKeep[$imageId] = $imageId;

            } else {
                $imagesToKeep[$imageId] = $imageId;
            }
        }

        foreach ($existingGalleryImages as $imageId => $existingGalleryImage) {
            if (!isset($imagesToKeep[$imageId])) {
                $this->galleryProcessor->removeImage($productModel, $existingGalleryImage);
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

    public function resolveAttributeValue($attributeCode, $attributeValue)
    {
        $attribute = $this->attributeRepository->get($attributeCode);
        if (!$attribute) {
            return $attributeValue;
        }

        if ($attribute->getBackendType() == 'int' && $attribute->getFrontendInput() == 'select') {

            $options = $attribute->getOptions();
            foreach ($options as $option) {
                if ($option->getLabel() == $attributeValue) {
                    return $option->getValue();
                }
            }

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
        unset($productData['skwirrel']);
        unset($productData['attributes']);

        $attributeSet = $this->getAttributeSet($productData);
        $attributeSetId = 4;

        if ($attributeSet) {
            $attributeSetId = $attributeSet->getId();
        }


        $product = $this->productFactory->create();
        $product->setAttributeSetId($attributeSetId);

        $product->setData(Mapping::SKWIRREL_ID_ATTRIBUTE_CODE, $productData['skwirrel_id']);

        $product->setSku($productData['sku']);
        $product->setName($productData['name']);

        foreach($this->getDefaultProductData() as $key => $value){
            $product->setData($key, $value);
        }

        if($item['parent_id'] !== 0){
            $product->setData('visibility',1);
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
        if($magentoProduct){
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
        try{
            $fileName = basename($sourceUrl);
            $fileContent = file_get_contents($sourceUrl);
            file_put_contents($attachmentPath.'/'.$fileName, $fileContent);
            return $attachmentPath.'/'.$fileName;

        }
        catch(\Exception $e){

        }
        return false;
    }

    private function resolveCategoryIds($productData)
    {
        $categories = isset($productData['_categories']) ? (array) $productData['_categories'] : [];
        $ids = [];
        foreach($categories as $category){
            if($id = $this->resolveCategoryId($category['product_category_id'])){
                $ids[] = $id;
            }
        }
        return $ids;
    }

    private function resolveCategoryId($productCategoryId)
    {
        $collection = $this->categoryCollectionFactory->create();
        $collection
            ->addAttributeToSelect([Mapping::SKWIRREL_ID_ATTRIBUTE_CODE, 'name','entity_id'])
            ->addAttributeToFilter(Mapping::SKWIRREL_ID_ATTRIBUTE_CODE, ['eq' => $productCategoryId])
            ->load();

        $item = $collection->fetchItem();
        if($item){
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