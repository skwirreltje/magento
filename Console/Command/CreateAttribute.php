<?php


namespace Skwirrel\Pim\Console\Command;

use Magento\Catalog\Model\Product;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Magento\Framework\Setup\InstallDataInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Eav\Setup\EavSetup;
use Magento\Eav\Setup\EavSetupFactory;

class CreateAttribute extends Command
{

    const NAME_ARGUMENT = "name";
    const NAME_OPTION = "option";
    /**
     * @var \Magento\Eav\Setup\EavSetupFactory
     */
    private $setupFactory;
    /**
     * @var \Magento\Framework\Setup\ModuleDataSetupInterface
     */
    private $setup;


    public function __construct($name = null, EavSetupFactory $setupFactory, ModuleDataSetupInterface $setup)
    {
        parent::__construct($name);
        $this->setupFactory = $setupFactory;
        $this->setup = $setup;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(
        InputInterface $input,
        OutputInterface $output
    ) {

        /**
         * @var $eavSetup EavSetup
         */
        $eavSetup = $this->setupFactory->create(['setup' => $this->setup]);


        $eavSetup->addAttribute(\Magento\Catalog\Model\Category::ENTITY, 'skwirrel_id', [
            'type'     => 'int',
            'label'    => 'Skwirrel id',
            'input'    => 'text',
            'source'   => '',
            'visible'  => true,
            'default'  => '0',
            'required' => false,
            'global'   => \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_GLOBAL,
            'group'    => 'General',
        ]);
        $eavSetup->addAttribute(\Magento\Catalog\Model\Product::ENTITY, 'skwirrel_id', [
            'type'     => 'int',
            'label'    => 'Skwirrel id',
            'input'    => 'text',
            'source'   => '',
            'visible'  => true,
            'default'  => '0',
            'required' => false,
            'global'   => \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_GLOBAL,
            'group'    => 'General',
        ]);

        die();

        $config = json_decode(file_get_contents(__DIR__ . '/../../../../../../var/import/attributes.json'));
        foreach ($config as $attributeConfig) {
            $data = $this->parseAttributeConfig($attributeConfig);
            $existing = $eavSetup->getAttribute(Product::ENTITY, $attributeConfig->attribute_code);
            if($existing){
                print_r('Already exists :'.$attributeConfig->attribute_code);
                continue;
            }
            $eavSetup->addAttribute(
                Product::ENTITY,
                $attributeConfig->attribute_code,
                $data
            );
        }


    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName("skwirrel:createattribute");
        $this->setDescription("import products");
        parent::configure();
    }

    private function parseAttributeConfig($attributeConfig)
    {
        $type = $attributeConfig->type;
        $frontEnd = 'text';
        $source = '';
        if ($attributeConfig->type == 'select') {
            $type = 'int';
            $frontEnd = 'select';
            $source = \Magento\Eav\Model\Entity\Attribute\Source\Table::class;
        }
        $data = [
            'type' => $type,
            'input' => $frontEnd,
            'source' => $source,
            'label' => $attributeConfig->name,

        ];

        $data += [
            'visible' => true,
            'required' => false,
            'user_defined' => true,
            'default' => null,
            'searchable' => false,
            'filterable' => false,
            'comparable' => false,
            'visible_on_front' => false,
            'used_in_product_listing' => false,
            'unique' => false,
            'apply_to' => '',
            'system' => 1,
            'group' => 'General',
        ];

        $data['option'] = $this->parseAttributeOptions($attributeConfig);
        return $data;
    }

    private function parseAttributeOptions($attributeConfig)
    {
        $options = ['value' => []];
        foreach ($attributeConfig->options as $key => $optionConfig) {
            $options['value'][$key][0] = $optionConfig->nl;
        }
        return $options;
    }
}
