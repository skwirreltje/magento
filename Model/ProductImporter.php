<?php
namespace Skwirrel\Pim\Model;

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
        \Magento\Catalog\Model\ResourceModel\Category\CollectionFactory $categoryCollectionFactory

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
    }

    public function import($productData)
    {

        $this->productData = $productData;

        $skwirrelId = $productData->product_id;
        $magentoProduct = $this->findMagentoProductBySkwirrelId($skwirrelId);
        $attributeValues = $this->attributeValuesExtractor->extract($productData);

        $images = $this->resolveProductImages($productData);

        if ($magentoProduct) {
        }

        if (!$magentoProduct) {
            $magentoProduct = $this->createProduct($productData);

        }

        $attributeData = $this->resolveAttributeData($magentoProduct, $attributeValues);

        foreach ($attributeData as $key => $value) {
            $magentoProduct->setData($key, $value);
        }

        $magentoProduct->setPrice($this->productPriceExtractor->extract($productData));

        $this->handleProductImages($magentoProduct, $images);

        $magentoProduct->setCategoryIds($this->resolveCategoryIds($productData));

        $magentoProduct->save();

    }

    protected function handleProductImages($product, $images)
    {
        $existingGalleryImages = [];
        $imagesToKeep = [];


        if (!$product->hasGalleryAttribute()) {
            $product->setMediaGallery(['images' => [], 'values' => []]);
        }

        $entries = $product->getMediaGalleryEntries() ;
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
                $this->galleryProcessor->addImage($product, $importFilename, ['image', 'small_image', 'thumbnail'], true, false);
                $imagesToKeep[$imageId] = $imageId;

            } else {
                $imagesToKeep[$imageId] = $imageId;
            }
        }

        foreach ($existingGalleryImages as $imageId => $existingGalleryImage) {
            if (!isset($imagesToKeep[$imageId])) {
                $this->galleryProcessor->removeImage($product, $existingGalleryImage);
            }
        }

    }

    protected function resolveProductImages($productData)
    {
        $attachments = isset($productData->_attachments) ? (array)$productData->_attachments : [];
        $images = [];
        foreach ($attachments as $attachment) {

            if ($attachment->product_attachment_type_code !== Mapping::ATTACHMENT_TYPE_IMAGE) {
                continue;
            }

            $filename = $this->storeAttachmentImage($productData->product_id, $attachment->source_url);
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

    private function createProduct($productData)
    {
        $attributeSet = $this->getAttributeSet($productData);
        $attributeSetId = 4;

        if ($attributeSet) {
            $attributeSetId = $attributeSet->getId();
        }


        $product = $this->productFactory->create();
        $product->setAttributeSetId($attributeSetId);
        $product->setData(Mapping::SKWIRREL_ID_ATTRIBUTE_CODE, $productData->product_id);

        $product->setSku($this->resolveProductSku($productData));
        $product->setName($this->resolveProductName($productData));

        foreach($this->getDefaultProductData() as $key => $value){
            $product->setData($key, $value);
        }

        return $this->productRepository->save($product);
    }

    private function getAttributeSet($productData)
    {
        $classCode = $productData->_etim->etim_class_code;
        $attributeSetName = $this->resolveAttributeSetName($classCode);
        if (!$attributeSetName) {
            $translations = (array)$productData->_etim->_etim_class_translations;

            if (isset($translations[Mapping::SYSTEM_LANGUAGE_CODE])) {
                $attributeSetName = $translations[Mapping::SYSTEM_LANGUAGE_CODE]->etim_class_description;
            } else {
                $translation = array_shift($translations);
                $attributeSetName = $translation->etim_class_description;
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
        $categories = isset($productData->_categories) ? (array) $productData->_categories : [];
        $ids = [];
        foreach($categories as $category){
            if($id = $this->resolveCategoryId($category->product_category_id)){
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