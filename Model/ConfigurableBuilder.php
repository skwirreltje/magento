<?php

namespace Skwirrel\Pim\Model;


use InvalidArgumentException;
use Magento\Catalog\Model\Product;
use Skwirrel\Pim\Api\ImportInterface;
use Magento\Framework\ObjectManagerInterface;

class ConfigurableBuilder

{
    /**
     * @var \Magento\Catalog\Model\ProductFactory
     */
    private $productFactory;

    /**
     * @var \Magento\Eav\Model\Entity\Attribute
     */
    private $entityAttribute;
    /**
     * @var \Magento\Catalog\Model\ProductRepository
     */
    private $productRepository;

    protected $configurableAttributeCodes = [];
    protected $configurableAttributeIds = [];

    protected $simpleProducts = [];

    protected $optionCopyImages = true;

    public function __construct(
        \Magento\Catalog\Model\ProductRepository $productRepository,
        \Magento\Catalog\Model\ProductFactory $productFactory,
        \Magento\Eav\Model\Entity\Attribute $entityAttribute
    ) {
        $this->productRepository = $productRepository;
        $this->productFactory = $productFactory;
        $this->entityAttribute = $entityAttribute;
    }

    public function addConfigurableAttributeCode($code)
    {
        $this->configurableAttributeCodes[] = $code;
        return $this;
    }

    public function setConfigurableAttributeCodes($codes)
    {
        $this->configurableAttributeCodes = $codes;
        return $this;
    }

    public function addSimpleProduct($simpleProduct)
    {
        $this->simpleProducts[] = $simpleProduct;
        return $this;
    }

    public function addSimpleProductById($id)
    {
        $simpleProduct = $this->productRepository->getById($id);
        return $this->addSimpleProduct($simpleProduct);
    }

    public function addSimpleProductBySku($sku)
    {
        $simpleProduct = $this->productRepository->get($sku, true);
        return $this->addSimpleProduct($simpleProduct);
    }

    public function build($configurableProduct)
    {

        if (is_array($configurableProduct)) {

                $configurableProduct = $this->findOrCreateConfigurableProduct($configurableProduct);
        }

        $attributeData = $this->buildAttributeData();
        $configurableProduct->getTypeInstance()->setUsedProductAttributeIds($this->configurableAttributeIds, $configurableProduct);

        $configurableAttributesData = $configurableProduct->getTypeInstance()->getConfigurableAttributesAsArray($configurableProduct);
        $configurableProduct->setCanSaveConfigurableAttributes(true);
        $configurableProduct->setConfigurableAttributesData($configurableAttributesData);


        $configurableProductsData = [];
        foreach ($this->simpleProducts as $simpleProduct) {

            $configurableProductsData[$simpleProduct->getId()] = [];
            foreach ($attributeData as $attributeCode => $attributeConfig) {
                $label = isset($attributeConfig['options'][$simpleProduct->getData($attributeCode)]) ? $attributeConfig['options'][$simpleProduct->getData($attributeCode)] : '';
                $configurableProductsData[$simpleProduct->getId()][] = [
                    'label' => $label,
                    'attribute_id' => $attributeConfig['id'],
                    'value_index' => $simpleProduct->getData($attributeCode),
                    'is_percent' => 0,
                    'pricing_value' => $simpleProduct->getData('price')
                ];
            };
        }


        $configurableProduct->setConfigurableProductsData($configurableProductsData);
        $configurableProduct = $this->productRepository->save($configurableProduct);
        $configurableProduct->setAssociatedProductIds(array_keys($configurableProductsData)); // Assign simple product id
        $configurableProduct->setCanSaveConfigurableAttributes(true);
        return $this->productRepository->save($configurableProduct);

    }

    private function buildAttributeData()
    {
        $attributeData = [];
        $this->configurableAttributeIds = [];
        foreach ($this->configurableAttributeCodes as $attributeCode) {
            $attribute = $this->entityAttribute->loadByCode('catalog_product', $attributeCode);
            $this->configurableAttributeIds[] = $attribute->getId();

            $optionsData = $attribute->getSource()->getAllOptions();
            $attributeData[$attributeCode] = ['id' => $attribute->getId(), 'options' => []];
            foreach ($optionsData as $optionData) {
                if (empty($optionData['value'])) {
                    continue;
                }

                $attributeData[$attributeCode]['options'][$optionData['value']] = $optionData['label'];
            }

        }
        return $attributeData;

    }

    private function findOrCreateConfigurableProduct($configurableProduct)
    {
        if(isset($configurableProduct['skwirrel_id']) && !empty($configurableProduct['skwirrel_id'])){
            if($existingProduct = $this->getExistingProductBySkwirrelId($configurableProduct['skwirrel_id'])){
                return $existingProduct;
            }
        }
        if($existingProduct = $this->getExistingProductBySku($configurableProduct['sku'])){
            print_r('found product by sku:'.$configurableProduct['sku']);
            return $existingProduct;
        }

        $data = [
            'sku' => '',
            'name' => '',
            'website_ids' => [1],
            'attribute_set_id' => 4,
            'status' => \Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED,
            'visibility' => 4,
            'price' => 0,
            'stock_data' => [
                'use_config_manage_stock' => 1,
                'manage_stock' => 1,
                'is_in_stock' => 1,
                'qty' => 100
            ],
        ];
        $data = array_replace($data, $configurableProduct);

        $data['type_id'] = 'configurable';
        $product = $this->productFactory->create();
        $product->setData($data);
        $newProduct = $this->productRepository->save($product);
        return $this->productRepository->getById($newProduct->getId());

    }


    private function getExistingProductBySkwirrelId($skwirrelId){
        $product = $this->productFactory->create();
        if($existing = $product->loadByAttribute('skwirrel_id', $skwirrelId)){
            return $product->load($existing->getId());
        }
        return false;
    }

    private function getExistingProductBySku($sku)
    {
        $product = $this->productFactory->create();
        $productId = $product->getIdBySku($sku);
        if($productId){
            return $product->load($productId);
        }
        return false;
    }


}