<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    require __DIR__ . '/app/bootstrap.php';
} catch (\Exception $e) {
	echo $e->getMessage();
    exit(1);
}

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
$store = $storeManager->getStore();
$storeId = $store->getStoreId();

echo 'websiteId: '.$websiteId." ".'storeId: '.$storeId." \n";

$objectManager->get('Magento\Framework\Registry')->register('isSecureArea', true);

                
$dsn = 'mysql:dbname=ernabo;host=cypis.net.pl;charset=utf8';
$user = 'ernabo';
$password = 'ernabo1234a';

try {
    $db = new PDO($dsn, $user, $password);
} catch (PDOException $e) {
    echo 'Connection failed: ' . $e->getMessage();
}

$dsn = 'mysql:dbname=ernabo_newtpl;host=127.0.0.1;charset=utf8';
$user = 'ernabo_m2';
$password = 'NUFkAkm37';

try {
    $dbm = new PDO($dsn, $user, $password);
} catch (PDOException $e) {
    echo 'Connection failed: ' . $e->getMessage();
}

$productsArray=[];
$images=[];



$fix=[];
$sth = $dbm->prepare("SELECT * FROM catalog_product_entity_media_gallery");
$sth->execute();
while($i = $sth->fetch(PDO::FETCH_OBJ)){
	if(file_exists('pub/media/catalog/product'.$i->value))
		if(@getimagesize( 'pub/media/catalog/product'.$i->value ))
			echo '';
			else
			$fix[]= $i->value_id;
		else
		$fix[]= $i->value_id;
		
	
}

$sql = "delete from catalog_product_entity_media_gallery WHERE value_id IN (".implode(',',$fix).");";
echo $sql."\n";
$sql = "delete from catalog_product_entity_media_gallery_value WHERE value_id IN (".implode(',',$fix).");";
echo $sql."\n";
$sql = "delete from catalog_product_entity_media_gallery_value_to_entity WHERE value_id IN (".implode(',',$fix).");";
echo $sql."\n";



die('img');


$productRepository = $objectManager->create('Magento\Catalog\Api\ProductRepositoryInterface');
$productModel = $objectManager->get('\Magento\Catalog\Model\Product');
$imageProcessor = $objectManager->create('\Magento\Catalog\Model\Product\Gallery\Processor');


$sql="select cp.sku, mg.value AS image, ev.value AS name from catalog_product_entity_media_gallery mg
LEFT JOIN catalog_product_entity_media_gallery_value mgv ON mg.value_id=mgv.value_id
LEFT JOIN catalog_product_entity cp ON cp.entity_id=mgv.entity_id
LEFT JOIN catalog_product_entity_varchar ev ON ev.entity_id=cp.entity_id AND ev.attribute_id=73
GROUP BY mgv.value_id ORDER BY cp.sku ";
$sth = $db->prepare( $sql);
$sth->execute();
while($p = $sth->fetch(PDO::FETCH_OBJ)){
	echo $p->sku."\n";
	//$images[ $p->sku ][] = 'http://ernabo.cypis.net.pl/pub/media/catalog/product/'.$p->value;


$file_tmp = explode('/',$p->image );

	$productsArray[]=[
						'url_key'	=> '',
					   	'sku' => (string)$p->sku,
				        'attribute_set_code' => 'Default',
				        'product_type' => 'simple',
				        'product_websites' => 'base',
				        'name' => $p->name,
				        'product_websites' => 'base',
				        'base_image' => (!empty($p->image )? '/m/'.$file_tmp[ count($file_tmp)-1] :''),
				        'small_image' => (!empty($p->image )? '/m/'.$file_tmp[ count($file_tmp)-1] :''),
				        'thumbnail_image' => (!empty($p->image )? '/m/'.$file_tmp[ count($file_tmp)-1] :''),
				        'swatch_image' => (!empty($p->image )? '/m/'.$file_tmp[ count($file_tmp)-1]:''),
				        'additional_images'	=> [ '/m/'.$file_tmp[ count($file_tmp)-1] ]
					];

$imagePath = '/home/ernabo/domains/dev.afstatnet.stpl.net.pl/public_html/pub/media/catalog/product'.$p->image; // path of the image
$file_tmp = explode('/',$p->image );

	echo $imagePath."\n";
		$sku= trim(	(string)$p->sku );
		
$productRepository = $objectManager->create('Magento\Catalog\Api\ProductRepositoryInterface');
try {
    $product_rep = $productRepository->get($sku);
    echo "MAG ID: ".$product_rep->getId()."  SKU:".$p->sku."\n";
} catch (\Magento\Framework\Exception\NoSuchEntityException $e){
    $product_rep = false;
    continue;
}


			
		
		if( !file_exists("/home/ernabo/domains/delikont.pl/public_html/pub/media/import/".$sku."_".$file_tmp[ count($file_tmp)-1]))
			copy  ( $imagePath, "/home/ernabo/domains/delikont.pl/public_html/pub/media/import/".$sku."_".$file_tmp[ count($file_tmp)-1] );



		$product = $productModel->load($product_rep->getId() );
		if( $product->getId() ){
			$product->setStoreId(0);

			if( file_exists("/home/ernabo/domains/delikont.pl/public_html/pub/media/import/".$sku."_".$file_tmp[ count($file_tmp)-1])){
					echo "ADD to: ".$sku."\n";
					
					$filee = file_exists( dirname(__FILE__).'/pub/media/catalog/product/'.$product->getImage() );
					
					if($product->getImage() && $product->getImage() != 'no_selection' and $filee ) {
							echo " skip has image \n". 'http://delikont.pl/pub/media/catalog/product/'.$product->getImage()."\n" ;
						}else{
						
						$product->setMediaGallery (array('images'=>array (), 'values'=>array ()));
						$product->addImageToMediaGallery(
							"/home/ernabo/domains/delikont.pl/public_html/pub/media/import/".$sku."_".$file_tmp[ count($file_tmp)-1] , 
							array('image', 'small_image', 'thumbnail'), 
							true, 
							false);
							
						if( empty($product->getPrice())){
							$product->setPrice(0);
						}
						$product->save();

						$mediaGalleryEntriesTemp = [];
						$i = 1;
						foreach ($product->getMediaGalleryEntries() as $item) {
						    if ($i === 1) {
						        $item->setTypes(['image', 'small_image', 'thumbnail']);
						        $mediaGalleryEntriesTemp[] = $item;
						    }
						    
						    $i++;
						}
						$product->setMediaGalleryEntries($mediaGalleryEntriesTemp);
						$productRepository->save($product);
			
					}
				}else
					echo "SKIP NO FILE ".$p->sku."\n";
	
		}else echo "SKIP ".$p->sku."\n";
		
					
}
					

/*
$importerModel = $objectManager->create('FireGento\FastSimpleImport\Model\Importer');
$importerModel->setBehavior(Magento\ImportExport\Model\Import::BEHAVIOR_ADD_UPDATE);
$importerModel->setEntityCode('catalog_product');
try {
		$importerModel->processImport($productsArray);
	} catch (\Exception $e) {
		echo json_encode( $e->getMessage() );
	}

	echo "\n";
	print_r($importerModel->getLogTrace());
	echo "\n";
	print_r($importerModel->getErrorMessages());
	echo "\n";
	*/