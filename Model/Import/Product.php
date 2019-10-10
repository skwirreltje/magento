<?php

namespace Skwirrel\Pim\Model\Import;

use Magento\Catalog\Model\Product\Visibility;
use Magento\Framework\App\ObjectManager;
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


        $websites = $this->mapping->getWebsites();
        $storeIds = [];
        foreach ($websites as $website) {
            foreach ($website['storeviews'] as $storeview) {
                $storeIds[$storeview['storeviewid']] = ['id' => $storeview['storeviewid'], 'locale' => $storeview['locale']];
            }
        }
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
                        'simpleIds' => [],
                        'attribute_set_id' => '',
                        'category_ids' => [],
                        'description' => $productData['attributes']['description'],
                        'short_description' => $productData['attributes']['short_description'],
                        'attributes' => $productData['attributes'],
                        'attributeData' => []

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

            //print_r($images);

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
            $languageSpecificAttributes = [];
            foreach ($attributeValues as $attributeCode => $attributeValue) {

                $magentoAttributeName = $attributeCode;
                if (isset($attributeMap[$attributeCode])) {
                    $magentoAttributeName = $attributeMap[$attributeCode];
                }

                $attributeData[$magentoAttributeName] = $this->findProductAttributeValue($magentoAttributeName, $attributeValue);
                if ($parentId) {
                    $configurableProducts[$parentId]['attributeData'][$magentoAttributeName] = $attributeData[$magentoAttributeName];
                }
            }

            if (isset($existingProducts[$skwirrelProductId])) {

                $magentoProductId = $existingProducts[$skwirrelProductId];
                $productModel = $this->productRepository->getById($magentoProductId, true);

                $productModel->setPrice($productData['price']);

                if ($parentId) {
                    $productModel->setVisibility(Visibility::VISIBILITY_NOT_VISIBLE);
                    $productModel->setName($productData['sku']);
                    $configurableProducts[$parentId]['simples'][$productModel->getId()] = $productModel->getSku();
                    $configurableProducts[$parentId]['simpleIds'][] = $productModel->getId();

                } else {
                    $productModel->setName($productData['name']);

                    foreach ($attributeData as $key => $value) {
                        if (is_array($value)) {
                            $languageSpecificAttributes[$key] = $value;
                            continue;
                        }
                        $productModel->setData($key, $value);
                    }

                    $this->handleProductImages((int)$productModel->getId(), $images, false, true);
                    //$this->productRepository->save($productModel);
                }

                $this->linkManagement->assignProductToCategories(
                    $productModel->getSku(),
                    $productCategoryIds
                );

                $this->productRepository->save($productModel);

                $this->handleLanguageSpecificAttributes($productModel, $languageSpecificAttributes);


            } else {

                $productModel = $this->productFactory->create();
                $productModel->setData($productData);
                $productModel->setSku($productData['sku']);

                $productModel = $this->productRepository->save($productModel);

                foreach ($attributeData as $key => $value) {
                    if (is_array($value)) {
                        $languageSpecificAttributes[$key] = $value;
                        continue;
                    }

                    if (trim($value) == '') {
                        continue;
                    }
                    $productModel->setData($key, $value);
                }

                $productModel->setAttributeSetId($attributeSetId);
                $productModel->setCategoryIds($productCategoryIds);

                $productModel->setPrice($productData['price']);
                $productModel->setName($productData['name']);

                if ($parentId) {
                    $productModel->setVisibility(Visibility::VISIBILITY_NOT_VISIBLE);
                }


                if (!$parentId) {
                    $this->handleProductImages($productModel, $images);
                } else {
                    $configurableProducts[$parentId]['simpleIds'][] = $productModel->getId();
                }

                $this->linkManagement->assignProductToCategories(
                    $productModel->getSku(),
                    $productCategoryIds
                );

                $this->handleLanguageSpecificAttributes($productModel, $languageSpecificAttributes);
                $productModel->save();

            }

            $this->progress->barAdvance('import_product');
        }

        foreach ($configurableProducts as $configurableId => $configurable) {

            /**
             * @var $builder \Skwirrel\Pim\Model\ConfigurableBuilder
             */
            $builder = $this->configurableBuilderFactory->create();


            $builder->setConfigurableAttributeCodes([$configurable['data']['configurable_attribute_code']]);
            $configurableSkuParts = [];


            foreach ($configurable['simpleIds'] as $simpleId) {
                $builder->addSimpleProductById($simpleId);
            }

            $configurableProduct = $builder->build([
                'sku' => $configurable['data']['manufacturer_product_code'] != '' ? $configurable['data']['manufacturer_product_code'] : 'product_' . $configurableId,
                'attribute_set_id' => $configurable['attribute_set_id'],
                'skwirrel_id' => $this->mapping->getSkwirrelId($configurableId, 'product'),
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

            $languageSpecificAttributes = [];

            foreach ($configurable['attributeData'] as $attributeCode => $attributeValue) {
                if (is_array($attributeValue)) {
                    $languageSpecificAttributes[$attributeCode] = $attributeValue;
                    continue;
                }
                $configurableProduct->setData($attributeCode, $attributeValue);
            }
            $configurableProduct->save();

            $configurableImages = isset($configurable['data']['images']) ? $configurable['data']['images'] : [];


            $this->handleProductImages((int)$configurableProduct->getId(), $configurableImages);
            $this->handleLanguageSpecificAttributes($configurableProduct, $languageSpecificAttributes);
        }


        $this->progress->barFinish('import_product');

    }

    protected function handleProductImages($product, $images, $roles = false, $existing = false)
    {

        if (is_int($product) || is_string($product)) {
            $product = $this->productRepository->getById($product, true, 0);
        }

        $productGallery = ObjectManager::getInstance()->create('Magento\Catalog\Model\ResourceModel\Product\Gallery');

        $existingGalleryImages = [];
        $imagesToAdd = [];

        $imageRoles = ['image', 'small_image', 'thumbnail'];

        if ($roles && is_array($roles)) {
            $imageRoles = $roles;
        } elseif (is_string($roles)) {
            $imageRoles = [$roles];
        }

        if (!$product->hasGalleryAttribute()) {
            $product->setMediaGallery(['images' => [], 'values' => []]);
        }

        $entries = $product->getMediaGalleryEntries();
        $removed = false;
        $added = false;


        if ($entries && is_array($entries)) {
            foreach ($entries as $i => $entry) {
                $productGallery->deleteGallery($entry->getId());
                $this->galleryProcessor->removeImage($product, $entry->getFile());
            }
        }

        foreach ($images as $image) {
            //create copy to import directory
            $imageId = md5(basename($this->helper->createImageImportFile($image, false)));

            if (!isset($existingGalleryImages[$imageId])) {
                $importFilename = $this->helper->createImageImportFile($image, true);
                $imagesToAdd[$imageId] = $importFilename;
            }

        }

        foreach ($imagesToAdd as $imageId => $importFileName) {
            $added = true;
            $this->galleryProcessor->addImage($product, $importFileName, $imageRoles, false, false);

        }

        if ($added || $removed) {
            $product->save();
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
        $attribute = $this->attributeRepository->get($magentoAttributeName);
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

    private function handleLanguageSpecificAttributes($productModel, $languageSpecificAttributes)
    {
        foreach ($this->mapping->getWebsites() as $website) {
            foreach ($website['storeviews'] as $storeview) {
                $locale = $storeview['locale'];
                $product = $this->productRepository->getById($productModel->getId(), true, $storeview['storeviewid']);

                foreach ($languageSpecificAttributes as $attributeCode => $values) {
                    if (isset($values[$locale]) && $values[$locale] != '') {
                        $product->setData($attributeCode, $values[$locale]);
                    } else {
                    }
                }
                $product->save();

            }
        }

    }


}