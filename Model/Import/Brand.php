<?php
namespace Skwirrel\Pim\Model\Import;

use Magento\Eav\Model\AttributeSetManagement;
use Skwirrel\Pim\Model\Converter\Etim\EtimAttribute;
use Skwirrel\Pim\Model\Mapping;
use Symfony\Component\Console\Helper\ProgressBar;

class Brand extends AbstractImport
{

    const PROCESS_NAME = 'Brand';
    const DEFAULT_ATTRIBUTE_CODE = 'brand';

    protected $existingBrands = [];

    /**
     * @var \Magento\Catalog\Model\ResourceModel\Product\Attribute\Collection
     */
    private $attributeCollection;
    /**
     * @var \Skwirrel\Pim\Model\Import\Attribute\TypeFactory
     */
    private $typeFactory;
    /**
     * @var \Magento\Framework\Filesystem\Directory\ReadFactory
     */
    private $directoryReadFactory;


    protected $parsedProductData = [];
    protected $attributeCodeIndex = [];
    /**
     * @var \Magento\Eav\Setup\EavSetup
     */
    private $eavSetup;
    /**
     * @var \Magento\Setup\Module\DataSetup
     */
    private $dataSetup;
    /**
     * @var \Magento\Catalog\Model\ResourceModel\Eav\AttributeFactory
     */
    private $attributeFactory;
    /**
     * @var \Magento\Eav\Model\Entity\Attribute\SetFactory
     */
    private $attributeSetFactory;


    public function __construct(
        \Psr\Log\LoggerInterface $logger,
        \Skwirrel\Pim\Console\Progress $progress,
        \Skwirrel\Pim\Api\MappingInterface $mapping,
        \Skwirrel\Pim\Helper\Data $helper,
        \Skwirrel\Pim\Api\ConverterInterface $converter,
        \Skwirrel\Pim\Model\Import\Attribute\TypeFactory $typeFactory,
        \Magento\Catalog\Model\ResourceModel\Product\Attribute\CollectionFactory $attributeCollectionFactory,
        \Magento\Catalog\Model\ResourceModel\Eav\AttributeFactory $attributeFactory,
        \Magento\Eav\Model\Entity\Attribute\SetFactory $attributeSetFactory,

        \Magento\Framework\Filesystem\Directory\ReadFactory $directoryReadFactory,
        \Magento\Eav\Setup\EavSetupFactory $eavSetupFactory,
        \Magento\Setup\Module\DataSetup $dataSetup

    ) {
        parent::__construct($logger, $progress, $mapping, $helper, $converter);

        $this->attributeCollection = $attributeCollectionFactory->create();

        $this->typeFactory = $typeFactory;
        $this->directoryReadFactory = $directoryReadFactory;
        $this->eavSetup = $eavSetupFactory->create(['setup' => $dataSetup]);
        $this->dataSetup = $dataSetup;
        $this->attributeFactory = $attributeFactory;
        $this->attributeSetFactory = $attributeSetFactory;
    }


    public function getAttributeCode(){

        $config = $this->mapping->getProcess(self::PROCESS_NAME);
        return isset($config['options']['attribute_code']) ? $config['options']['attribute_code'] : self::DEFAULT_ATTRIBUTE_CODE;
    }

    function import()
    {

        $existingBrands = $this->getExistingItems();
        $config = $this->getProcess();

        $attributeCode = $this->getAttributeCode();

        $data =  $this->getConvertedData();
        $this->progress->barStart('brand', count($data));

        $websites = $this->mapping->getWebsites();

        $newOptions = ['option' => ['value' => []]];
        foreach($data as $id => $item){
            $optionId = 'option_'.$id;
            if(!in_array($item->brand_name, $existingBrands)){
                $newOptions['option']['value'][$optionId][0] = $item->brand_name;
                foreach($websites as $website){
                    foreach($website['storeviews'] as $storeview){
                        $newOptions['option']['value'][$optionId][$storeview['storeviewid']] = $item->brand_name;
                    }
                }
            }

            $this->progress->barAdvance('brand');
        }
        if(count($newOptions)){
            $this->addAttributeOptions($attributeCode, $newOptions);
        }

        $this->progress->barFinish('brand');
    }




    protected function addAttributeOptions($attributeCode, $options)
    {
        $attribute = $this->attributeFactory->create();
        $attribute->loadByCode(\Magento\Catalog\Model\Product::ENTITY, $attributeCode);
        $attribute->addData($options);
        $attribute->save();
    }

    public function addAttribute($attributeName, $attributeData)
    {
        $this->eavSetup->addAttribute(
            \Magento\Catalog\Model\Product::ENTITY,
            $attributeName,
            $attributeData
        );

    }

    private function getExistingItems()
    {
        $config = $this->mapping->getProcess(self::PROCESS_NAME);

        $attributeCode = $this->getAttributeCode();

        $attribute = $this->attributeFactory->create();
        $attribute->loadByCode(\Magento\Catalog\Model\Product::ENTITY, $attributeCode);

        if (empty($attribute->getData())) {

            $this->createAttribute($attributeCode);
            return $this->existingBrands;
        }

        $this->existingBrands = [];
        $optionsData = $attribute->getSource()->getAllOptions();
        foreach ($optionsData as $option) {
            $this->existingBrands[] = $option['label'];
        }

        return $this->existingBrands;
    }

    private function createAttribute($attributeCode)
    {

        $attributeData = [
            'label' => 'Brand',
            'input' => 'select',
            'type' => 'int',
            'global' => 1,
            'class' => '',
            'backend' => '',
            'source' => \Magento\Eav\Model\Entity\Attribute\Source\Table::class,
            'visible' => true,
            'required' => false,
            'searchable' => true,
            'filterable' => true,
            'comparable' => false,
            'user_defined' => true,
            'is_user_defined' => true,
            'visible_on_front' => true,
            'used_in_product_listing' => true,
            'is_unique' => false,
        ];

        $mappedAttributes = $this->mapping->getAttributes();
        foreach($mappedAttributes as $mappedAttribute){
            if($attributeCode == $mappedAttribute->getMagentoName()){
                $attributeData = array_replace($attributeData, $mappedAttribute->prepareNew());
            }
        }

        if($attributeData['filterable'] == '1' && $attributeData['input'] == 'select'){
            $attributeData['filterable'] = 2;
        }


        $this->addAttribute($attributeCode, $attributeData);

        // add to all sets
        $attributeSet = $this->attributeSetFactory->create();
        $entityTypeId = $this->eavSetup->getEntityTypeId(\Magento\Catalog\Model\Product::ENTITY);
        $setCollection = $attributeSet->getResourceCollection()
            ->addFieldToFilter('entity_type_id', $entityTypeId)
            ->load();

        /**
         * @var $set \Magento\Eav\Model\Entity\Attribute\Set
         */
        foreach ($setCollection as $set) {
            $this->eavSetup->addAttributeToSet(
                \Magento\Catalog\Model\Product::ENTITY,
                $set->getId(),
                Mapping::GROUP_NAME_GENERAL,
                $attributeCode
            );
        }

    }


}