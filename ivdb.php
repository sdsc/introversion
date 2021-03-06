<?php
/** ivdb.php 
  * database routines for introversion 
  * 
  * Created by Scott Sakai (ssakai@sdsc.edu)
  *
  * June 2016
  */

// Change this to the location of the sqlite database, created with 
// schema_sqlite.txt.
// ---> PLACE THIS FILE OUTSIDE OF YOUR DOCUMENT ROOT <---
define("IVDB_DB_FILE", "/var/tmp/introversion.sqlite");

class ivdb 
{

    private $dbh;


    /* "connect" to an sqlite database 
       We'll die() if this fails, since there's really nothing else to do.
     */
    function __construct($dbfile = IVDB_DB_FILE)
    {
	try
	{
	    $this->dbh = new PDO("sqlite:$dbfile", '', '');
	    $this->dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	} catch(PDOException $e)
	{
	    die("Unable to connect to database: " . $e->getMessage());
	}
    }


	
    /* assets - these are things we want to protect
     *  right now we have:
     *    information system (host or cluster of hosts that process information)
     *    information asset (data sets)
     */

    /* add an asset to the database.  
     * This function assumes that the caller has otherwise sanitized the
     * arguments such that the insert will succeeed.  
     * This function will die() if the insert fails.
     *
     * The caller must perform any sanity checks necessary to establish
     * data consistency.
     *
     * An asset_id of -1 (or not present in arg list) means to assign the next
     * available asset ID number.
     *
     * If the caller provides an asset_id > -1, then it is assumed that a db 
     * transaction is already started and another one will not be started.
     *
     * Returns the asset ID.
     */
    function insertAsset($name, $description, $contact_emails, $asset_type, $asset_id = -1)
    {
	global $AUTHENTICATED_USER;

	if( $asset_id === -1 )
	{
	    $q = "INSERT INTO assets (name, description, contact_emails, asset_type,
	      entered_by, asset_id) 
	      values(:name, :description, :contact_emails, :asset_type, 
	      :entered_by, (select case when max(asset_id) isnull then 0 else max(asset_id) + 1 end from assets))";	
	} else
	{
	    $q = "INSERT INTO assets (name, description, contact_emails, asset_type,
	      entered_by, asset_id) 
	      values(:name, :description, :contact_emails, :asset_type, 
	      :entered_by, :asset_id )";	
	}

	try
	{
	    if( $asset_id === -1 ) $this->dbh->beginTransaction();
	    $sth = $this->dbh->prepare($q);
	    $sth->bindParam(':name', $name);
	    $sth->bindParam(':description', $description);
	    $sth->bindParam(':contact_emails', $contact_emails);
	    $sth->bindParam(':asset_type', $asset_type);
	    $sth->bindParam(':entered_by', $AUTHENTICATED_USER);
	    if( $asset_id !== -1 ) $sth->bindParam(':asset_id', $asset_id);	
	    $sth->execute();

	    // need the asset id if we added a new one
	    if( $asset_id === -1 ) $res = $this->dbh->query("SELECT max(asset_id) from assets");
	} catch(Exception $e)
	{
	    // weesa gonna die, never returns to caller
	    $this->dbh->rollBack();
	    die("insertAsset() - failed to add asset: " . $e->getMessage());
	}

	// if adding a new asset, we need to return the newly-added asset id
	// commit after getting the rowid, to avoid race conditions
	if( $asset_id === -1 )
	{
	    $this->dbh->commit();
	    // guess you can also do fetchrow
	    foreach($res as $row)
	    {
		return($row[0]);
	    }
	}

	// otherwise use the provided asset id (because max(asset_id) isn't the
	// asset id we added.
	return($asset_id);

    }



    /* modify an asset in the database
     *
     * we don't actually update an existing row. we expire it and add a 
     * new one with the same asset_id.  this allows us to keep a
     * history.
     *
     * the caller must perform all sanitization and sanity checks to
     * ensure that the new data is consistent and safe to insert.
     * this function will die() if there's a problem.
     *
     * returns asset_id
     */
    function updateAsset($asset_id, $name, $description, $contact_emails, $asset_type)
    {
	global $AUTHENTICATED_USER;

	// try to do everything as an atomic operation
	try
	{
	    $this->dbh->beginTransaction();
	} catch(Exception $e)
	{
	    die("updateAsset() - failed to begin transaction: " . $e->getMessage());
	}

	// expire the old row
	$q = "UPDATE assets set superseded_on = (strftime('%s', 'now')), 
	      superseded_by = :entered_by where asset_id = :asset_id and
	      superseded_on = 0 ";
	try 
	{
	    $sth = $this->dbh->prepare($q);
	    $sth->bindParam(':entered_by', $AUTHENTICATED_USER);
	    $sth->bindParam(':asset_id',   $asset_id);
	    $sth->execute();
	    if( $sth->rowCount() != 1 )
	    {
		throw new Exception("Wrong number of rows affected. Expected 1, got: " . $sth->rowCount());
	    }
	} catch(Exception $e)
	{
	    $this->dbh->rollBack();
	    die("updateAsset() - failed to expire old asset: " . $e->getMessage());
	}

	// insert new row
	$res = $this->insertAsset($name, $description, $contact_emails, $asset_type, $asset_id);

	$this->dbh->commit();
	
	return $res;
    }


    /* asset attributes - these are properties that assets can have
     */

    /* adds an asset atribute
     * 
     * An attr_id of -1 (or not present in arg list) means to assign the next
     * available attribute ID number.
     *
     * If the caller provides an attr_id > -1, then it is assumed that a db 
     * transaction is already started and another one will not be started.
     *
     * returns attr_id
     */
    function insertAssetAttribute($display_priority, $body, 
      $verbose, $rationale, $type, $loss_potential_disclosure, 
      $loss_potential_disruption, $loss_potential_usurpation, 
      $loss_potential_impersonation, $mitigation_disclosure, 
      $mitigation_disruption, $mitigation_usurpation, 
      $mitigation_impersonation, $attr_id = -1, $parent_attr_id = -1, 
      $applicable_if_parent_false_instead_of_true = "F")
    {
	global $AUTHENTICATED_USER;

	if( $attr_id === -1 )
	{
	    $q = "INSERT INTO asset_attributes (display_priority, body, verbose,
	      rationale, type, loss_potential_disclosure, loss_potential_disruption,
	      loss_potential_usurpation, loss_potential_impersonation,
	      mitigation_disclosure, mitigation_disruption,
	      mitigation_usurpation, mitigation_impersonation,
	      parent_attr_id, applicable_if_parent_false_instead_of_true,
	      entered_by, attr_id)
	      values(:display_priority, :body, :verbose, :rationale, :type,
	      :loss_potential_disclosure, :loss_potential_disruption,
	      :loss_potential_usurpation, :loss_potential_impersonation,
	      :mitigation_disclosure, :mitigation_disruption, 
	      :mitigation_usurpation, :mitigation_impersonation,
	      :parent_attr_id, :applicable_if_parent_false, :entered_by,
	      (select case when max(attr_id) isnull then 0 else max(attr_id) + 1 end from asset_attributes))";
	    
	} else
	{
	    $q = "INSERT INTO asset_attributes (display_priority, body, verbose,
	      rationale, type, loss_potential_disclosure, loss_potential_disruption,
	      loss_potential_usurpation, loss_potential_impersonation,
	      mitigation_disclosure, mitigation_disruption,
	      mitigation_usurpation, mitigation_impersonation,
	      parent_attr_id, applicable_if_parent_false_instead_of_true,
	      entered_by, attr_id)
	      values(:display_priority, :body, :verbose, :rationale, :type,
	      :loss_potential_disclosure, :loss_potential_disruption,
	      :loss_potential_usurpation, :loss_potential_impersonation,
	      :mitigation_disclosure, :mitigation_disruption, 
	      :mitigation_usurpation, :mitigation_impersonation,
	      :parent_attr_id, :applicable_if_parent_false, :entered_by,
	      :attr_id)";
	}

	try
	{
	    if( $attr_id === -1 ) $this->dbh->beginTransaction();
	    $sth = $this->dbh->prepare($q);
	    $sth->bindParam(':display_priority', $display_priority);
	    $sth->bindParam(':body', $body);
	    $sth->bindParam(':verbose', $verbose);
	    $sth->bindParam(':rationale', $rationale);
	    $sth->bindParam(':type', $type);
	    $sth->bindParam(':loss_potential_disclosure', $loss_potential_disclosure);
	    $sth->bindParam(':loss_potential_disruption', $loss_potential_disruption);
	    $sth->bindParam(':loss_potential_usurpation', $loss_potential_usurpation);
	    $sth->bindParam(':loss_potential_impersonation', $loss_potential_impersonation);
	    $sth->bindParam(':mitigation_disclosure', $mitigation_disclosure);
	    $sth->bindParam(':mitigation_disruption', $mitigation_disruption);
	    $sth->bindParam(':mitigation_usurpation', $mitigation_usurpation);
	    $sth->bindParam(':mitigation_impersonation', $mitigation_impersonation);
	    $sth->bindParam(':parent_attr_id', $parent_attr_id);
	    $sth->bindParam(':applicable_if_parent_false',$applicable_if_parent_false_instead_of_true);
	    
	    $sth->bindParam(':entered_by', $AUTHENTICATED_USER);
	    if( $attr_id !== -1 ) $sth->bindParam(':attr_id', $attr_id);	
	    $sth->execute();

	    // need the asset id if we added a new one
	    if( $attr_id === -1 ) $res = $this->dbh->query("SELECT max(attr_id) from asset_attributes");
	} catch(Exception $e)
	{
	    // weesa gonna die, never returns to caller
	    $this->dbh->rollBack();
	    die("insertAssetAttribute() - failed to add asset attribute: " . $e->getMessage());
	}

	// if adding a new asset attr, we need to return the newly-added attr id
	// commit after getting the rowid, to avoid race conditions
	if( $attr_id === -1 )
	{
	    $this->dbh->commit();
	    // guess you can also do fetchrow
	    foreach($res as $row)
	    {
		return($row[0]);
	    }
	}

	// otherwise use the provided attr id (because max(attr_id) isn't the
	// attribute id we added.
	return($attr_id);

    }



    /* update an asset attribute
     * 
     * like assets, we don't do an update, rather, expire old row and
     * insert a new one.
     *
     * returns asset id
     */
    function updateAssetAttribute($display_priority, $body, 
      $verbose, $rationale, $type, $loss_potential_disclosure, 
      $loss_potential_disruption, $loss_potential_usurpation, 
      $loss_potential_impersonation, $mitigation_disclosure, 
      $mitigation_disruption, $mitigation_usurpation, 
      $mitigation_impersonation, $attr_id, $parent_attr_id = -1, 
      $applicable_if_parent_false_instead_of_true = "F")
    {

	global $AUTHENTICATED_USER;

	// try to do everything as an atomic operation
	try
	{
	    $this->dbh->beginTransaction();
	} catch(Exception $e)
	{
	    die("updateAssetAttribute() - failed to begin transaction: " . $e->getMessage());
	}

	// expire the old row
	$q = "UPDATE asset_attributes set superseded_on = (strftime('%s', 'now')), 
	      superseded_by = :entered_by where attr_id = :attr_id and
	      superseded_on = 0 ";
	try 
	{
	    $sth = $this->dbh->prepare($q);
	    $sth->bindParam(':entered_by', $AUTHENTICATED_USER);
	    $sth->bindParam(':attr_id',   $attr_id);
	    $sth->execute();
	    if( $sth->rowCount() != 1 )
	    {
		throw new Exception("Wrong number of rows affected. Expected 1, got: " . $sth->rowCount());
	    }
	} catch(Exception $e)
	{
	    $this->dbh->rollBack();
	    die("updateAssetAttribute() - failed to expire old attribute: " . $e->getMessage());
	}

	// insert new row
	$res = $this->insertAssetAttribute($display_priority, $body, 
      $verbose, $rationale, $type, $loss_potential_disclosure, 
      $loss_potential_disruption, $loss_potential_usurpation, 
      $loss_potential_impersonation, $mitigation_disclosure, 
      $mitigation_disruption, $mitigation_usurpation, 
      $mitigation_impersonation, $attr_id, $parent_attr_id, 
      $applicable_if_parent_false_instead_of_true);

	$this->dbh->commit();
	
	return $res;
    }



    /* asset attribute responses - these are properties of assets
     *  assets have attributes, these are those relevant to a given
     *  asset.
     *
     *  For now, we're going to try binary responses
     *  true/false.  This should be a little easier to enter and
     *  analyze, compared to free-form text.
     */


    /* retrieve response columns for response described by asset and attr id
     * 
     * if $history is set to true, the returned array contains every
     * revision of the response, in descending chronological order.
     *
     * returns something, even empty array, unless something goes horribly
     * wrong, in which case, dies.
     *
     */
    function getResponse($asset_id, $attr_id, $history = false)
    {
	if( $history === true )
	{
	    $q = "SELECT * from asset_attribute_responses where
	      asset_id = :asset_id AND attr_id = :attr_id order by
	      entered_on desc";
	} else
	{
	    $q = "SELECT * from asset_attribute_responses where 
	      asset_id = :asset_id AND attr_id = :attr_id AND 
	      superseded_on = 0";
	}

	try
	{
	    $sth = $this->dbh->prepare($q);
	    $sth->bindParam(':asset_id', $asset_id);
	    $sth->bindParam(':attr_id', $attr_id);
	    $sth->execute();
	    return $sth->fetchAll(PDO::FETCH_ASSOC);
	} catch(Exception $e)
	{
	    die("getResponses() - failed to fetch response for asset id $asset_id, attribute id $attr_id : " . $e->getMessage());
	}
    }

    

    /* retrieve all current responses for a given asset id
     * returns an array (list) of response rows ordered by display priority
     * dies otherwise
     */
    function getAllResponses($asset_id)
    {
        $q = "SELECT ar.*, aa.* from asset_attribute_responses ar, 
	asset_attributes aa where 
	ar.asset_id = :asset_id AND ar.attr_id = aa.attr_id AND 
	ar.superseded_on = 0 AND aa.superseded_on = 0 order by
	aa.display_priority asc";

	try
	{
	    $sth = $this->dbh->prepare($q);
	    $sth->bindParam(':asset_id', $asset_id);
	    $sth->execute();
	    return $sth->fetchAll(PDO::FETCH_ASSOC);
	} catch(Exception $e)
	{
	    die("getAllResponses() - failed to fetch responses for asset id $asset_id : " . $e->getMessage());
	}
    }



    /* insert a fresh attribute response for a given attribute and asset
     * 
     * The caller is responsible for sanitizing and sanity-checking the
     * arguments to this function.  This function will die() if there's
     * trouble.
     * 
     * Returns if everything went okay.
     */
    function insertResponse($asset_id, $attr_id, $val)
    {
	global $AUTHENTICATED_USER;

	$needs_expire = false;

	// ok, so indiscriminate inserts are easier to handle.
	// only make changes if necessary.
	$oldresp = $this->getResponse($asset_id, $attr_id, true);
	if( isset($oldresp[0]) && isset($oldresp[0]['response']) && $oldresp[0]['superseded_on'] == 0 )
	{
	    if( $oldresp[0]['response']  == $val )
	    {
		return;
	    }

	    $needs_expire = true;

	    // if the user somehow manages two updates in <1 second, a
	    // constraint violation will occur (superseded_on = now() x 2 rows)
	    // delay a bit to make sure this doesn't happen.
	    if( isset($oldresp[1]) && isset($oldresp[1]['superseded_on']) && 
	      $oldresp[1]['superseded_on'] == time() )
	    {
		sleep(1); 	
	    }
	}

	// guess there's work to do...
	$this->dbh->beginTransaction();

	// expire old response
	if( $needs_expire )
	{
	    $q = "update asset_attribute_responses set
		  superseded_by = :superseded_by,
		  superseded_on = (strftime('%s', 'now'))
		  where asset_id = :asset_id AND
		  attr_id = :attr_id AND
		  superseded_on = 0";
	
	    try
	    {
		$sth = $this->dbh->prepare($q);
		$sth->bindParam(':asset_id', $asset_id);
		$sth->bindParam(':attr_id',  $attr_id);
		$sth->bindParam(':superseded_by', $AUTHENTICATED_USER);
		$sth->execute();
		if( $sth->rowCount() != 1 )
		{
		    throw new Exception("Wrong number of rows affected. Expected 1, got: " . $sth->rowCount());
		}
	    } catch(Exception $e)
	    {
		$this->dbh->rollback();
		die("insertResponse() - failed to expire old response for asset id $asset_id, attribute id $attr_id : " . $e->getMessage());
	    }
	}

	$q =  "insert into asset_attribute_responses (asset_id, attr_id, 
	      response, entered_by) values(:asset_id, :attr_id, :response,
	      :entered_by)";

	try
	{
	    $sth = $this->dbh->prepare($q);
	    $sth->bindParam(':asset_id', $asset_id);
	    $sth->bindParam(':attr_id', $attr_id);
	    $sth->bindParam(':response', $val);
	    $sth->bindParam(':entered_by', $AUTHENTICATED_USER);
	    $sth->execute();
	    $this->dbh->commit();
	} catch(Exception $e)
	{
	    $this->dbh->rollBack();
	    die("insertResponse() - failed to add attribute response: " . $e->getMessage());
	}
	// must be good if we're still here.
	return;
    }
    

}


?>
