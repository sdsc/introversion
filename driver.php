<?php

require_once('ivdb.php');

$db = new ivdb();

// we'll pull this from the environment when in production
$AUTHENTICATED_USER = 'someuser';


// test insrt of asset
//$aid = $db->insertAsset('asset name4', 'asset description', 'ssakai@sdsc.edu','IS');
//echo $aid;

// test update of asset
$aid = $db->updateAsset(3, 'new asset name', 'asset description', 'ssakai@sdsc.edu', 'IS');
echo $aid;


?>
