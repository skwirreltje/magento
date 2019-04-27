<?php


namespace Skwirrel\Pim\Console\Command;

use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Skwirrel\Pim\Import\Data\Model\Product;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CreateConfigProduct extends Command
{

    const NAME_ARGUMENT = "name";
    const NAME_OPTION = "option";
    /**
     * @var \Magento\Catalog\Model\ProductFactory
     */
    private $productFactory;
    /**
     * @var \Magento\Framework\App\State
     */
    private $state;
    /**
     * @var \Magento\Eav\Model\Entity\Attribute
     */
    private $entityAttribute;
    /**
     * @var \Magento\Catalog\Model\ProductRepository
     */
    private $productRepository;
    /**
     * @var \Magento\Catalog\Model\ResourceModel\Eav\Attribute
     */
    private $attributeFactory;

    protected $attributeInfo = [];
    /**
     * @var \Skwirrel\Pim\Model\Mapping\Config\Reader
     */
    private $configReader;

    public function __construct(
        \Magento\Catalog\Model\ProductFactory $productFactory,
        \Magento\Eav\Model\Entity\Attribute $entityAttribute,
        \Magento\Catalog\Model\ProductRepository $productRepository,
        \Magento\Catalog\Model\ResourceModel\Eav\Attribute $attributeFactory,
        \Magento\Eav\Model\ResourceModel\Entity\Attribute\Option\Collection $attributeOptionCollection,

        \Magento\Framework\App\State $state
    ) {
        $this->productFactory = $productFactory;

        parent::__construct();
        $this->state = $state;
        $this->entityAttribute = $entityAttribute;
        $this->productRepository = $productRepository;
        $this->attributeFactory = $attributeFactory;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(
        InputInterface $input,
        OutputInterface $output
    ) {

        $this->state->setAreaCode(\Magento\Framework\App\Area::AREA_ADMINHTML);


        $attributeCode= 'EF001618';
        $attribute = $this->entityAttribute->loadByCode('catalog_product', $attributeCode);
        $optionsData = $attribute->getSource()->getAllOptions();

        print_r($optionsData);
        die();


        $data =
        $prod = new Product();
        $prod->addData(['attrs' => ['poep' => 'bruin']]);
        print_r($prod->getData());
        die();


        $data = [
            'type' => 'configurable',
            'sku' => 'myprod',
            'configurable_attributes' => ['color'],
            'variants' => [
                [
                    'sku' => 'myprod-red9',
                    'name' => 'my prod red',
                    'attributes' => [
                        'color' => 'red'
                    ],
                    'price' => 10
                ],
                [
                    'sku' => 'myprod-green',
                    'name' => 'my prod green',
                    'attributes' => [
                        'color' => 'green'
                    ],
                    'price' => 16
                ]
            ]
        ];


        $simpleProducts = [];
        $attributeData = [];
        foreach ($data['configurable_attributes'] as $attributeCode) {
            $attribute = $this->entityAttribute->loadByCode('catalog_product', $attributeCode);

            $optionsData = $attribute->getSource()->getAllOptions();
            $attributeData[$attributeCode] = ['id' => $attribute->getId(), 'options' => []];
            foreach ($optionsData as $optionData) {
                if (empty($optionData['value'])) {
                    continue;
                }

                $attributeData[$attributeCode]['options'][$optionData['value']] = $optionData['label'];
            }

        }


        $attributeIds = array_map(function ($item) {
            return $item['id'];
        }, $attributeData);

        foreach ($data['variants'] as $variant) {
            $simpleProduct = $this->createSimpleProduct($variant, $attributeData);
            $simpleProducts[] = $simpleProduct;
        }

        $configurableProduct = $this->createConfigurableProduduct($data);

        $configurableProduct->getTypeInstance()->setUsedProductAttributeIds($attributeIds, $configurableProduct);

        $configurableAttributesData = $configurableProduct->getTypeInstance()->getConfigurableAttributesAsArray($configurableProduct);
        $configurableProduct->setCanSaveConfigurableAttributes(true);
        $configurableProduct->setConfigurableAttributesData($configurableAttributesData);


        $configurableProductsData = array();
        foreach ($simpleProducts as $simpleProduct) {

            $configurableProductsData[$simpleProduct->getId()] = []; // id of a simple product associated with the configurable
            foreach ($attributeData as $attrCode => $attrData) {
                $configurableProductsData[$simpleProduct->getId()][] = [
                    'label' => $attrData['options'][$simpleProduct->getData($attrCode)],
                    'attribute_id' => $attrData['id'],
                    'value_index' => $simpleProduct->getData($attrCode),
                    'is_percent' => 0,
                    'pricing_value' => $simpleProduct->getData('price')
                ];
            };
        }


        $configurableProduct->setConfigurableProductsData($configurableProductsData);
        $configurableProduct = $this->productRepository->save($configurableProduct);
        $configurableProduct->setAssociatedProductIds(array_keys($configurableProductsData)); // Assign simple product id
        $configurableProduct->setCanSaveConfigurableAttributes(true);
        $configurableProduct = $this->productRepository->save($configurableProduct);





        return;


        $product = [
            'type' => 'configurable',
            'sku' => 'config1214',
            'configurable_attributes' => ['color'],
            'simples' => [
                'sku' => '2828red',
                'attribute_options' => ['color' => 'red']
            ]
        ];

        $config = [];

        $config['type'] = $product['type'];
        $config['sku'] = $product['sku'];
        $config['configurable_products_data'] = [];
        $config['configurable_attribute_ids'] = [];

        foreach ($product['configurable_attributes'] as $attributeCode) {
            $attribute = $this->entityAttribute->loadByCode('catalog_product', $attributeCode);
            if ($attribute) {
                $attributeData = ['id' => $attribute->getId(), 'options' => []];

                $optionsData = $attribute->getSource()->getAllOptions();
                foreach ($optionsData as $optionData) {
                    if (empty($optionData['value'])) {
                        continue;
                    }

                    $attributeData['options'][$optionData['value']] = $optionData['label'];
                }

                $config['configurable_attribute_ids'][$attributeCode] = $attributeData;
            }

        }

        print_r(array_search('red', $config['configurable_attribute_ids']['color']['options']));

        foreach ($product['simples'] as $simple) {
            $simpleProduct = $this->productRepository->getById(16);
            print_r($simpleProduct->getData('color'));
        }

        //print_r($config);
        die();


        /**
         * @var \Magento\Catalog\Model\Product
         */
        print_r($attribute->getSource()->getAllOptions());

        die();
        $configurableProduct = $this->productFactory->create()->load(19);
        $configurableAttributesData = $configurableProduct->getTypeInstance()->getConfigurableAttributesAsArray($configurableProduct);
        print_r($configurableAttributesData);
        $configurableSimples = $configurableProduct->getTypeInstance()->getUsedProducts($configurableProduct);
        print_r($configurableAttributesData);
        print_r(array_map(function ($item) {
            return $item->getId();
        }, $configurableSimples));

        die();

//        $productData = [
//            'sku' => 'simple-123-c12',
//            'name' => 'Simple product 123c12',
//            'website_ids' => [1],
//            'attribute_set_id' => 4,
//            'status' => \Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED,
//            'visibility' => 4,
//            'price' => 12,
//            'type_id' => 'simple',
//            'stock_data' => [
//                'use_config_manage_stock' => 0,
//                'manage_stock' => 1,
//                'is_in_stock' => 1,
//                'qty' => 1991
//            ],
//        ];
//
//        $simpleProduct = $this->productFactory->create();
//        $simpleProduct->setData($productData);
//        $simpleProduct->save();
//        $simpleId = $simpleProduct->getId();
//
//        $productData2 = [
//            'sku' => 'simple-456-e12',
//            'name' => 'Simple product 456e12',
//            'website_ids' => [1],
//            'attribute_set_id' => 4,
//            'status' => \Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED,
//            'visibility' => 4,
//            'price' => 19,
//            'type_id' => 'simple',
//            'stock_data' => [
//                'use_config_manage_stock' => 0,
//                'manage_stock' => 1,
//                'is_in_stock' => 1,
//                'qty' => 1991
//            ],
//        ];
//
//        $simpleProduct2 = $this->productFactory->create();
//        $simpleProduct2->setData($productData2);
//        $simpleProduct2->save();
//        $simpleId2 = $simpleProduct2->getId();


        $configurableProduct = $this->productFactory->create();
        $configurableProduct->setData('sku', 'Configurable Product22232323232');
        $configurableProduct->setData('name', 'Configurable Produc2333t2');

        $configurableProduct->setData('attribute_set_id', 4);

        $configurableProduct->setData('status', \Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED);

        $configurableProduct->setData('type_id', 'configurable');

        $configurableProduct->setData('price', 0);

        $configurableProduct->setData('website_ids', array(1));  // set website

        $configurableProduct->setData('category_ids', array(3));// set category

        $configurableProduct->setData('stock_data', array(

                'use_config_manage_stock' => 0, //'Use config settings' checkbox

                'manage_stock' => 1, //manage stock

                'is_in_stock' => 1, //Stock Availability

            )

        );

        $colorAttrId = $configurableProduct->getResource()->getAttribute('color')->getId();

        $configurableProduct->getTypeInstance()->setUsedProductAttributeIds(array($colorAttrId), $configurableProduct);

        $configurableAttributesData = $configurableProduct->getTypeInstance()->getConfigurableAttributesAsArray($configurableProduct);
        $configurableProduct->setCanSaveConfigurableAttributes(true);
        $configurableProduct->setConfigurableAttributesData($configurableAttributesData);

        $configurableProductsData = array();

        $configurableProductsData[16] = array( // id of a simple product associated with the configurable

            '0' => array(
                'label' => 'Red', //attribute label
                'attribute_id' => $colorAttrId, //color attribute id
                'value_index' => 6,
                'is_percent' => 0,
                'pricing_value' => '19',
            )

        );

        $configurableProductsData[18] = array(

            '0' => array(

                'label' => 'blue',

                'attribute_id' => $colorAttrId,

                'value_index' => 8,

                'is_percent' => 0,

                'pricing_value' => '20',

            )

        );

        $configurableProduct->setConfigurableProductsData($configurableProductsData);

        $configurableProduct->save();

        $configurableProductId = $configurableProduct->getId();


        $simpleProductIds = array(16, 18);

        $configurableProduct = $this->productFactory->create()->load($configurableProductId); // Load Configurable Product

        $configurableProduct->setAssociatedProductIds($simpleProductIds); // Assign simple product id

        $configurableProduct->setCanSaveConfigurableAttributes(true);

        $configurableProduct->save();

    }

    function getAllAttributes($attributeSetId)
    {

        if (!isset($this->attributeInfo[$attributeSetId])) {
            $attributeInfo = $this->attributeFactory->getCollection()->addFieldToFilter(\Magento\Eav\Model\Entity\Attribute\Set::KEY_ENTITY_TYPE_ID, $attributeSetId);
            $attributes = [];
            foreach ($attributeInfo as $info) {
                $attributes[$info->getAttributeId()] = [
                    'code' => $info->getData('attribute_code'),
                    'user_defined' => $info->getData('is_user_defined'),
                    'label' => $info->getData('frontend_label')
                ];
                $attributes[$info->getAttributeId()] += $info->getData();
            }
            $this->attributeInfo[$attributeSetId] = $attributes;

        }
        return $this->attributeInfo[$attributeSetId];
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName("skwirrel:create:config");
        $this->setDescription("import products");
        parent::configure();
    }

    private function createSimpleProduct($variant, $attributesData)
    {

        $productData = $this->createProductData($variant);
        $productData['type_id'] = 'simple';
        foreach ($attributesData as $attributeCode => $attributeData) {
            if (isset($variant['attributes'][$attributeCode])) {
                $optionId = array_search($variant['attributes'][$attributeCode], $attributeData['options']);
                if ($optionId) {
                    $productData[$attributeCode] = $optionId;
                }
            }
        }

        $product = $this->productFactory->create();
        $product->setData($productData);
        $savedProduct = $this->productRepository->save($product);
        return $savedProduct;


    }

    private function createProductData($variant)
    {
        return [
            'sku' => $variant['sku'],
            'name' => $variant['sku'],
            'website_ids' => [1],
            'attribute_set_id' => 4,
            'status' => \Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED,
            'visibility' => 4,
            'price' => isset($variant['price']) ? $variant['price'] : 0,
            'stock_data' => [
                'use_config_manage_stock' => 0,
                'manage_stock' => 1,
                'is_in_stock' => 1,
                'qty' => 100
            ],
        ];
    }

    private function createConfigurableProduduct($data)
    {
        $productData = $this->createProductData($data);
        $productData['type_id'] = 'configurable';
        $product = $this->productFactory->create();
        $product->setData($productData);
        return $this->productRepository->save($product);


    }
}
