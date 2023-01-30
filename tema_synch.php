<?php
$time_start = microtime(true); 
try {
    require __DIR__ . '/app/bootstrap.php';
} catch (\Exception $e) {
    echo <<<HTML
<div style="font:12px/1.35em arial, helvetica, sans-serif;">
    <div style="margin:0 0 25px 0; border-bottom:1px solid #ccc;">
        <h3 style="margin:0;font-size:1.7em;font-weight:normal;text-transform:none;text-align:left;color:#2f2f2f;">
        Autoload error</h3>
    </div>
    <p>{$e->getMessage()}</p>
</div>
HTML;
    exit(1);
}

// include('./httpful.phar');
$bootstrap = \Magento\Framework\App\Bootstrap::create(BP, $_SERVER);

$objectManager = $bootstrap->getObjectManager();
$url = \Magento\Framework\App\ObjectManager::getInstance();
$storeManager = $url->get('\Magento\Store\Model\StoreManagerInterface');
$mediaurl= $storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA);
$state = $objectManager->get('\Magento\Framework\App\State');
$state->setAreaCode('frontend');
$websiteId = $storeManager->getWebsite()->getWebsiteId();
/// Get Website ID
$websiteId = $storeManager->getWebsite()->getWebsiteId();
echo 'websiteId: '.$websiteId." ";
/// Get Store ID
$store = $storeManager->getStore();
$storeId = $store->getStoreId();
echo 'storeId: '.$storeId." ";
/// Get Root Category ID
$rootNodeId = $store->getRootCategoryId();
echo 'rootNodeId: '.$rootNodeId." <br>";

$dsn = 'mysql:dbname=ernabo_newtpl;host=localhost;charset=utf8';
$user = 'ernabo_m2';
$password = 'NUFkAkm37';

try {
    $db = new PDO($dsn, $user, $password);
} catch (PDOException $e) {
    echo 'Connection failed: ' . $e->getMessage();
}

$productFactory = $objectManager->create('\Magento\Catalog\Model\ProductFactory');
$ProductRepositoryInterface = $objectManager->create('\Magento\Catalog\Api\ProductRepositoryInterface');
$group  = $objectManager->create(\Magento\Customer\Model\Group::class);
$productObj = $objectManager->get('Magento\Catalog\Model\Product');
$collecionFactory = $objectManager->get('\Magento\Catalog\Model\ResourceModel\Category\CollectionFactory');

$all_cats=[];
$category_path=[];

$TEMA_ROOT_ID = 630;
$rootCat = $objectManager->get('Magento\Catalog\Model\Category');
$root_cat_info = $rootCat->load($TEMA_ROOT_ID);
$sth = $db->prepare( "select * FROM aaa_tema  ORDER BY e_id ");
$sth->execute();

while($i = $sth->fetch(PDO::FETCH_OBJ)){
	
	
	if( $group_tema=json_decode($i->group) ){
		$cat=[];
		foreach($group_tema as $k=>$v){
			if( substr($k,-4)=='Name' )
				if(strlen($v)>1)
					$cat[]=$v;
		}
		$all_cats[ implode('/', $cat ) ]=$cat;
	}
}
ksort($all_cats);
$manufacturer=[];
$tema_categories=[];

$sth = $db->prepare( "select manufacturer, group FROM aaa_tema GROUP BY manufacturer");
$sth->execute();
while($r = $sth->fetch(PDO::FETCH_OBJ)){
	$manufacturer[$r->manufacturer] =$r->manufacturer;
}

$sth = $db->prepare( "select `group` FROM aaa_tema ");
$sth->execute();
while($r = $sth->fetch(PDO::FETCH_OBJ)){
	//echo $r->group;
	unset($cat);
	$cat=[];
	if(!empty($r->group))
	if( $group_tema=json_decode($r->group) ){
		foreach($group_tema as $key=>$v){
			if( substr($key,-4)=='Name' )
				if(strlen($v)>1)
					$cat[]=$v;
		}
	}
	$tema_categories [ implode( '/',  $cat ) ] = $cat;
}
	
if(empty($argv[1])){
/* import tema */
$db->exec('TRUNCATE TABLE aaa_tema;');


/* START stock qty */
$stock=array();
$page=0;	
$ts='';
$fetchNext=true;
while($page<200 and $fetchNext==true){
	$response = \Httpful\Request::get('https://delikont.ihurt.pl/api/erp/v1/warehouse/01/stock?ts='.$ts )->addHeader('WebApiKey', '44ad6fbb-f20b-473a-9086-8bba8f13dc40')->send();
	
	$fetchNext=$response->body->fetchNext;
	$ts = urlencode( trim($response->body->lastTimestamp) );
	
	echo 'https://delikont.ihurt.pl/api/erp/v1/warehouse/01/stock?ts='.$ts."\n";
	foreach($response->body->items as $i){
		$stock[ $i->productId ] = (int) $i->totalQuantity;
		}
	}
/* END stock qty */

$page=0;	
$ts='';
$fetchNext=true;
while($page<200 and $fetchNext==true){

$response = \Httpful\Request::get('https://delikont.ihurt.pl/api/erp/v1/warehouse/01/dictionaries/products?ts='.$ts )->addHeader('WebApiKey', '44ad6fbb-f20b-473a-9086-8bba8f13dc40')->send();

$ts = urlencode( trim($response->body->lastTimestamp) );
echo 'https://delikont.ihurt.pl/api/erp/v1/warehouse/01/dictionaries/products?ts='.$ts ."\n";
$fetchNext=$response->body->fetchNext;
	
	

echo $page.":  ".$ts." -> ".json_encode($fetchNext)." \n Items=".count($response->body->items)."<br>";
foreach($response->body->items as $i){
		$sql="INSERT INTO `aaa_tema` (`id`, `updatetime`, `rowId`, `e_id`, `name`, `unit`, `barcode`, `prices`, `activity`, `category`, `manufacturer`, `group`, `vatClassification`, `purchaseVatRate`, `salesVatRate`, `carteQuantity`, `palleteQuantity`, `layerQuantity`, `boxQuantity`, `netWeight`, `grossWeight`, `expiryDays`, `internetVisibility`, `quantityLimits`,`descriptions`,`stock_qty`, `debug`) VALUES (null, now(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?);
	";
		$ins = $db->prepare($sql); 
		$ins->execute( array(
					$i->rowId,
					$i->id,
					$i->name,
					$i->unit->name,
					$i->barCode,
					json_encode( $i->prices ),
					$i->activity,
					$i->category,
					$i->manufacturer->name,
					(!empty($i->group)? json_encode( $i->group ):''),
					$i->vatClassification,
					json_encode( $i->purchaseVatRate ),
					$i->salesVatRate->value,
					$i->carteQuantity,
					$i->palleteQuantity,
					$i->layerQuantity,
					$i->boxQuantity,
					$i->netWeight,
					$i->grossWeight,
					$i->expiryDays,
					$i->internetVisibility,
					$i->quantityLimits,
					json_encode( $i->descriptions ),
					(!empty($stock[$i->id])?$stock[$i->id]:0),
					json_encode($i)
				));
		
	
	}/* page elements */
	$page++;
	sleep(5);
}



/* import tema */
} 

if(empty($argv[1])){
/* Adding manufacturer */

$attribute = $objectManager->create('\Magento\Eav\Model\Config')->getAttribute('catalog_product', 'producent');
$options = $attribute->getSource()->getAllOptions();

$current_options=[];
foreach( $options as $o)
	{
		$current_options[ $o['value'] ] = strtolower( $o['label'] ); 
		$current_options_by_label[ $o['label'] ] = $o['value']; 
	}

$optionLabelFactory = $objectManager->create('\Magento\Eav\Api\Data\AttributeOptionLabelInterfaceFactory');
$optionFactory=$objectManager->create('\Magento\Eav\Api\Data\AttributeOptionInterfaceFactory');
$attributeOptionManagement=$objectManager->create('\Magento\Eav\Api\AttributeOptionManagementInterface');
					            
 
foreach($manufacturer as $k=>$m){
	if(!in_array( strtolower( $m ) , $current_options)){
			$optionLabel = $optionLabelFactory->create();
			$optionLabel->setStoreId(0);
			$optionLabel->setLabel($m);
			$optionLabel1 = $optionLabelFactory->create();
			$optionLabel1->setStoreId(23);
			$optionLabel1->setLabel($m);
			            
			$optioninsert = $optionFactory->create();
			$optioninsert->setLabel( $line[0] );
			$optioninsert->setStoreLabels( [$optionLabel, $optionLabel1] );
			$optioninsert->setSortOrder(0);
			$optioninsert->setIsDefault(false);

			$attributeOptionManagement->add(
			   'catalog_product',
			   'producent',
			   $optioninsert
			);
	}
}

$options = $attribute->getSource()->getAllOptions();
$current_options=[];
foreach( $options as $o)
	{
		$current_options[ $o['value'] ] = $o['label']; 
		$current_options_by_label[ $o['label'] ] = $o['value']; 
	}
/* manufacturer */
}// disable if arg

/* Add UPDATE PRODUCTS */

$k=0;


	
foreach($all_cats as $k=>$a){
	$parent_ID = $TEMA_ROOT_ID;
	foreach($a as $el){
	//	echo $TEMA_ROOT_ID." -> ".$parent_ID." \n";
		$exist=false;
		$cat = $el;
		$name=ucfirst($cat);
					
		$collection= $collecionFactory
             ->create()
             ->addAttributeToFilter('name',$name)
              ->addAttributeToFilter('parent_id',['eq'=> $parent_ID ])
              ->setPageSize(1);
              
				if ($collection->getSize()) {
				     $exist = $collection->getFirstItem()->getId();
				     $parent_ID = $collection->getFirstItem()->getId();
				     $all_cats[$k]['mag_id']= $parent_ID;
				}else{
					
					$cat = $el;
					$name=ucfirst($cat);
					$url=strtolower($cat);
					$cleanurl = trim(preg_replace('/ +/', '', preg_replace('/[^A-Za-z0-9 ]/', '', urldecode(html_entity_decode(strip_tags($url))))));
					$categoryFactory=$objectManager->get('\Magento\Catalog\Model\CategoryFactory');
					/// Add a new sub category under root category
					$categoryTmp = $objectManager->get('\Magento\Catalog\Model\CategoryFactory')->create();

					$categoryTmp->setName($name);
					$categoryTmp->setIsActive(true);
					$categoryTmp->setUrlKey($cleanurl);
					$categoryTmp->setData('description', 'description');
					$categoryTmp->setParentId( $parent_ID );
					$mediaAttribute = array ('image', 'small_image', 'thumbnail');
					$categoryTmp->setImage('/m2.png', $mediaAttribute, true, false);// Path pub/meida/catalog/category/m2.png
					$categoryTmp->setStoreId(0);
						
						$par_path= $objectManager->get('Magento\Catalog\Model\Category')->load( $parent_ID );
					//$categoryTmp->setPath( $par_path->getPath() );
					try{
						$objectManager->get('\Magento\Catalog\Api\CategoryRepositoryInterface')->save($categoryTmp);
					$parent_ID = $categoryTmp->getId();
					$all_cats[$k]['mag_id']=$categoryTmp->getId();
					}catch(Exception $e){
						echo $e->getMessage();
						die('kat');
					}
					
				}
	}

		
	//echo implode('/', $a )."\n";
}


//echo "Upd \n";
$k=0;

$sth = $db->prepare( "select COUNT(*) ile FROM aaa_tema ");
$sth->execute();
$t = $sth->fetch(PDO::FETCH_OBJ);
$all_cnt = $t->ile;

$licz=0;
$sth = $db->prepare( "select * FROM aaa_tema ORDER BY e_id ");
$sth->execute();
while($i = $sth->fetch(PDO::FETCH_OBJ)){
	
	if(!empty( $argv[1]) and $argv[1]!='nosynch') 
		if( $argv[1] != $i->barcode )
			continue;
			
	$k++;
	unset( $_product );
	unset( $product );
	//echo "i status: ".$i->internetVisibility." \n";
	
	$id = $productObj->getIdBySku(trim((string)$i->e_id) );
	
	if( $i->internetVisibility!=1 ){
			echo "\n SKIP UPD: ".$i->name." | ".$id." | ".trim((string)$i->e_id)."\n";
			if($id){				
				//$product = $ProductRepositoryInterface->getById($id);
				$product = $productObj->load($id);
				$product->setStoreId(0);
			    $product->setStatus(\Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_DISABLED);
			    $product->setVisibility(\Magento\Catalog\Model\Product\Visibility::VISIBILITY_NOT_VISIBLE);
			    $product->save();
			    //$ProductRepositoryInterface->save($product);
			   // echo "upd status \n";
				}
			continue;
		}
		if(!$id)
			$type='add';
			else
			$type='update';
			
			
		if(!$id){
					$_product = $objectManager->create('Magento\Catalog\Model\Product');
					$_product->setName($i->name);
					$_product->setStoreId(0);
					$_product->setTypeId('simple');
					$_product->setAttributeSetId(4);
					$_product->setSku( $i->e_id );
					$_product->setWebsiteIds(array(1));
					$_product->setVisibility(4);
					$_product->setPrice(array(1));
					$_product->setStockData(array(
					        'use_config_manage_stock' => 0, //'Use config settings' checkbox
					        'manage_stock' => 1, //manage stock
					        'min_sale_qty' => 1, //Minimum Qty Allowed in Shopping Cart
					        'max_sale_qty' => 1000, //Maximum Qty Allowed in Shopping Cart
					        'is_in_stock' => 1, //Stock Availability
					        'qty' => $i->stock_qty //qty
					        )
					    );
					    try {
							$_product->save();
							} catch (Exception $e) {
				    				echo 'Caught exception: ',  $e->getMessage(), "\n";
				    				echo "\n\n";
							}
				
				$product = $productObj->load( $_product->getId() );
			}else{
				$product = $productObj->load($id);
				$product->setStoreId(0);
				$product->setVisibility(4);
				$product->setStatus(\Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED);
			   	$product->setVisibility(\Magento\Catalog\Model\Product\Visibility::VISIBILITY_BOTH);
				$product->setStockData(array(
				        'use_config_manage_stock' => 0, //'Use config settings' checkbox
				        'manage_stock' => 1, //manage stock
				        'min_sale_qty' => 1, //Minimum Qty Allowed in Shopping Cart
				        'max_sale_qty' => 1000, //Maximum Qty Allowed in Shopping Cart
				        'is_in_stock' => 1, //Stock Availability
				        'qty' => $i->stock_qty //qty
				        )
				    );
			}
			
			$tierPrice=[];

			$_sourceItemsSaveInterface = $objectManager->get('\Magento\InventoryApi\Api\SourceItemsSaveInterface');
			$_sourceItemFactory = $objectManager->get('Magento\InventoryApi\Api\Data\SourceItemInterfaceFactory');
			$sourceItem = $_sourceItemFactory->create();
			$sourceItem->setSourceCode('default'); // default : stock source
			$sourceItem->setSku($i->e_id );
			$sourceItem->setQuantity( $i->stock_qty  );
			$sourceItem->setStatus(1);
			$_sourceItemsSaveInterface->execute([$sourceItem]);


			foreach(json_decode($i->prices) as $p)
				{
				if($p->priceKind=='')
					{
						$vat_val=1.23;
						if($i->salesVatRate==8)
							$vat_val=1.08;
						if($i->salesVatRate==5)
							$vat_val=1.05;
						if($i->salesVatRate==0)
							$vat_val=1;
						
						
						$price = $p->price /  $vat_val;
						$product->setPrice( $price );
					}
					else
					if( $p->price>0)
						{
							$vat_val=1.23;
								if($i->salesVatRate==8)
									$vat_val=1.08;
								if($i->salesVatRate==5)
									$vat_val=1.05;
								if($i->salesVatRate==0)
									$vat_val=1;
									
								$price = $p->price / $vat_val;
								
							$tierPrice[] = array('website_id' => 0,'cust_group' => $group->load( $p->priceKind ,'customer_group_code')->getId(), 'price_qty' => 1 , 'price' => $price );
						}
       
				}
			
			if(count($tierPrice))
				$product->setTierPrice( $tierPrice );
				 
			$vat=2;
				if($i->salesVatRate==8)
					$vat=7;
				if($i->salesVatRate==5)
					$vat=5;
				if($i->salesVatRate==0)
					$vat=0;
			
			$product->setTaxClassId($vat);
			if(strlen( $product->getName() )<3 )
			 {
			 	$product->setName($i->name);
				$db->exec("DELETE FROM url_rewrite WHERE entity_type='product' AND entity_id=".$product->getId() );
			}
			
			if($type=='add'){
				$product->setData('meta_description',$i->name);
					$product->getResource()->saveAttribute($product, 'meta_description');	
				$product->setData('meta_keyword',$i->name);
					$product->getResource()->saveAttribute($product, 'meta_keyword');	
				$product->setData('meta_title',$i->name);
					$product->getResource()->saveAttribute($product, 'meta_title');	
				if(!empty($current_options_by_label[$i->manufacturer]))
				{
					$product->setData('producent', $current_options_by_label[$i->manufacturer] );
						$product->getResource()->saveAttribute($product, 'producent');
				}
				$product->setData("cartequantity", $i->carteQuantity );
					$product->getResource()->saveAttribute($product, 'cartequantity');
				$product->setData("ean", $i->barcode);
					$product->getResource()->saveAttribute($product, 'ean');
	        	$product->setData("palletequantity", $i->palleteQuantity );
	        		$product->getResource()->saveAttribute($product, 'palletequantity');
	            $product->setData("layerquantity", $i->layerQuantity );
	            	$product->getResource()->saveAttribute($product, 'layerquantity');
	        	$product->setData("boxquantity", $i->boxQuantity );
	        		$product->getResource()->saveAttribute($product, 'boxquantity');
	        		
			}
			
        $price_for_kg =0;
      /*  if( $i->netWeight >0)
        {
        	$product->setData("weight" , ($i->netWeight/1000) );
			$price_for_kg =  $product->getPrice()/($i->netWeight / 1000);
			if($price_for_kg!= $product->getData("price_for_kg") ){
		        $product->setData("price_for_kg", $price_for_kg );
		        	$product->getResource()->saveAttribute($product, 'price_for_kg');
			}
		}
		
          */
          		
    	if(!empty($current_options_by_label[$i->manufacturer]))
				{
					$product->setData('producent', $current_options_by_label[$i->manufacturer] );
						$product->getResource()->saveAttribute($product, 'producent');
				}
        
        try {
        	$product->setCategoryIds([]);
        	$product->save();
        	if($type=='add')
				$ProductRepositoryInterface->save($product);
			
			} catch (Exception $e) {
				
    				echo "\n".'Caught exception: ',  $e->getMessage(), "\n s id:".$id." \n ";
    				echo json_encode( $product->getData()  );
    				echo $product->getId() . "\n";
    				echo $product->getData('url_key') . "\n";
echo '<pre>';
print_r($e->getTraceAsString()); 
    				die();
    				
			}
	echo "\n".++$licz." / $k [ ".round( ($licz/$all_cnt)*100 )."% from ".$all_cnt." ] $type";
	echo "[$id]: ".$i->name.": [". $i->e_id ."] ";
	$time_end = microtime(true);
	$time= ($time_end - $time_start);
    echo " Price ". number_format( $product->getPrice(),2 )." FOR KG ". number_format($price_for_kg,2)." W:".$i->netWeight ." T: ".number_format($time,2)."s";
        
	unset($cat);
	$cat=[];
	if(!empty($i->group))
	if( $group_tema=json_decode($i->group) ){
		
		foreach($group_tema as $key=>$v){
			if( substr($key,-4)=='Name' )
				if(strlen($v)>1)
					$cat[]=$v;
		}
	}
//	echo "\n".$i->group."\n";
	//if($type=='add')
	if(!empty($cat) and count($cat)>0)	
	{
		echo "\n CATEGORY: ".implode('/', $cat )."\n";
		if(!empty( $all_cats[ implode('/', $cat ) ]['mag_id'] ) )
			{
				$db->exec("DELETE FROM  `catalog_category_product` WHERE `product_id`= ".$product->getId()."; ");
				$mag_id = $all_cats[ implode('/', $cat ) ]['mag_id'];
				$sthx = $db->prepare( "select COUNT(*) as ile FROM catalog_category_product WHERE category_id=? AND product_id=?");
				$sthx->execute( array($mag_id,$product->getId() ) );
				if($sthx->fetch(PDO::FETCH_OBJ)->ile==0){
					$db->exec("INSERT INTO `catalog_category_product` (`entity_id`, `category_id`, `product_id`, `position`) VALUES (null, ".$mag_id.", ".$product->getId().", 0)");
					}
			}
	}else{
		echo "DELETE FROM  `catalog_category_product` WHERE `product_id`= ".$product->getId()."; ";
		$db->exec("DELETE FROM  `catalog_category_product` WHERE `product_id`= ".$product->getId()."; ");
	}
			
}/* end foreach prodyct from tmp db */
$lp=0;

if(empty( $argv[1]) ){
	/* clean product not exists in tema */
$sth = $db->prepare( "SELECT p.entity_id, p.sku FROM catalog_product_entity p LEFT JOIN  aaa_tema t ON p.sku = t.e_id WHERE t.e_id IS NULL");
$sth->execute();
while($i = $sth->fetch(PDO::FETCH_OBJ)){
	
		$id = $productObj->getIdBySku(trim((string)$i->sku) );
		if($id){				
		echo "\n [".$lp."] CLEAN: ".(string)$i->sku;
				$product = $productObj->load($id);
				$product->setStoreId(0);
			    $product->setStatus(\Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_DISABLED);
			    $product->setVisibility(\Magento\Catalog\Model\Product\Visibility::VISIBILITY_NOT_VISIBLE);
			    
			    $product->setStockData(array(
				        'use_config_manage_stock' => 0, //'Use config settings' checkbox
				        'manage_stock' => 1, //manage stock
				        'min_sale_qty' => 1, //Minimum Qty Allowed in Shopping Cart
				        'max_sale_qty' => 1000, //Maximum Qty Allowed in Shopping Cart
				        'is_in_stock' => 0, //Stock Availability
				        'qty' => 0//qty
				        )
				    ); 
			    $product->save();
				}
				
	}
$db->exec( "DELETE FROM catalog_product_entity_int WHERE store_id<>0;");
$db->exec( "DELETE FROM catalog_product_entity_text WHERE store_id<>0;");
$db->exec( "DELETE FROM catalog_product_entity_varchar WHERE store_id<>0;");
$db->exec( "TRUNCATE TABLE inventory_reservation;");
}

/* clean product not exists in tema */
/*
1. kiedy w temie jest status (N) Nie - to wtedy w ogóle produktu nie powinno dać się znaleźć na stronie, tzn. że powinno wyświetlić informację "poszukiwany produkt nie istnieje", jeśli jest status (T) Tak - to wtedy produkt za każdym razem powinien być widoczny na stronie bez znaczenia czy jest na stanie czy go nie ma. Czyli jeśli mamy zerowy stan magazynowy - wyświetlamy informację zamiast "brak na magazynie" to "Produkt chwilowo niedostępny" zaś jeśli jest normalnie na stanie wyświetlamy standardowo. 
*/

