<?php
namespace Cypisnet\TemaOrder\Setup;

use Magento\Customer\Setup\CustomerSetupFactory;
use Magento\Customer\Model\Customer;
use Magento\Eav\Model\Entity\Attribute\Set as AttributeSet;
use Magento\Eav\Model\Entity\Attribute\SetFactory as AttributeSetFactory;
use Magento\Framework\Setup\InstallDataInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Sales\Setup\SalesSetupFactory;

use Magento\Eav\Setup\EavSetup;
use Magento\Eav\Setup\EavSetupFactory;;
use Magento\Eav\Model\Config;
use Magento\Customer\Api\CustomerMetadataInterface;

/**
 * @codeCoverageIgnore
 */
class InstallData implements InstallDataInterface
{
    
    /**
     * @var CustomerSetupFactory
     */
    protected $customerSetupFactory;
    
    /**
     * @var AttributeSetFactory
     */
    private $attributeSetFactory;
    protected $salesSetupFactory;
	private $eavSetupFactory;
	private $eavConfig;
    /**
     * @param CustomerSetupFactory $customerSetupFactory
     * @param AttributeSetFactory $attributeSetFactory
     */
    public function __construct(
        CustomerSetupFactory $customerSetupFactory,
        AttributeSetFactory $attributeSetFactory,
        SalesSetupFactory $salesSetupFactory,
        EavSetupFactory $eavSetupFactory, 
        \Magento\Eav\Model\Config $eavConfig
    ) {
        $this->customerSetupFactory = $customerSetupFactory;
        $this->attributeSetFactory = $attributeSetFactory;
        $this->salesSetupFactory = $salesSetupFactory;
        $this->eavSetupFactory = $eavSetupFactory;
        $this->eavConfig       = $eavConfig;
    }
 
    
    /**
     * {@inheritdoc}
     */
    public function install(ModuleDataSetupInterface $setup, ModuleContextInterface $context)
    {
      $installer = $setup;
 
      $installer->startSetup();

        /** @var CustomerSetup $customerSetup */
        $customerSetup = $this->customerSetupFactory->create(['setup' => $setup]);
        
        $customerEntity = $customerSetup->getEavConfig()->getEntityType('customer_address');
        $attributeSetId = $customerEntity->getDefaultAttributeSetId();
        
        
        /** @var $attributeSet AttributeSet */
        $attributeSet = $this->attributeSetFactory->create();
        $attributeGroupId = $attributeSet->getDefaultGroupId($attributeSetId);
        
        /* Customer TEMA ERP ID */
         /** @var \Magento\Eav\Setup\EavSetup $eavSetup */
        $eavSetup = $this->eavSetupFactory->create(['setup' => $setup]);
         $attributeCode = 'customer_tema_id';

        $eavSetup->addAttribute(CustomerMetadataInterface::ENTITY_TYPE_CUSTOMER, $attributeCode, [
            'label' => 'TemaERP Customer ID',
            'required' => false,
            'user_defined' => 1,
            'system' => 0,
            'position' => 999,
            'is_used_in_grid'       => true,
        	'is_visible_in_grid'    => true
        ]);

        $eavSetup->addAttributeToSet(
            CustomerMetadataInterface::ENTITY_TYPE_CUSTOMER,
            CustomerMetadataInterface::ATTRIBUTE_SET_ID_CUSTOMER,
            null,
            $attributeCode);

        $amountSpend = $this->eavConfig->getAttribute(CustomerMetadataInterface::ENTITY_TYPE_CUSTOMER, $attributeCode);
        $amountSpend->setData('used_in_forms', [
           	'adminhtml_customer',
            'customer_account_create',
            'customer_account_edit'
        ]);
        $amountSpend->getResource()->save($amountSpend);
        
        $customerSetup->removeAttribute( 'customer_address' ,'tema_id');
        $customerSetup->addAttribute('customer_address', 'tema_id', [
            'type' => 'varchar',
            'label' => 'TEMA ERP ID',
            'input' => 'text',
            'required' => false,
            'visible' => true,
            'user_defined' => false,
            'sort_order' => 1000,
            'position' => 1000,
            'system' => 0,
        ]);
        
        $attribute = $customerSetup->getEavConfig()->getAttribute('customer_address', 'tema_id')
                  ->addData([
                        'attribute_set_id' => $attributeSetId,
                        'attribute_group_id' => $attributeGroupId,
                        'used_in_forms' => ['adminhtml_customer_address'],
                  ]);
        
        $attribute->save();
       
        $connection = $installer->getConnection();
 
        if ($connection->tableColumnExists('sales_order', 'TEMA_ID') === false) {
            $connection
             ->addColumn(
                    $setup->getTable('sales_order'),
                    'TEMA_ID',
                    [
                        'type' => \Magento\Framework\DB\Ddl\Table::TYPE_TEXT,
                        'length' => 255,
                        'comment' => 'TEMA_ID'
                    ]
                );
                /*
                ->addColumn(
                    $setup->getTable('sales_order'),
                    'TEMA_ID',
                    [
                        'type' => 'varchar',
                        'length' => 255,
                        'nullable' => true,
                        'default' => '',
                        'comment' => 'TEMA_ID'
                    ]
                );
                */
        }
        
        
       
        
        /* 
        $salesSetup = $this->salesSetupFactory->create(['setup' => $setup]);
        $options = [  
                        'label' => 'TEMA ERP Order ID',
                        'input' => 'text',
                        'type' => 'varchar', 
                        'visible' => false, 
                        'required' => false,
                        'user_defined' => false,
                         'system' => 0
                  ];
        $salesSetup->removeAttribute(\Magento\Sales\Model\Order::ENTITY,'order_tema_id');
        $salesSetup->addAttribute(\Magento\Sales\Model\Order::ENTITY , 'order_tema_id', $options);
		$attribute = $customerSetup->getEavConfig()->getAttribute(\Magento\Sales\Model\Order::ENTITY, 'order_tema_id')
	                  ->addData([
	                        'attribute_set_id' => $attributeSetId,
	                        'attribute_group_id' => $attributeGroupId,
	                        'used_in_forms' => [],
	                  ]);
        
        */
        
        $installer->endSetup();
    }
}