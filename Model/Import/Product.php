<?php
namespace Skwirrel\Pim\Model\Import;

use Magento\Catalog\Model\Product\Visibility;
use Skwirrel\Pim\Model\Mapping;

class Product extends AbstractImport
{
    protected $existingCategories = [];

    /**
     * @var \Magento\Catalog\Model\ResourceModel\Product\Collection
     */
    protected $productCollection;

    /**
     * @var \Magento\Eav\Setup\EavSetup
     */
    private $eavSetup;

    /**
     * @var \Magento\Catalog\Model\ResourceModel\Category\Collection
     */
    private $categoryCollection;
    /**
     * @var \Magento\Catalog\Api\CategoryLinkManagementInterface
     */
    private $linkManagement;
    /**
     * @var \Magento\Catalog\Model\ProductRepository
     */
    private $productRepository;
    /**
     * @var \Magento\Catalog\Model\ProductFactory
     */
    private $productFactory;
    /**
     * @var \Magento\Catalog\Model\Product\Attribute\Repository
     */
    private $attributeRepository;
    /**
     * @var \Magento\Eav\Model\Entity\Attribute\SetFactory
     */
    private $attributeSetFactory;
    /**
     * @var \Magento\Catalog\Model\Product\Gallery\Processor
     */
    private $galleryProcessor;
    /**
     * @var \Skwirrel\Pim\Model\ConfigurableBuilderFactory
     */
    private $configurableBuilderFactory;


    public function __construct(
        \Psr\Log\LoggerInterface $logger,
        \Skwirrel\Pim\Console\Progress $progress,

        \Skwirrel\Pim\Api\MappingInterface $mapping,
        \Skwirrel\Pim\Helper\Data $helper,
        \Skwirrel\Pim\Api\ConverterInterface $converter,
        \Magento\Catalog\Model\ResourceModel\Category\CollectionFactory $categoryCollectionFactory,
        \Magento\Catalog\Api\CategoryLinkManagementInterface $linkManagement,
        \Magento\Catalog\Model\ProductFactory $productFactory,
        \Magento\Catalog\Model\ProductRepository $productRepository,
        \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $productCollectionFactory,
        \Magento\Catalog\Model\Product\Attribute\Repository $attributeRepository,
        \Magento\Eav\Setup\EavSetupFactory $eavSetupFactory,
        \Magento\Setup\Module\DataSetup $dataSetup,
        \Magento\Eav\Model\Entity\Attribute\SetFactory $attributeSetFactory,
        \Magento\Catalog\Model\Product\Gallery\Processor $galleryProcessor,
        \Skwirrel\Pim\Model\ConfigurableBuilderFactory $configurableBuilderFactory


    ) {
        parent::__construct($logger, $progress, $mapping, $helper, $converter);

        $this->categoryCollection = $categoryCollectionFactory->create();
        $this->productCollection = $productCollectionFactory->create();

        $this->linkManagement = $linkManagement;
        $this->productRepository = $productRepository;
        $this->productFactory = $productFactory;
        $this->attributeRepository = $attributeRepository;
        $this->eavSetup = $eavSetupFactory->create(['setup' => $dataSetup]);
        $this->attributeSetFactory = $attributeSetFactory;
        $this->galleryProcessor = $galleryProcessor;
        $this->configurableBuilderFactory = $configurableBuilderFactory;
    }


    function import()
    {



        $existingCategories = $this->getExistingCategories();
        $existingProducts = $this->getExistingProducts();
        $attributes = $this->mapping->getAttributes();
        $attributeMap = [];

        foreach ($attributes as $attribute) {
            $attributeMap[$attribute->getSourceName()] = $attribute->getMagentoName();
        }

        $data = $this->getConvertedData();

        $this->progress->info('Starting product import');
        $this->progress->barStart('import_product', count($data));

        $configurableProducts = [];

        foreach ($data as $productData) {

            $parentId = false;
            $skwirrelProductId = $productData['skwirrel_id'];
            $skwirrelData = $productData['skwirrel'];

            if ($productData['parent_id'] > 0) {
                $parentId = $productData['parent_id'];

                if (!isset($configurableProducts[$parentId])) {
                    $configurableProducts[$parentId] = [
                        'data' => $productData['skwirrel'],
                        'simples' => [],
                        'attribute_set_id' => '',
                        'category_ids' => [],
                        'description' => $productData['attributes']['description'],
                        'short_description' => $productData['attributes']['short_description'],

                    ];
                }
                $configurableProducts[$parentId]['simples'][$skwirrelProductId] = $productData['sku'];
            }

            $attributeSetId = $this->findAttributeSetIdName($skwirrelData['_etim']);
            if (!$attributeSetId) {
                $attributeSetId = 4;
            }

            $attributeValues = $productData['attributes'];
            $images = $productData['skwirrel']['attachments'];

            unset($productData['skwirrel']);
            unset($productData['attributes']);
            unset($productData['parent_id']);

            $productCategoryIds = [];

            foreach ($skwirrelData['_categories'] as $category) {
                $skwirrelCategoryId = $category['product_category_id'];
                if (isset($existingCategories[$skwirrelCategoryId])) {
                    $productCategoryIds[] = $existingCategories[$skwirrelCategoryId];
                }
            }

            if ($parentId) {
                $configurableProducts[$parentId]['category_ids'] = $productCategoryIds;
                $configurableProducts[$parentId]['attribute_set_id'] = $attributeSetId;
            }

            $attributeData = [];
            foreach ($attributeValues as $attributeCode => $attributeValue) {

                $magentoAttributeName = $attributeCode;
                if (isset($attributeMap[$attributeCode])) {
                    $magentoAttributeName = $attributeMap[$attributeCode];
                }

                $attributeData[$magentoAttributeName] = $this->findProductAttributeValue($magentoAttributeName, $attributeValue);
            }

            if (isset($existingProducts[$skwirrelProductId])) {



                $magentoProductId = $existingProducts[$skwirrelProductId];
                $product = $this->productFactory->create()->load($magentoProductId);

                foreach ($attributeData as $key => $value) {
                    $product->setData($key, $value);
                }

                $product->setPrice($productData['price']);

                if($parentId){
                    $product->setVisibility( Visibility::VISIBILITY_NOT_VISIBLE );
                    $product->setName($productData['sku']);
                    $this->handleProductImages($product, $images,['thumbnail']);
                }
                else{
                    $product->setName($productData['name']);
                    $this->handleProductImages($product, $images);
                }

                $product->save();

            } else {

                $product = $this->productFactory->create();

                $product->setData($productData);
                foreach ($attributeData as $key => $value) {
                    $product->setData($key, $value);
                }

                $product->setAttributeSetId($attributeSetId);
                $product->setCategoryIds($productCategoryIds);


                $product= $this->productRepository->save($product);

                $product->setPrice($productData['price']);

                if($parentId){
                    $product->setVisibility( Visibility::VISIBILITY_NOT_VISIBLE );
                    $product->setName($productData['sku']);
                    $this->handleProductImages($product, $images,['thumbnail']);
                }
                else{
                    $product->setName($productData['name']);
                    $this->handleProductImages($product, $images);
                }

                $product->save();
            }


            // categories
            if (count($productCategoryIds) == 0) {
            }

            $this->linkManagement->assignProductToCategories(
                $product->getSku(),
                $productCategoryIds
            );
            $this->progress->barAdvance('import_product');
        }

        foreach ($configurableProducts as $configurableId => $configurable) {
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

            $configurableProduct = $builder->build([
                'sku' => implode('-', $configurableSkuParts),
                'attribute_set_id' => $configurable['attribute_set_id'],
                'skwirrel_id' => $this->mapping->getSkwirrelId($configurableId,'product'),
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
            ]);
            $configurableProduct->setVisibility(Visibility::VISIBILITY_BOTH);

            $configurableImages = $configurable['data']['attachments'];

            $this->handleProductImages($configurableProduct, $configurableImages);

            $configurableProduct->save();
        }


        $this->progress->barFinish('import_product');

    }

    protected function handleProductImages($product, $images, $roles = false)
    {
        $existingGalleryImages = [];
        $imagesToKeep = [];

        if($roles && is_array($roles)){
            $imageRoles = $roles;
        }
        elseif(is_string($roles)){
            $imageRoles = [$roles];
        }
        else{
            $imageRoles = ['image', 'small_image', 'thumbnail'];
        }


        if (!$product->hasGalleryAttribute()) {
            $product->setMediaGallery(['images' => [], 'values' => []]);
        }

        foreach ($product->getMediaGalleryEntries() as $entry) {
            $entryName = $this->helper->getNormalizedProductImageFromEntry($entry);
            $imageId = md5($entryName);
            $existingGalleryImages[$imageId] = $entry->getFile();
        }

        foreach ($images as $image) {
            //create copy to import directory
            $imageId = md5(basename($this->helper->createImageImportFile($image, false)));

            if (!isset($existingGalleryImages[$imageId])) {
                $importFilename = $this->helper->createImageImportFile($image, true);
                $this->galleryProcessor->addImage($product, $importFilename, $imageRoles, false, false);
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

    private function getExistingCategories()
    {
        if (empty($this->existingCategories)) {

            $this->categoryCollection
                ->addAttributeToSelect([Mapping::SKWIRREL_ID_ATTRIBUTE_CODE, 'name'])
                ->addAttributeToFilter(Mapping::SKWIRREL_ID_ATTRIBUTE_CODE, ['gt' => 0])
                ->load();

            foreach ($this->categoryCollection as $index => $category) {
                $this->existingCategories[$category->getData(Mapping::SKWIRREL_ID_ATTRIBUTE_CODE)] = $category->getId();
            }
        }

        return $this->existingCategories;
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


    private function getExistingProducts()
    {
        $products = [];
        $this->productCollection->addAttributeToFilter(Mapping::SKWIRREL_ID_ATTRIBUTE_CODE, ['neq' => ''])
            ->addAttributeToSelect([Mapping::SKWIRREL_ID_ATTRIBUTE_CODE, 'name', 'sku'])
            ->load();

        foreach ($this->productCollection as $index => $product) {
            $products[$product->getData(Mapping::SKWIRREL_ID_ATTRIBUTE_CODE)] = $product->getId();
        }
        return $products;
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
            $this->logger->error(sprintf('Attribute with code %s could not be found', $magentoAttributeName));
        }
        return $attributeValue;
    }

    private function findAttributeSetIdName($etim)
    {

        $classCode = $etim['etim_class_code'];
        if ($alias = $this->getMappedAttributeSetByClassCode($classCode)) {
            if (($attributeSet = $this->getAttributeSetByName($alias))) {
                return $attributeSet->getId();
            }

            return;

        }

        $defaultLanguage = $this->mapping->getDefaultLanguage();

        $translations = $etim['_etim_class_translations'];
        if (count($translations)) {
            if (isset($translations[$defaultLanguage])) {
                if (($attributeSet = $this->getAttributeSetByName($translations[$defaultLanguage]))) {
                    return $attributeSet->getId();
                }
            }
        }
        if ($attributeSet = $this->getAttributeSetByName($classCode)) {
            return $attributeSet->getId();
        }

    }

    public function getMappedAttributeSetByClassCode($classCode)
    {
        foreach ($this->mapping->getAttributeSets() as $set) {
            if ($set['class_id'] == $classCode) {
                return $set['magento_name'];
            }
        }
    }

}