<?php

namespace Skwirrel\Pim\Console\Command;

use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Framework\App\ObjectManager;
use Skwirrel\Pim\Api\MappingInterface;
use Skwirrel\Pim\Import\Model\ImportDirectory;
use Skwirrel\Pim\Model\Etim\Converter\Feature;
use Skwirrel\Pim\Model\ProductImporter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class GetProduct extends Command
{

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
     * @var \Skwirrel\Pim\Client\ApiClient
     */
    private $apiClient;
    /**
     * @var \Skwirrel\Pim\Api\MappingInterface
     */
    private $mapping;
    /**
     * @var \Skwirrel\Pim\Model\ModelFactory
     */
    private $adapterFactory;
    /**
     * @var \Skwirrel\Pim\Model\ProductImporter
     */
    private $importer;

    public function __construct(
        \Skwirrel\Pim\Client\ApiClient $apiClient,
        \Magento\Framework\App\State $state,
        MappingInterface $mapping,
        \Skwirrel\Pim\Model\ModelFactory $adapterFactory,
        \Magento\Eav\Model\Entity\Attribute $entityAttribute,
        ProductImporter $importer

    ) {

        parent::__construct();
        $this->state = $state;
        $this->apiClient = $apiClient;
        $this->mapping = $mapping;
        $this->adapterFactory = $adapterFactory;
        $this->entityAttribute = $entityAttribute;
        $this->importer = $importer;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(
        InputInterface $input,
        OutputInterface $output
    ) {

        $this->state->setAreaCode(\Magento\Framework\App\Area::AREA_ADMINHTML);

        $id = $input->getArgument('id');
        $response = $this->apiClient->makeRequest('getProductsByID', [
            'product_id' => [$id],
            'include_trade_items' => true,
            'include_trade_item_prices' => true,
            'include_etim' => true,
            'include_categories' => true,
            'include_attachments' => true,
            'include_languages' => ['en','nl']
        ]);

        $product = $response->products->{$id};

        $this->importer->import($product);

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
        $this->setName("skwirrel:product:get");
        $this->setDefinition([
            new InputArgument('id', InputArgument::REQUIRED, "id"),
        ]);
        $this->setDescription("download products");
        parent::configure();
    }

    private function parseProduct($product)
    {
        $parsed = ['attributes' => []];

        foreach($product as $key => $value){
            if(substr($key,0,1) !== '_'){
                $parsed[$key] = $value;
                continue;
            }

            if($key== '_etim'){
                foreach($value->{'_etim_features'} as $featureCode => $feature){
                    $parsed['attributes'][$featureCode] = $this->parseFeature($feature);
                }
            }
        }
        return $parsed;


    }

    private function parseFeature($feature)
    {
        $parser = new Feature();
        return $parser->convert($feature);

    }

    private function resolveAttributeValue($name, $value)
    {
        $attr = $this->entityAttribute->loadByCode(4,$name);
        if(!$attr){
            print_r('cannot load:'.$name);
            return ;
        }
        if($value){
            if($value instanceof SelectValue){


                $options = $attr->getOptions();
                if($options){
                    foreach($options as $option){
                        if($option->getLabel() == $value->value){
                            return $option->getValue();
                        }
                    }
                }
                return '';

            }
            return $value->value;
        }

    }

}

class LogicalValue
{
    public $value;
    public function __construct($value)
    {
        $this->value = $value;
    }
}
class NumericalValue
{
    public $value;
    public function __construct($value)
    {
        $this->value = $value;
    }
}
class SelectValue
{
    public $value;
    public function __construct($value)
    {
        $this->value = $value;
    }
}
