<?php
namespace Skwirrel\Pim\Model;

use Magento\Catalog\Model\ProductRepository;
use Magento\Eav\Model\ResourceModel\Entity\Attribute;

class ProductBuilder
{
    protected $data = [];
    protected $images = [];

    /**
     * @var \Magento\Catalog\Model\ProductRepository
     */
    private $productRepository;

    /**
     * @var \Magento\Catalog\Model\ResourceModel\Product\Attribute\Collection
     */
    private $attributeCollection;

    /**
     * @var \Magento\Catalog\Model\ProductFactory
     */
    private $productFactory;
    /**
     * @var \Magento\Catalog\Model\Product\Gallery\Processor
     */
    private $galleryProcessor;

    public function __construct(
        ProductRepository $productRepository,
        \Magento\Catalog\Model\ProductFactory $productFactory,
        \Magento\Catalog\Model\Product\Gallery\Processor $galleryProcessor,
        \Magento\Catalog\Model\ResourceModel\Product\Attribute\Collection $attributeCollection

    ) {
        $this->productRepository = $productRepository;
        $this->productFactory = $productFactory;
        $this->galleryProcessor = $galleryProcessor;
        $this->attributeCollection = $attributeCollection;
    }

    public function buildProductData($data)
    {
        $this->data = $data;
        $this->buildBaseProperties();
        return $this->data;
    }

    public function build($data)
    {
        $this->buildProductData($data);
    }

    public function addImage($filePath, $roles = null)
    {

        $this->images[] = ['file' => $filePath, 'roles' => $roles];
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

    public function setData($keyOrArray, $dataValue = null)
    {
        if (is_array($keyOrArray)) {
            foreach ($keyOrArray as $key => $value) {
                $this->data[$key] = $value;
            }
        }
        if (is_string($keyOrArray)) {
            $this->data[$keyOrArray] = $dataValue;
        }
        return $this;
    }

    private function buildBaseProperties()
    {
        $this->buildAttributeSet();
        $this->buildAttributeValues();

    }


    public function buildAttributeSet()
    {
        if (isset($this->data['attribute_set_id'])) {
            return;
        }

        if (isset($this->data['attribute_set'])) {
            $attributeSet = $this->getAttributeSetByName($this->data['attribute_set']);
            if (!$attributeSet) {
                $attributeSet = $this->getAttributeSetByName('Default');
            }
            unset($this->data['attribute_set']);
            $this->data['attribute_set_id'] = $attributeSet->getId();
        }

    }

    private function buildAttributeValues()
    {
        $attributeCollection = $this->attributeCollection->load();
        $attributes = [];
        /**
         * @var $attribute \Magento\Eav\Model\Attribute
         */
        foreach ($attributeCollection as $index => $attribute) {
            if ($attribute->getIsUserDefined()) {
                $attributeCode = $attribute->getAttributeCode();
                if (isset($this->data[$attributeCode])) {
                    if ($attribute->usesSource()) {
                        foreach ($attribute->getOptions() as $option) {
                            if ($option->getLabel() == $this->data[$attributeCode]) {
                                $this->data[$attributeCode] = $option->getValue();

                                break;
                            }
                        }
                    }
                }

            }
        }

    }

}