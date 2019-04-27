<?php


namespace Skwirrel\Pim\Console\Command;

use Magento\Catalog\Model\Product\Visibility;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Eav\Setup\EavSetup;
use Magento\Framework\Api\Filter;
use Magento\Framework\Api\Search\FilterGroup;
use Magento\Framework\Api\SearchCriteria;
use Magento\Framework\App\ObjectManager;
use Skwirrel\Pim\Model\Converter\Etim\EtimAttribute;
use Skwirrel\Pim\Import\Data\Model\Product;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ImportCategories extends Command
{

    const NAME_ARGUMENT = "name";
    const NAME_OPTION = "option";
    const FIELD_SKU = 'sku';
    const FIELD_STORE_VIEW_CODE = 'store_view_code';
    const FIELD_ATTRIBUTE_SET_CODE = 'attribute_set_code';
    const FIELD_PRODUCT_TYPE = 'product_type';
    const FIELD_URL_KEY = 'url_key';
    const FIELD_CATEGORIES = 'categories';
    const FIELD_PRODUCT_WEBSITES = 'product_websites';
    const FIELD_NAME = 'name';
    const FIELD_DESCRIPTION = 'description';
    const FIELD_SHORT_DESCRIPTION = 'short_description';
    const FIELD_WEIGHT = 'weight';
    const FIELD_PRODUCT_ONLINE = 'product_online';
    const FIELD_TAX_CLASS_NAME = 'tax_class_name';
    const FIELD_VISIBILITY = 'visibility';
    const FIELD_PRICE = 'price';
    const FIELD_QTY = 'qty';
    const FIELD_IS_IN_STOCK = 'is_in_stock';
    const FIELD_SKWIRREL_ID = 'skwirrel_id';

    protected $isBaseProduct = true;


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
    /**
     * @var \Magento\Framework\Filesystem\Directory\ReadFactory
     */
    private $directoryReadFactory;
    /**
     * @var \Skwirrel\Pim\Model\Converter\Etim\EtimAttribute
     */
    private $attributeConverter;
    /**
     * @var \Skwirrel\Pim\Helper\Data
     */
    private $dataHelper;
    /**
     * @var EavSetup
     */
    protected $eavSetup;

    protected $attributeCollection;

    protected $parsedAttributes = [];
    protected $existingAttributes = [];

    protected $defaultValues = [];
    /**
     * @var \Skwirrel\Pim\Model\Api\MappingInterface
     */
    private $mapping;
    public $requiredFields = [
        self::FIELD_SKU => '',
        self::FIELD_STORE_VIEW_CODE => '',
        self::FIELD_ATTRIBUTE_SET_CODE => '',
        self::FIELD_PRODUCT_TYPE => '',
        self::FIELD_URL_KEY => '',
        self::FIELD_CATEGORIES => '',
        self::FIELD_PRODUCT_WEBSITES => '',
        self::FIELD_NAME => '',
        self::FIELD_DESCRIPTION => '',
        self::FIELD_SHORT_DESCRIPTION => '',
        self::FIELD_WEIGHT => 0.0000,
        self::FIELD_PRODUCT_ONLINE => 0,
        self::FIELD_TAX_CLASS_NAME => '',
        self::FIELD_VISIBILITY => '',
        self::FIELD_PRICE => 1.0000,
        self::FIELD_QTY => 0,
        self::FIELD_IS_IN_STOCK => 0,
        self::FIELD_SKWIRREL_ID => '',
    ];

    protected $requiredValues = [
        self::FIELD_SKU,
        self::FIELD_ATTRIBUTE_SET_CODE,
        self::FIELD_PRODUCT_TYPE,
        self::FIELD_NAME,
        self::FIELD_TAX_CLASS_NAME,
        self::FIELD_SKWIRREL_ID,
    ];

    const STORE_BASE = 0;
    /**
     * @var \Skwirrel\Pim\Client\ApiClient
     */
    private $apiClient;
    /**
     * @var \Magento\Catalog\Model\CategoryFactory
     */
    private $categoryFactory;
    /**
     * @var \Magento\Catalog\Model\ResourceModel\Category\Collection
     */
    private $categoryCollection;

    protected $existingMap = [];

    public function __construct(
        $name = null,
        \Magento\Catalog\Model\ProductFactory $productFactory,
        \Magento\Eav\Model\Entity\Attribute $entityAttribute,
        \Magento\Catalog\Model\ProductRepository $productRepository,
        \Magento\Catalog\Model\ResourceModel\Eav\Attribute $attributeFactory,
        \Magento\Catalog\Model\ResourceModel\Product\Attribute\CollectionFactory $attributeCollectionFactory,

        \Magento\Eav\Model\ResourceModel\Entity\Attribute\Option\Collection $attributeOptionCollection,

        EtimAttribute $attributeConverter,
        \Skwirrel\Pim\Helper\Data $dataHelper,

        \Magento\Framework\Filesystem\Directory\ReadFactory $directoryReadFactory,
        \Magento\Eav\Setup\EavSetupFactory $eavSetupFactory,
        \Magento\Setup\Module\DataSetup $dataSetup,
        \Skwirrel\Pim\Api\MappingInterface $mapping,
        \Skwirrel\Pim\Client\ApiClient $apiClient,
        \Magento\Catalog\Model\CategoryFactory $categoryFactory,
        \Magento\Catalog\Model\ResourceModel\Category\Collection $categoryCollection,


        \Magento\Framework\App\State $state
    ) {
        $this->productFactory = $productFactory;

        parent::__construct($name);
        $this->state = $state;
        $this->entityAttribute = $entityAttribute;
        $this->productRepository = $productRepository;
        $this->attributeFactory = $attributeFactory;
        $this->directoryReadFactory = $directoryReadFactory;
        $this->attributeConverter = $attributeConverter;
        $this->dataHelper = $dataHelper;
        $this->attributeCollection = $attributeCollectionFactory->create();
        $this->eavSetup = $eavSetupFactory->create(['setup' => $dataSetup]);
        $this->mapping = $mapping;
        $this->apiClient = $apiClient;
        $this->categoryFactory = $categoryFactory;
        $this->categoryCollection = $categoryCollection;
    }


    /**
     * {@inheritdoc}
     */
    protected function execute(
        InputInterface $input,
        OutputInterface $output
    ) {

        $this->state->setAreaCode(\Magento\Framework\App\Area::AREA_ADMINHTML);


        $this->categoryCollection
            ->addAttributeToFilter('skwirrel_id',['gt' => 0])->load();


        foreach($this->categoryCollection->getItems() as $item){
            $this->existingMap[$item->getSkwirrelId()] =  $item->getId();
        }



        /**
         * @var $crit \Magento\Framework\Api\Search\SearchCriteria
         */
        $this->mapping->load();
        $websites = $this->mapping->getWebsites();

        $process = $this->mapping->getProcess('Category');
        print_r($process);
        die();


        $response = $this->apiClient->makeRequest('getCategories', [
            'super_category_id' => 2,
            'include_category_translations' => true,
            'include_languages' => ['nl', 'en']
        ]);

        $tree = [];

        foreach ($response->categories as $category) {
            if (isset($category->parent_category_id)) {
                if (!isset($tree[$category->parent_category_id])) {
                    $tree[$category->parent_category_id] = $response->categories->{$category->parent_category_id};
                    $response->categories->{$category->parent_category_id}->children = [];
                }
                $tree[$category->parent_category_id]->children[] = $category;
            } else {
                if (!isset($tree[$category->product_category_id])) {
                    $tree[$category->product_category_id] = $category;
                } else {
                    die('cre');
                }
            }
        }

        $this->walkPath('1/2', $tree);
        die();

        $rootCatId = 2;
        $category = $this->categoryFactory->create();
        $path = '1/' . $rootCatId;
        $categoryData = [
            'parent_id' => 2,
            'url_key' => $category->formatUrlKey('zooi'),
            'name' => 'Zooi',
            'skwirrel_id' => 666,
            'include_in_menu' => true,
            'is_active' => true,
            'custom_attributes' => [
                'display_mode' => 'PRODUCTS',

            ],
            'store_id' => 1,

        ];
        $category
            ->setData($categoryData)
            ->setPath($path)
            ->setAttributeSetId($category->getDefaultAttributeSetId());

        //$category->save();

        $prod = $this->productRepository->getById(40);

        $prod->setCategoryIds([7, 6]);
        $prod->setVisibility(Visibility::VISIBILITY_BOTH);
        $this->productRepository->save($prod);



        die();


        $this->mapping->load();
        $websites = $this->mapping->getWebsites();

        $process = $this->mapping->getProcess('Category');


        $categories = $this->parseCategories();

        print_r($categories);


    }

    function createProducts()
    {
        $baseDir = $this->getFileBasePath();
        $reader = $this->directoryReadFactory->create($baseDir);
        $parsed = [];
        foreach ($reader->read() as $file) {
            $filePath = $baseDir . DIRECTORY_SEPARATOR . $file;
            if (strpos($file, 'product') !== false && substr($file, -4) == 'json') {
                $data = json_decode(file_get_contents($filePath));
                $existing = false;
                try {

                    $existing = $this->productRepository->get('skwirrel_' . $data->product_id);
                } catch (\Exception $e) {

                }
                if (!$existing) {
                    $proddata = [
                        'sku' => 'skwirrel_' . $data->product_id,
                        'name' => 'skwirrel_' . $data->product_id,
                        'website_ids' => [1],
                        'attribute_set_id' => 4,
                        'status' => \Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED,
                        'visibility' => 4,
                        'price' => 0,
                        'stock_data' => [
                            'use_config_manage_stock' => 0,
                            'manage_stock' => 1,
                            'is_in_stock' => 1,
                            'qty' => 100
                        ],

                    ];
                    if (isset($data->_trade_items)) {
                        $tradeItems = (array)$data->_trade_items;
                        if (count($tradeItems)) {
                            foreach ($tradeItems as $tradeItem) {
                                if (isset($tradeItem->_trade_item_prices)) {
                                    foreach ($tradeItem->_trade_item_prices as $price) {
                                        $proddata['price'] = $price->gross_price;

                                    }
                                }
                            }
                        }
                    }


                    $proddata['EF001618'] = 24;

                    $product = $this->productFactory->create();
                    $product->setData($proddata);
                    print_r($proddata);
                    return $this->productRepository->save($product);

                    die();
                }


            }
        }
    }

    function createAttribute($key, $attribute)
    {
        $this->eavSetup->addAttribute(
            \Magento\Catalog\Model\Product::ENTITY,
            $key,
            $attribute
        );

    }


    function parseCategories()
    {

        $baseDir = $this->getFileBasePath();
        $reader = $this->directoryReadFactory->create($baseDir);
        $parsed = [];
        foreach ($reader->read() as $file) {
            $filePath = $baseDir . DIRECTORY_SEPARATOR . $file;
            if (strpos($file, 'product') !== false && substr($file, -4) == 'json') {
                $data = json_decode(file_get_contents($filePath));

                $attributes = $this->attributeConverter->convert($data);
                foreach ($attributes as $key => $attribute) {
                    $orgKey = $key;
                    $key = isset($keyMap[$key]) ? $keyMap[$key] : $key;
                    $this->parseAttribute($key, $attribute);
                }
            }

        }


        return $this->parsedAttributes;

    }

    function parseAttribute($key, $attribute)
    {


        if (!isset($this->parsedAttributes[$key])) {
            $this->parsedAttributes[$key] = $attribute['config'];
            $this->parsedAttributes[$key]['labels'] = $attribute['labels'];
            $this->parsedAttributes[$key]['label'] = array_values($attribute['labels'])[0];
        }

        if ($attribute['value_type'] == 'A') {

            if (!isset($this->parsedAttributes[$key]['option'])) {
                $this->parsedAttributes[$key]['option'] = [
                    'value' => []
                ];
            }

            if ($attribute['value_code'] != '') {
                $valueLabels = array_values($attribute['value_labels']);
                $this->parsedAttributes[$key]['option']['value'][$attribute['value_code']][0] = $valueLabels[0];
            }

        }
    }

    private function getFileBasePath()
    {
        $path = $this->dataHelper->getDirectory('var');
        if (!file_exists($path . '/import')) {
            mkdir($path . '/import');
        }
        return $path . '/import';
    }


    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName("skwirrel:importcategories");
        $this->setDescription("import products");
        parent::configure();
    }

    private function getDefaultValues()
    {
        if (empty($this->defaultValues)) {
            if ($this->isBaseProduct) {
                $defaultWebsite = $this->mapping->getDefaultWebsite();
                $this->defaultValues = array_replace($this->requiredFields, [
                    self::FIELD_STORE_VIEW_CODE => $defaultWebsite['storeviews'][$this->mapping->getDefaultLanguage()]['storeview'],
                    self::FIELD_PRODUCT_TYPE => 'simple',
                    self::FIELD_PRODUCT_WEBSITES => $defaultWebsite['website'],
                    //self::FIELD_TAX_CLASS_NAME => $this->mapping->getDefaultTax(),
                    self::FIELD_PRODUCT_ONLINE => 1,
                    self::FIELD_VISIBILITY => 'Catalog, Search',
                ]);
            }

            // Override default values defined in mapping XML
//            $attributes = $this->mapping->getAttributes();
//            foreach ($attributes as $attribute) {
//                if ($attribute['level'] == $this->identifier) {
//                    $this->defaultValues[$attribute['magento_name']] = $attribute['default_value'];
//                }
//            }
        }

        return $this->defaultValues;

    }

    private function walkPath($string, $tree, $level = 0)
    {
        print_r(['level' => $level]);
        $catIds = explode('/',$string);
        $parentCatId = array_pop($catIds);
        foreach ($tree as $cat) {
            if ($cat->depth == $level) {
                if(!isset($this->existingMap[$cat->product_category_id])){

                    $category = $this->categoryFactory->create();
                    $path = $string;
                    $categoryData = [
                        'parent_id' => $parentCatId,
                        'url_key' => $category->formatUrlKey($cat->_translation->nl->category_name),
                        'name' => $cat->_translation->nl->category_name,
                        'skwirrel_id' => $cat->product_category_id,
                        'include_in_menu' => true,
                        'is_active' => true,
                        'custom_attributes' => [
                            'display_mode' => 'PRODUCTS',

                        ],
                        'store_id' => 1,

                    ];
                    $category
                        ->setData($categoryData)
                        ->setPath($path)
                        ->setAttributeSetId($category->getDefaultAttributeSetId());

                    $category->save();
                    $entityId = $category->getId();
                    $this->existingMap[$cat->product_category_id] = $entityId;
                }
                else{
                    $entityId = $this->existingMap[$cat->product_category_id];
                }


                print_r(['created' => $entityId]);
                if(isset($cat->children)){
                    $this->walkPath($string.'/'.$entityId,$cat->children, $level+1 );

                }

            //    die();
            }
        }
    }


}
