<?php
namespace Cypisnet\TemaOrder\Block\Adminhtml\System\Config\Field;

use Magento\Framework\View\Element\Html\Select;

class DropdownValues extends Select
{
    /**
     * Set "name" for <select> element
     *
     * @param string $value
     * @return $this
     */
     
     protected $shipconfig;

	public function getShippingMethods(){
		
		$ObjectManager = \Magento\Framework\App\ObjectManager::getInstance(); 
		$scopeConfig = $ObjectManager->create('\Magento\Framework\App\Config\ScopeConfigInterface');
		$shipconfig = $ObjectManager->create('\Magento\Shipping\Model\Config');
		
	        $activeCarriers = $shipconfig->getActiveCarriers();
	        $storeScope = \Magento\Store\Model\ScopeInterface::SCOPE_STORE;
	            foreach($activeCarriers as $carrierCode => $carrierModel)
	            {
	               $options = array();
	               if( $carrierMethods = $carrierModel->getAllowedMethods() )
	               {
	                   foreach ($carrierMethods as $methodCode => $method)
	                   {
	                        $code= $carrierCode.'_'.$methodCode;
	                        $options[]=array('value'=>$code,'label'=>$method);

	                   }
	                   $carrierTitle =$scopeConfig->getValue('carriers/'.$carrierCode.'/title');

	               }
	                $methods[]=array('value'=>$options,'label'=>$carrierTitle);
	            }
	        return $methods;        

	    }
    
    public function setInputName($value)
    {
        return $this->setName($value);
    }

    /**
     * Set "id" for <select> element
     *
     * @param $value
     * @return $this
     */
    public function setInputId($value)
    {
        return $this->setId($value);
    }

    /**
     * Render block HTML
     *
     * @return string
     */
    public function _toHtml(): string
    {
        if (!$this->getOptions()) {
            $this->setOptions($this->getSourceOptions());
        }
        return parent::_toHtml();
    }

    private function getSourceOptions(): array
    {
        return $this->getShippingMethods();
        /*[
            ['label' => 'Yes', 'value' => '1'],
            ['label' => 'No', 'value' => '0'],
        ];
        */
    }
}