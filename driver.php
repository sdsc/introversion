<?php

require_once('ivdb.php');

$db = new ivdb();

// we'll pull this from the environment when in production
$AUTHENTICATED_USER = 'someuser';


// test insrt of asset
//$aid = $db->insertAsset('asset name2', 'asset description', 'ssakai@sdsc.edu','IS');
//echo $aid;

// test update of asset
//$aid = $db->updateAsset($aid, 'new asset name2', 'asset description', 'ssakai@sdsc.edu', 'IS');
//echo $aid;

// test insert of asset attribute response
$r = $db->insertResponse(0,1,0);

$r = $db->getResponse(0,1);

$r = $db->insertResponse(0,1,1);

$r = $db->getResponse(0,1);

print_r($r);

?>
