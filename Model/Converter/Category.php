<?php
namespace Skwirrel\Pim\Model\Converter;

use Skwirrel\Pim\Api\ConverterInterface;
use Skwirrel\Pim\Model\Converter\Category\ObjectFactory;
use Skwirrel\Pim\Model\Mapping;

class Category extends AbstractConverter
{

    const PROCESS_NAME = 'Category';
    /**
     * @var $mapping Mapping
     */

    protected $attributes = [];
    protected $configPath;
    protected $baseCategoryId = 1;
    protected $existingCategories = [];
    protected $categories = [];
    protected $convertedData;

    /**
     * @var \Skwirrel\Pim\Model\Import\Attribute\TypeFactory
     */
    private $typeFactory;
    /**
     * @var \Skwirrel\Pim\Client\ApiClient
     */
    private $apiClient;

    /**
     * @var \Magento\Framework\Filesystem\Directory\ReadFactory
     */
    private $directoryReadFactory;

    /**
     * @var \Magento\Catalog\Model\ResourceModel\Category\Collection
     */
    protected $collection;
    /**
     * @var \Skwirrel\Pim\Model\Converter\Category\ObjectFactory
     */
    private $objectFactory;


    public function __construct(
        \Psr\Log\LoggerInterface $logger,
        \Skwirrel\Pim\Console\Progress $progress,

        \Skwirrel\Pim\Api\MappingInterface $mapping,
        \Skwirrel\Pim\Helper\Data $helper,
        \Skwirrel\Pim\Client\ApiClient $apiClient,
        \Magento\Framework\Filesystem\Directory\ReadFactory $directoryReadFactory,
        \Magento\Catalog\Model\ResourceModel\Category\CollectionFactory $categoryCollectionFactory,
        ObjectFactory $objectFactory

    )
    {
        parent::__construct($logger, $progress, $mapping, $helper);

        $this->directoryReadFactory = $directoryReadFactory;
        $this->objectFactory = $objectFactory;
        $this->apiClient = $apiClient;
        $this->collection = $categoryCollectionFactory->create();
    }

    /**
     * Initialize the converter
     *
     * @return ConverterInterface
     */
    public function init()
    {
        $this->collection
            ->addAttributeToSelect([Mapping::SKWIRREL_ID_ATTRIBUTE_CODE, 'parent_id', 'path', 'name'])
            ->addAttributeToFilter(Mapping::SKWIRREL_ID_ATTRIBUTE_CODE, ['gt' => 0])
            ->load();

        foreach ($this->collection as $index => $item) {
            $id = $item->getData(Mapping::SKWIRREL_ID_ATTRIBUTE_CODE);
            $this->existingCategories[$id] = $index;
        }

        return $this;
    }


    /**
     * This function converts the Skwirrel data to Magento 2 ready data and i run
     * from the construct by default. Should be an array of entities's data.
     * Entity data structure depends on its corresponding import model.
     *
     * @return void
     */
    public function convertData()
    {

        $this->loadCategories();
        $tree = $this->buildCategoryTree();

        $this->convertedData = $tree;
    }


    function buildCategoryTree()
    {
        $children = [];
        $tree = [];
        foreach ($this->categories as $category) {
            if (isset($category->parentId)) {
                $parentId = $category->getParentId();
                if (!isset($children[$parentId])) {
                    $children[$parentId] = [];
                }
                $children[$parentId][] = $category;

            } else {
                $tree[] = $category;
            }
        }

        foreach ($this->categories as $category) {
            if (isset($children[$category->getId()])) {
                $category->children = $children[$category->getId()];
            }
        }
        return $tree;
    }

    function loadCategories()
    {

        $process = $this->mapping->getProcess(self::PROCESS_NAME);
        $languages = $this->mapping->getLanguages();

        $superCategoryId = isset($process['options']['super_category_id']) ? $process['options']['super_category_id'] : 1;

        $response = $this->apiClient->makeRequest('getCategories', [
            'super_category_id' => $superCategoryId,
            'include_category_translations' => true,
            'include_languages' => array_values($languages)
        ]);


        if (isset($response->categories)) {

            $categoryCount = count((array) $response->categories);
            $this->progress->barStart('category', $categoryCount);
            $categories = $this->parseCategories($response->categories);
            $this->categories = $categories;
        }


    }

    public function parseCategories($items)
    {

        $categories = [];
        foreach ($items as $id => $item) {
            $categories[] = $this->parseCategory($item);
        }
        return $categories;
    }

    public function parseCategory($item)
    {

        $defaultLanguage = $this->mapping->getDefaultLanguage();

        $category = $this->objectFactory->create('Category');
        $category->setId($item->product_category_id);
        if (isset($item->parent_category_id)) {
            $category->setParentId($item->parent_category_id);
        }


        $names = [];
        foreach ($item->_translation as $languageCode => $translation) {
            $names[$languageCode] = $translation->category_name;
        }

        if (count($names) == 0) {
            $category->setName('Category ' . $item->product_category_id);
        } else {
            if (isset($names[$defaultLanguage])) {
                $category->setName($names[$defaultLanguage]);
            } else {
                $category->setName(array_values($names)[0]);
            }

        }

        $category->setTranslations($names);


        return $category;


    }

    /**
     * Get the data converted in convertData()
     *
     * @return array
     */
    public function getConvertedData()
    {
        if (empty($this->convertedData)) {
            $this->init()->convertData();
        }
        return $this->convertedData;

    }
}