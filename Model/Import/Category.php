<?php
namespace Skwirrel\Pim\Model\Import;

use Skwirrel\Pim\Model\Mapping;

class Category extends AbstractImport
{
    protected $existingCategories = [];


    protected $rootCategoryId = 2;
    /**
     * @var \Magento\Catalog\Model\ResourceModel\Product\Attribute\Collection
     */
    private $categoryCollection;
    /**
     * @var \Magento\Catalog\Model\CategoryFactory
     */
    private $categoryFactory;


    public function __construct(
        \Psr\Log\LoggerInterface $logger,
        \Skwirrel\Pim\Console\Progress $progress,
        \Skwirrel\Pim\Api\MappingInterface $mapping,
        \Skwirrel\Pim\Helper\Data $helper,
        \Skwirrel\Pim\Api\ConverterInterface $converter,
        \Magento\Catalog\Model\ResourceModel\Category\CollectionFactory $categoryCollectionFactory,
        \Magento\Catalog\Model\CategoryFactory $categoryFactory

    )
    {
        parent::__construct($logger,$progress, $mapping, $helper, $converter);
        $this->categoryCollection = $categoryCollectionFactory->create();
        $this->categoryFactory = $categoryFactory;

    }


    function import()
    {

        $this->getExistingCategories();
        $data = $this->getConvertedData();
        foreach($data as $rootCategory){
            $rootCategoryId = $this->rootCategoryId;
            $this->walkTree($rootCategory, '1/' . $rootCategoryId, $rootCategoryId);
        }
        $websites = $this->mapping->getWebsites();



    }

    protected function walkTree($categoryObject, $path, $categoryParentId){

        $parentId = $this->importCategory($categoryObject, $path, $categoryParentId);

        $path = $path . '/'. $parentId;
        foreach($categoryObject->children as $child){
            $this->walkTree($child, $path, $parentId);
        }
    }

    /**
     * @param $object
     * @param $path
     * @param $parentId
     */
    protected function importCategory($object, $path ,$parentId){

        $categoryId = $object->getId();

        $categoryExists = array_key_exists($categoryId, $this->existingCategories);

        $categoryData = array_replace([
            'is_active' => $object->isActive(),
            'name' => $object->getName(),
        ], $object->getAdditionalData());

        if (!$categoryExists) {

            $category = $this->categoryFactory->create();
            $categoryData += [
                'parent_id' => $parentId,
                'url_key' => $category->formatUrlKey($object->getUrlKey()),
                Mapping::SKWIRREL_ID_ATTRIBUTE_CODE => $object->getId(),
            ];
            $category
                ->setData($categoryData)
                ->setPath($path)
                ->setAttributeSetId($category->getDefaultAttributeSetId());




            $category->save();
            $parentId = $category->getId();

        }
        else{
            $magentoId = $this->existingCategories[$object->getId()];
            $category = $this->categoryCollection->getItemById($magentoId);

            $parentId = $category->getId();

            $category->setName($object->getName());
            $category->save();
        }
        $this->progress->barAdvance('category');

        return $parentId;

    }

    private function getExistingCategories()
    {
        if (empty($this->existingCategories)) {

            $this->categoryCollection
                ->addAttributeToSelect([Mapping::SKWIRREL_ID_ATTRIBUTE_CODE, 'name','entity_id'])
                ->addAttributeToFilter(Mapping::SKWIRREL_ID_ATTRIBUTE_CODE, ['gt' => 0])
                ->load();

            foreach ($this->categoryCollection as $index => $attribute) {
                $this->existingCategories[$attribute->getData(Mapping::SKWIRREL_ID_ATTRIBUTE_CODE)] = $index;
            }
        }

        return $this->existingCategories;

    }

}