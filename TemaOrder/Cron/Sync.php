<?php

namespace Cypisnet\TemaOrder\Cron;

class Sync
{


	protected $_customerFactory;
	protected $_orderFactory;
	protected $_ScopeConfig;
	protected $_addressFactory;
	protected $_countryFactory;
	protected $objectManager;
	protected $_configWriter;
	protected $_configInterface;
	
	public function __construct(
	    \Magento\Customer\Model\ResourceModel\Customer\CollectionFactory $customerFactory,
	    \Magento\Sales\Model\ResourceModel\Order\CollectionFactory $orderFactory,
	    \Magento\Framework\App\Config\ScopeConfigInterface $ScopeConfigInterface,
	    \Magento\Customer\Model\AddressFactory $addressFactory,
	    \Magento\Directory\Model\CountryFactory $countryFactory,
	    \Magento\Framework\App\Config\Storage\WriterInterface $configWriter,
	     \Magento\Framework\App\Config\ConfigResource\ConfigInterface $configInterface
	) {
	    $this->_customerFactory = $customerFactory;
	    $this->_orderFactory = $orderFactory;
	    $this->_ScopeConfig = $ScopeConfigInterface;
	    $this->_addressFactory = $addressFactory;
	    $this->_countryFactory = $countryFactory;
	    $this->_configWriter = $configWriter;
	    $this->objectManager = $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
	     $this->_configInterface = $configInterface;
	}

	/**
	 * Get customer collection
	 */
	public function getCustomerCollection()
	{
	    return $this->_customerFactory->create();
	}
	
	/**
	 * Get customer collection
	 */
	public function getOrderCollection()
	{
	    return $this->_orderFactory->create();
	}

	public function execute()
	{
		if($this->_ScopeConfig->getValue('temaerp/general/enable')!=1)
			return true;
		
		@stream_wrapper_restore('phar');			
		require(dirname(__FILE__).'/httpful.phar');
		
		$apikey = $this->_ScopeConfig->getValue('temaerp/general/webapikey');
		$apiurl = $this->_ScopeConfig->getValue('temaerp/general/webapiurl');
		$warehouseId = $this->_ScopeConfig->getValue('temaerp/general/order_warehouseId');
		
		if(!$apiurl)
			$apiurl='https://api-deli.ihurt.eu';
		
		$order_per_minute = intval( $this->_ScopeConfig->getValue('temaerp/general/order_per_minute') );
		$customer_per_minute = intval( $this->_ScopeConfig->getValue('temaerp/general/customer_per_minute') );
		
		if(empty($order_per_minute) ){
			$order_per_minute=5;
		}
		if(empty($customer_per_minute)){
			$customer_per_minute=5;
		}
		
		$customers_TemaIDs = $this->_ScopeConfig->getValue('temaerp/general/customers_TemaIDs');
		$customers_TemaIDs = explode( ',' , $customers_TemaIDs);
		/*
		$this->_configWriter->save('temaerp/general/customers_TemaIDs', $value);
		*/
		
		$writer = new \Zend\Log\Writer\Stream(BP . '/var/log/temacron.log');
		$logger = new \Zend\Log\Logger();
		$logger->addWriter($writer);
		$logger->info(__METHOD__);

		$customets_collection = $this->getCustomerCollection()
                						->addFieldToFilter("customer_tema_id", [ array('null' => true), array('eq' => '')] )
										->setPageSize($customer_per_minute)
										->setCurPage(1)
										->load();
										
										
		if($customets_collection->getSize()){
			foreach($customets_collection->getItems() as $customer)
					{
						
						if( is_array($customers_TemaIDs) and !empty($customers_TemaIDs[ key($customers_TemaIDs)]) )
						{
							$customerData = $customer->getDataModel();
						    $customerData->setCustomAttribute('customer_tema_id', $customers_TemaIDs[ key($customers_TemaIDs)]);
						    $customer->updateData($customerData);
						    $customer->save();
						    unset($customers_TemaIDs[ key($customers_TemaIDs)] );
						    continue;
						}
						
						
						$billingAddressId = $customer->getDefaultBilling();
						$billingAddress = $this->_addressFactory->create()->load($billingAddressId);

						$shippingAddressId = $customer->getDefaultShipping();
						$shippingAddress = $this->_addressFactory->create()->load($shippingAddressId);
						
						
						$customerdata=array(
						  "externalId" => $customer->getId(),
						  "vatId" => ( $billingAddress->getData('taxvat')?$billingAddress->getData('taxvat') : $customer->getData('taxvat') ),
						  "name" => ($shippingAddress->getCompany()?$shippingAddress->getCompany(): $customer->getName() ),
						  "address" => $billingAddress->getData('street'),
						  "postCode" => $billingAddress->getPostcode(),
						  "city" => $billingAddress->getCity(),
						  "country" => $this->_countryFactory->create()->load( $billingAddress->getCountryId() )->getName(),
						  "phoneNumber" => $billingAddress->getTelephone(),
						  "email" => $customer->getEmail(),
						  "isRetail" => true,
						  "isSubjectToGdpr" => true,
						  "creditLimit" => 0,
						  "contacts" => [
						      [
						      "externalId" => $billingAddress->getId(),
						      "firstName" => $billingAddress->getFirstname(),
						      "lastName" => $billingAddress->getLastname(),
						      "email" => $billingAddress->getEmail(),
						      "phoneNumber" => $billingAddress->getTelephone()
						      ]
						  ],
						  "shipmentAdresses" => [
							      [
							      "externalId" => $shippingAddress->getId(),
							      "name" =>  ($shippingAddress->getCompany()?$shippingAddress->getCompany(): $shippingAddress->getFirstname()." ".$shippingAddress->getLastname()),
							      "address" =>  $shippingAddress->getData('street'),
							      "postCode" => $shippingAddress->getPostcode(),
							      "city" =>  $shippingAddress->getCity(),
							      "country" =>  $this->_countryFactory->create()->load( $shippingAddress->getCountryId() )->getName(),
							      "phoneNumber" =>  $shippingAddress->getTelephone()
							      ]
							  ]
						);
						//									https://api-deli.ihurt.eu/api/erp/v1/warehouse
						
						if(!empty($billingAddress->getFirstname()) and !empty($billingAddress->getLastname()))
						{
							$response = \Httpful\Request::post($apiurl.'/api/erp/v1/contractors')
									->addHeader('WebApiKey', trim($apikey) )
									->body( $customerdata )
		                        	->sendsJson()
		                        	->send();
		                        	
			                if(!empty($response->body->contractorId) )
			                	{
			                		$customerData = $customer->getDataModel();
							        $customerData->setCustomAttribute('customer_tema_id', (string) $response->body->contractorId );
							        $customer->updateData($customerData);
							        $customer->save();
	        
	        
									//$customer->setData('tema_id',(string) $response->body->contractorId );
									//$this->objectManager->create('Magento\Customer\Api\CustomerRepositoryInterface')->save($customer);
								}else{
									//var_dump( $response );
									$customerData = $customer->getDataModel();
							        $customerData->setCustomAttribute('customer_tema_id', '-1'  );
							        $customer->updateData($customerData);
							        $customer->save();
							        if(!empty($response))
							        	{
							        		$logger->info( "\n ERROR (1): ".json_encode( $response )."\n" );
							               // var_dump( $response );
											//die();
											return $this;
										}
											
								}
						//has billing address
						}else{
							$logger->info( "\n SKIP CUSTOMER No BILL ADDRESS: ".json_encode( $customer->getId() )."\n" );
							            
						}
                        
					}

			$customers_TemaIDs_old = $this->_ScopeConfig->getValue('temaerp/general/customers_TemaIDs');
			$customers_TemaIDs_old = explode( ',' , $customers_TemaIDs_old);
			if(count($customers_TemaIDs_old) != count($customers_TemaIDs) )					
				{
					//$this->_configWriter->save('temaerp/general/customers_TemaIDs', implode(',',$customers_TemaIDs)  , 'default', 0 );
					$this->_configInterface
    						->saveConfig('temaerp/general/customers_TemaIDs', implode(',',$customers_TemaIDs) , 'default', 0);
    
					    try{
						    $_cacheTypeList = $this->objectManager->create('Magento\Framework\App\Cache\TypeListInterface');
						    $_cacheFrontendPool = $this->objectManager->create('Magento\Framework\App\Cache\Frontend\Pool');
						    $types = array('config');
						    foreach ($types as $type) {
						        $_cacheTypeList->cleanType($type);
						    }
						    foreach ($_cacheFrontendPool as $cacheFrontend) {
						        $cacheFrontend->getBackend()->clean();
						    }
						}catch(Exception $e){
						    echo $msg = 'Error : '.$e->getMessage();
						}

					echo "GET ID customer ".implode(',',$customers_TemaIDs)."\n";
				}
		}
		
		/* end synch customers */
		//die();
		
		/* start synch orders */
		$orders_collection = $this->getOrderCollection()
										->addFieldToFilter('TEMA_ID', [ array('null' => true), array('eq' => '')] )
										->setPageSize($order_per_minute)
										->setOrder('created_at','DESC')
										->setCurPage(1);
		
		
		if($orders_collection->getSize()){
			
			$customerRepositoryInterface =  $this->objectManager->create('\Magento\Customer\Api\CustomerRepositoryInterface');
			$tema_erp_customer_id=null;
			foreach($orders_collection->getItems() as $order)
					{
						$code_to_product_id=[];
						$shipping_map = $this->_ScopeConfig->getValue('temaerp/general/shipping_products');
						$conf_map =  json_decode($shipping_map);
						if(!empty($conf_map))
							foreach(json_decode($shipping_map) as $sh)
								$code_to_product_id[ $sh->s_code ] = $sh->t_code;
								
						
						
						$is_quote_address = false;
						$logger->info( "START ID " . $order->getId()." -> " .$order->getIncrementId() );
						
						$customer_id = $order->getCustomerId();
						if(!empty($customer_id))
							$customerRep = $customerRepositoryInterface->getById( $customer_id );
							else
							{
								$order->setData('TEMA_ID', '-1:NO_CUSTOMER_ID' );
								$order->save();
								$logger->info( "\n ERROR (2): ORDER WITHOUT CUSTOMER ORDER ID: ".$order->getIncrementId() ); 
								continue;
							}
								
						$tema_erp_customer_id=null;
						
						if( $customer_tema_attr = $customerRep->getCustomAttribute('customer_tema_id') )
							{
								if($customer_tema_attr=='-1')
									continue;
									else
									$tema_erp_customer_id = $customer_tema_attr->getValue();
							}else{
								$logger->info( "\n SKIP ORDER ".$order->getId()." EMPTY TEMA ID " ); 
								continue;
							}				
						
						if(empty($tema_erp_customer_id))
							{
								$order->setData('TEMA_ID', '-1' );
								$order->save();
								$logger->info( "\n ERROR (3): EMPTY CUSSTOMER ERP ID ON ORDER CREATE" ); 
								continue;
							}
							
						$logger->info( "\n INFO: L:208 TEMA customerID:".$tema_erp_customer_id." : M2 Customer ID - ".$customer_id ); 
						
							
						$shippingAddress = $order->getShippingAddress();
						
						
						$AddressRepositoryInterface = $this->objectManager->create('\Magento\Customer\Model\Address');//Api\AddressRepositoryInterface');
						unset($shippingAddress_obj);
						if(!empty($shippingAddress->getData("customer_address_id")) or !empty($shippingAddress->getData("parent_id")))
							{
								if(!empty($shippingAddress->getData("customer_address_id")) )
									$shippingAddress_obj = $AddressRepositoryInterface->load($shippingAddress->getData("customer_address_id"));
								else
								{
									
									if($shippingAddressId = $customer->getDefaultShipping())
										$shippingAddress_obj = $AddressRepositoryInterface->load( $shippingAddressId );
								}
								
								
							}
							
							if(empty($shippingAddress_obj))
							{
								if(!empty($shippingAddress->getData("quote_address_id"))){
									$shippingAddress_obj = $shippingAddress;
									$is_quote_address = true;
								}else{
									$order->setData('TEMA_ID', '-1' );
								 	$order->save();
									$logger->info( "\n ERROR (4): EMPTY ATTRIBUTE customer_address_id L:226 " ); 
									continue;
								}
								
							}
					
					
						if(empty($shippingAddress_obj) or $shippingAddress_obj->getData('tema_id')=='-1' ){
								$logger->info( "\n ERROR (5) [".date('Y-m-d H:I:s')."]: EMPTY ADDRESS OR TEMA RESPOND ERROR " ); 
								continue;
							}
						
						
						/*
						echo 	"customer id: ".$tema_erp_customer_id."adres id: ".$shippingAddress->getData("customer_address_id") ." ";
						echo "\n ADDRESS tema_id: ".$shippingAddress_obj->getData('tema_id')."\n"; 
						var_dump( $shippingAddress_obj->getData() );
						echo "URL: ".$apiurl.'/api/erp/v1/contractors/'.$tema_erp_customer_id."\n";
						
						return;
						*/
							$response = \Httpful\Request::get($apiurl.'/api/erp/v1/contractors/'.$tema_erp_customer_id)
									->addHeader('WebApiKey', trim($apikey) )
		                        	->send();
		                   // echo "\n\n";
		                    
		                    //var_dump( $response->body);
		                    
						if(  !empty( $shippingAddress_obj->getData('tema_id') )  )
							{
								$tema_shipmentAddressId = $shippingAddress_obj->getData('tema_id');
								$logger->info( "\n L:239  [".date('Y-m-d H:I:s')."]: SHIPPING ERP ID: ".$tema_shipmentAddressId); 
							
								$response = \Httpful\Request::get($apiurl.'/api/erp/v1/contractors/'.$tema_erp_customer_id)
									->addHeader('WebApiKey', trim($apikey) )
		                        	->send();
		                        
		                        	
							}else{
								$customerdata=array(
										  "externalId" => $customerRep->getId(),
										  "name" => ($shippingAddress->getCompany()?$shippingAddress->getCompany(): $customerRep->getFirstname()." ".$customerRep->getLastname() ),
										  'contacts'	=> [],
										  "shipmentAdresses" => [
											      [
											      "externalId" => $shippingAddress->getId(),
											      "name" =>  ($shippingAddress->getCompany()?$shippingAddress->getCompany(): $shippingAddress->getFirstname()." ".$shippingAddress->getLastname()),
											      "address" =>  str_replace("\n",' ',$shippingAddress->getData('street') ),
											      "postCode" => $shippingAddress->getPostcode(),
											      "city" =>  $shippingAddress->getCity(),
											      "country" =>  $this->_countryFactory->create()->load( $shippingAddress->getCountryId() )->getName(),
											      "phoneNumber" =>  $shippingAddress->getTelephone()
											      ]
											  ]
										);
						
							/*
							$response = \Httpful\Request::get($apiurl.'/api/erp/v1/contractors/002697')
									->addHeader('WebApiKey', trim($apikey) )
		                        	->sendsJson()
		                        	->send();
		                    */    
		                       
								
								//var_dump($customerdata );
								$response = \Httpful\Request::post($apiurl.'/api/erp/v1/contractors')
									->addHeader('WebApiKey', trim($apikey) )
									->body( $customerdata )
		                        	->sendsJson()
		                        	->send();
								 
		                        if( !empty($response->body->shipmentAddressesMaps))
		                        	{
		                        		//var_dump( $response->body->shipmentAddressesMaps );
		                        		foreach( $response->body->shipmentAddressesMaps as $shipmentAddressesMaps ) {
		                        			if($is_quote_address==false)
												{
													try{
														$shippingAddress_obj->setCustomAttribute('tema_id', $shipmentAddressesMaps->erpId ); 
														$AddressRepositoryInterface->save($shippingAddress_obj);	
													}catch( \Magento\Framework\Exception\InputException $e ){
														$logger->info( "\n INFO [".date('Y-m-d H:I:s')."]: Insert ERP ID to customer error :". $e->getMessage()  ); 
														continue;
													}
												}
											}
											$tema_shipmentAddressId = $shipmentAddressesMaps->erpId;
											//$tema_shipmentAddressId = $shipmentAddressesMaps->externalId ;
											$logger->info( "\n INFO [".date('Y-m-d H:I:s')."]: ADD address ERP ID:". $shipmentAddressesMaps->erpId ); 
											//var_dump( $response->body );
						
									}else{
										$order->setData('TEMA_ID', '-1' );
								 		$order->save();
										$logger->info( "\n ERROR (6) [".date('Y-m-d H:I:s')."]: Address response add invalid:". json_encode($response) ); 
										//var_dump( $response );
										//die();
										return $this;
									}
							}	
						
						$order_items=[];
						if($order->getAllVisibleItems())
							foreach($order->getAllVisibleItems() as $item)
								{
									$order_items[]=[
											'type'		=> "product",
											'productId'	=> $item->getSku(),// TEMA product ID
											'quantity'	=> $item->getData("qty_ordered"), // TEMA product ID
											'netPrice'	=> $item->getPrice(),
											'grossPrice'=> $item->getPriceInclTax()
										];
								}
							if(!empty($code_to_product_id [ $order->getShippingMethod() ]))
								{
// [TO DO] pobrać cene wysylki z tema
									
									$order_items[]=[
											'type'		=> "product",
											'productId'	=> $code_to_product_id [ $order->getShippingMethod() ],// TEMA product ID
											'quantity'	=> 1
										];
								}
								
						$payment = $order->getPayment();
						$method = $payment->getMethodInstance();
						$methodTitle = $method->getTitle();
						
						$deliverydate = $order->getDeliveryDate();
						if($deliverydate=='0000-00-00 00:00:00')
							$deliverydate = $order->getData('created_at');

						$new_orderdata=[
							'id'				=> $order->getId(),
							'name'				=> $order->getIncrementId(),
							'clientId'			=> $tema_erp_customer_id,
						//	'shipmentAddressId'	=> $tema_shipmentAddressId,
							'orderDate'			=> $order->getCreatedAt(),
							'paymentMethodId'	=> 'paymentOperator',
							'paymentMethodName'	=> $methodTitle,
							'notes'				=> $order->getDeliveryComment(),
							'shipmentDate'		=> $deliverydate, 
							'items'				=> $order_items 
						];
					
						if(count($order_items)){
								
								$response = \Httpful\Request::post($apiurl.'/api/erp/v1/warehouse/'.$warehouseId.'/client-orders/orders')
										->addHeader('WebApiKey', trim($apikey) )
										->body( $new_orderdata )
			                        	->sendsJson()
			                        	->send();
		                    // var_dump(  $new_orderdata );
		                     	
				                 if(!empty($response->body->orderId) ){
								 	$order->setData('TEMA_ID', (string)$response->body->orderId );
								 	$order->save();
								 	$logger->info( "\n SUCCESS: ".json_encode($new_orderdata) );
								 	$logger->info( "\n SUCCESS RESPONSE: ".json_encode($response->body) );
								 }else{
								 		//$order->setData('TEMA_ID', '-1' );
								 		//$order->save();
								 		$out ='';
								 		//var_dump($response->body);
								 		if(!empty($response->body->errors))
								 		foreach($response->body->errors as $e)
										 	$out.= " ".$e->key." : ".implode("\n",$e->errors)." \n";
										
								        $logger->info( "\n ERROR (7): ".$out."\n json: [".json_encode($response->body).']' );														$order->setData('TEMA_ID', " -1: ERROR".$out);
								 	$order->save();
								 }
						}
						
		                
					}
		}
		
		return $this;

	}
}