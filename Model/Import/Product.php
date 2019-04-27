<?php
namespace Skwirrel\Pim\Model\Import;

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
        \Magento\Catalog\Model\Product\Gallery\Processor $galleryProcessor

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

        foreach ($data as $productData) {

            $skwirrelProductId = $productData['skwirrel_id'];
            $skwirrelData = $productData['skwirrel'];

            $attributeSetId = $this->findAttributeSetIdName($skwirrelData['_etim']);
            if (!$attributeSetId) {
                $attributeSetId = 4;
            }

            $attributeValues = $productData['attributes'];
            $images = $productData['skwirrel']['attachments'];

            unset($productData['skwirrel']);
            unset($productData['attributes']);
            $productCategoryIds = [];

            foreach ($skwirrelData['_categories'] as $category) {

                $skwirrelCategoryId = $category['product_category_id'];
                if (isset($existingCategories[$skwirrelCategoryId])) {
                    $productCategoryIds[] = $existingCategories[$skwirrelCategoryId];
                }
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
                $product = $this->productRepository->getById($magentoProductId);

                foreach ($attributeData as $key => $value) {
                    $product->setData($key, $value);
                }

                $this->handleProductImages($product, $images);

                $product->setAttributeSetId($attributeSetId);
                $product->setPrice($productData['price']);

                $product->save();

            } else {

                $product = $this->productFactory->create();

                $product->setData($productData);
                foreach ($attributeData as $key => $value) {
                    $product->setData($key, $value);
                }

                $product->setAttributeSetId($attributeSetId);
                $product->setCategoryIds($productCategoryIds);
                $newProduct = $this->productRepository->save($product);

                $this->handleProductImages($newProduct, $images);

                $newProduct->setPrice($productData['price']);
                $newProduct->save();
            }


            // categories
            if (count($productCategoryIds) == 0) {
                $productCategoryIds = [$this->mapping->getDefaultCategoryId()];
            }

            $this->linkManagement->assignProductToCategories(
                $product->getSku(),
                $productCategoryIds
            );
            $this->progress->barAdvance('import_product');

        }
        $this->progress->barFinish('import_product');

    }

    protected function handleProductImages($product, $images)
    {
        $existingGalleryImages = [];
        $imagesToKeep = [];


        if (!$product->hasGalleryAttribute()) {
            $product->setMediaGallery(['images' => [], 'values' => []]);
        }

        foreach ($product->getMediaGalleryEntries() as $entry) {
            $imageId = md5(basename($entry->getFile()));
            $existingGalleryImages[$imageId] = $entry->getFile();
        }

        foreach ($images as $image) {
            //create copy to import directory
            $imageId = md5(basename($this->helper->createImageImportFile($image, false)));
            if (!isset($existingGalleryImages[$imageId])) {
                $importFilename = $this->helper->createImageImportFile($image, true);
                $this->galleryProcessor->addImage($product, $importFilename, ['image', 'small_image', 'thumbnail'], false, false);
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
        $this->productCollection->addAttributeToFilter(Mapping::SKWIRREL_ID_ATTRIBUTE_CODE, ['gt' => 0])
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

                if(is_array($attributeValue)){
                    $optionValue = $attributeValue[0];
                }
                else{
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