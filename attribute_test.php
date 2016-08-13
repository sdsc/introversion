<html>
<body>
<?php
  if( !isset($_POST['submit']) )
  {
?>
<form method="post" action="attribute_test.php">
  <h3>Add Asset Attribute</h3>

  <div>
    <label for="type">Applies to Asset Type</label>
    <br>
    <select name="type" id="type">
      <option value="system">Information System</option>
      <option value="asset">Information Asset</option>
    </select>
  </div>

  <div>
    <label for="display_priority">Display Priority (smaller number = closer to top of page)</label>
    <br>
    <input type="text" name="display_priority" id="display_priority">
  </div>

  <div>
    <label for="body">Attribute Body</label>
    <br>
    <input type="text" name="body" id="body">
  </div>

  <div>
    <label for="verbose">Verbose Description</label>
    <br>
    <input type="text" name="verbose" id="verbose">
  </div>

  <div>
    <label for="rationale">Why is this attribute relevant?</label>
    <br>
    <input type="text" name="rationale" id="rationale">
  </div>

  <div>
    <fieldset>
      <legend>Loss Potential</legend>
      <label for="loss_disclosure">Disclosure</label>
      <input type="text" name="loss_disclosure" id="loss_disclosure">
      <br>
      <label for="loss_disruption">Disruption</label>
      <input type="text" name="loss_disruption" id="loss_disruption">
      <br>
      <label for="loss_usurpation">Usurpation</label>
      <input type="text" name="loss_usurpation" id="loss_usurpation">
      <br>
      <label for="loss_impersonation">Impersonation</label>
      <input type="text" name="loss_impersonation" id="loss_impersonation">
    </fieldset>
  </div>
  
  <div>
    <fieldset>
      <legend>Mitigation</legend>
      <label for="mitigation_disclosure">Disclosure</label>
      <input type="text" name="mitigation_disclosure" id="mitigation_disclosure">
      <br>
      <label for="mitigation_disruption">Disruption</label>
      <input type="text" name="mitigation_disruption" id="mitigation_disruption">
      <br>
      <label for="mitigation_usurpation">Usurpation</label>
      <input type="text" name="mitigation_usurpation" id="mitigation_usurpation">
      <br>
      <label for="mitigation_impersonation">Impersonation</label>
      <input type="text" name="mitigation_impersonation" id="mitigation_impersonation">
    </fieldset>
  </div>
  
  <input type="submit" name="submit" value="Add Attribute">
  </form>
<?php
  }
  else
  {
      # insert the submit
      require_once('ivdb.php');

      $db = new ivdb();

      // we'll pull this from the environment when in production
      $AUTHENTICATED_USER = 'someuser';


      # let's copy relevant variables from the post so they can get
      # munged
      $display_priority = $_POST['display_priority'];
      $body = $_POST['body'];
      $verbose = $_POST['verbose'];
      $rationale = $_POST['rationale'];
      $type = $_POST['type'];
      $loss['disclosure'] = $_POST['loss_disclosure'];
      $loss['disruption'] = $_POST['loss_disruption'];
      $loss['usurpation'] = $_POST['loss_usurpation'];
      $loss['impersonation'] = $_POST['loss_impersonation'];
      $mitigation['disclosure'] = $_POST['mitigation_disclosure'];
      $mitigation['disruption'] = $_POST['mitigation_disruption'];
      $mitigation['usurpation'] = $_POST['mitigation_usurpation'];
      $mitigation['impersonation'] = $_POST['mitigation_impersonation'];

      # check sanity here
      $errors = Array();

      if ( !is_numeric($display_priority) )
      {
          array_push($errors, "Display Priority is not a numeric value.  It must be a numeric value.");
      }

      if ( trim($body)  == '' )
      {
          array_push($errors, "Body appears to be empty.  It cannot be empty.");
      }
      $body = htmlspecialchars($body);

      if ( trim($verbose) == '' )
      {
          array_push($errors, "Verbose appears to be empty.  It cannot be empty.");
      }
      $verbose = htmlspecialchars($verbose);

      if ( trim($rationale) == '' )
      {
          array_push($errors, "Rationale appears to be empty.  It cannot be empty.");
      }
      $rationale = htmlspecialchars($rationale);

      if ( trim($type) == '' )
      {
          array_push($errors, "Type appears to be empty.  It cannot be empty.");
      }
      $type = htmlspecialchars($type);

      foreach ( $loss as $k => $v)
      {
          if ( !is_numeric($v) )
	  {
	      array_push($errors, "Loss $k is not a numeric value.  It must be a numeric value.");
	  }

	  if ( $v < 0 || $v > 10 )
	  {
	      array_push($errors, "Loss $k is outside the range of [0,10].  It must be between 0 and 10, inclusive.");
	  }
      }

      foreach ( $mitigation as $k => $v)
      {
          if ( !is_numeric($v) )
	  {
	      array_push($errors, "Mitigation $k is not a numeric value.  It must be a numeric value.");
	  }

	  if ( $v < 0 || $v > 10 )
	  {
	      array_push($errors, "Mitigation $k is outside the range of [0,10].  It must be between 0 and 10, inclusive.");
	  }
      }

      
      # stop here if something is wrong
      if ( count($errors) > 0 )
      {
          echo(implode("<br>\n", $errors));
	  die();
      }

      # all good, do it.
      $res = $db->insertAssetAttribute($display_priority, $body,
        $verbose, $rationale, $type, $loss['disclosure'],
	$loss['disruption'], $loss['usurpation'],
	$loss['impersonation'], $mitigation['disclosure'],
	$mitigation['disruption'], $mitigation['usurpation'],
	$mitigation['impersonation']);
      echo "Whee. $res";
  }
?>
</body>
</html>
