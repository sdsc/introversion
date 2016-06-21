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

	    $rc = $sth->execute();
	    if( !$rc )
	    {
		$ec = $this->dbh->errorInfo();
		throw new Exception($ec[2]);
	    }

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
	    $rc = $sth->execute();
	    if( !$rc )
	    {
		$ec = $this->dbh->errorInfo();
		throw new Exception($ec[2]);
	    }
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



    /* asset attribute responses - these are properties of assets
     *  assets have attributes, these are those relevant to a given
     *  asset.
     *
     *  For now, we're going to try binary responses
     *  true/false.  This should be a little easier to enter and
     *  analyze, compared to free-form text.
     */

    
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

	$q =  "insert into asset_attribute_responses (asset_id, attr_id, 
	      response, entered_by) values(:asset_id, :attr_id, :response,
	      :entered_by)";

	try
	{
	    $this->dbh->beginTransaction();
	    $sth = $this->dbh->prepare($q);
	    $sth->bindParam(':asset_id', $asset_id);
	    $sth->bindParam(':attr_id', $attr_id);
	    $sth->bindParam(':response', $val);
	    $sth->bindParam(':entered_by', $AUTHENTICATED_USER);
	    $rc = $sth->execute();

	    if( !$rc )
	    {
		$ec = $this->dbh->errorInfo();
		throw new Exception($ec[2]);
	    }

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
