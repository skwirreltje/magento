<?php


namespace Skwirrel\Pim\Setup;

use Magento\Framework\Setup\InstallSchemaInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;

class InstallSchema implements InstallSchemaInterface
{

    /**
     * {@inheritdoc}
     */
    public function install(
        SchemaSetupInterface $setup,
        ModuleContextInterface $context
    ) {
        $table_skwirrel_pim_pimmapping = $setup->getConnection()->newTable($setup->getTable('skwirrel_pim_pimmapping'));

        $table_skwirrel_pim_pimmapping->addColumn(
            'pimmapping_id',
            \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
            null,
            ['identity' => true,'nullable' => false,'primary' => true,'unsigned' => true,],
            'Entity ID'
        );

        $table_skwirrel_pim_pimmapping->addColumn(
            'mapping_id',
            \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
            null,
            ['nullable' => False,'identity' => true,'auto_increment' => true,'unsigned' => true],
            'mapping_id'
        );

        $table_skwirrel_pim_pimmapping->addColumn(
            'source_field_name',
            \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
            255,
            [],
            'source_field_name'
        );

        $table_skwirrel_pim_pimmapping->addColumn(
            'target_attribute_id',
            \Magento\Framework\DB\Ddl\Table::TYPE_INTEGER,
            null,
            ['unsigned' => true],
            'target_attribute_id'
        );

        $setup->getConnection()->createTable($table_skwirrel_pim_pimmapping);
    }
}
