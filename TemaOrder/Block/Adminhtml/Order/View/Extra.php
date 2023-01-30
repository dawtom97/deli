<?php
namespace Cypisnet\TemaOrder\Block\Adminhtml\Order\View;

class Extra extends \Magento\Backend\Block\Template
{

	protected $request;
	protected $orderRepository;
	protected $scopeConfig;
	protected $address;
	

    public function __construct(
        \Magento\Framework\App\Request\Http $request,
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository,
	  \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
	  \Magento\Customer\Model\Address $address,
        \Magento\Backend\Block\Template\Context $context, 
        array $data = []
    ) {
        $this->request = $request;
        $this->orderRepository = $orderRepository;
	  $this->scopeConfig = $scopeConfig;
	  $this->address = $address;
        parent::__construct($context, $data);
    }
    
    
	public function GetOrderData(){
		$order = $this->orderRepository->get( $this->request->getParam('order_id') );
		if($order)
		{
			return (!empty($order->getData('TEMA_ID'))?$order->getData('TEMA_ID'):null);
		}
		return null;
	}
	

	public function GetCustomerData(){
		$order = $this->orderRepository->get( $this->request->getParam('order_id') );
		$order_billing=$order->getBillingAddress()->getData();
		$addressObject = $this->address->load( (int) $order_billing['customer_address_id'] ) ;
		if($addressObject)
			return $addressObject->getData('tema_id');
			else
			return null;
	}
}