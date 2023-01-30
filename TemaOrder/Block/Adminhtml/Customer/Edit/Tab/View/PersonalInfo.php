<?php 
namespace Cypisnet\TemaOrder\Block\Adminhtml\Customer\Edit\Tab\View;

use Magento\Customer\Controller\RegistryConstants;

class PersonalInfo extends \Magento\Customer\Block\Adminhtml\Edit\Tab\View\PersonalInfo
{
	public function getTemaErpID()
    {
    	$customerModel = $this->getCustomerRegistry()->retrieve($this->getCustomerId());
        return $customerModel->getData('customer_tema_id');
    }
}