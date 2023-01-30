<?php
namespace Cypisnet\TemaOrder\Observer;

use Magento\Framework\Event\ObserverInterface;
use Psr\Log\LoggerInterface;

class Orderplaceafter implements ObserverInterface
{

	protected $logger;
	protected $_dir;
	protected $orderFactory;
	protected $addressRepository;

	public function __construct(LoggerInterface $logger,
					\Magento\Framework\Filesystem\DirectoryList $dir,
					\Magento\Sales\Model\Order $orderFactory,
					\Magento\Customer\Model\Address $addressRepository
					) {
		$this->_dir = $dir;
		$this->logger = $logger;
		$this->orderFactory = $orderFactory;
		$this->addressRepository = $addressRepository;
	}
	public function execute(\Magento\Framework\Event\Observer $observer)
	{
	
		$order = $observer->getEvent()->getOrder();
		//include($this->_dir->getRoot().'/httpful.phar');
		/*
		$response = \Httpful\Request::get('https://api-deli.ihurt.eu/api/erp/v1/warehouse/01/dictionaries/products?ts='.$ts)->addHeader('WebApiKey', 'ca623aaf-70f2-4587-bf4e-c31a119861d5')->send();
		*/
		$dump=[];
		$dump['order']=$order->getData();
		$order_billing=$order->getBillingAddress()->getData();
		$addressObject = $this->addressRepository->load( (int) $order_billing['customer_address_id'] ) ;

		$dump['b_address'] = $addressObject->getData();
		file_put_contents ($this->_dir->getPath('var')."/Cypis_tema.log", json_encode( $dump ) );
	}
	/*
	public function execute(\Magento\Framework\Event\Observer $observer){
		try{
			
			$order = $observer->getEvent()->getOrder();
			file_put_contents ($this->_dir->getPath('var')."/Cypis_tema.log", json_encode( $order->getData() ) );
			var_dump($order->getData());
			die('jsdjsdjf');
		   }catch (\Exception $e) {
				$this->logger->info($e->getMessage());
				}
	    }
	*/
}
