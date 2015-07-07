<?php

/* $Id ShipmentTerms.php series 1 18/5/2015 22:30:00 by sj $ */

include('includes/session.inc');
$Title = _('Shipment Terms') . ' / ' . _('Maintenance');
include('includes/header.inc');

if (isset($_POST['SelectedTerms'])){
	$SelectedTerms = mb_strtoupper($_POST['SelectedTerms']);
} elseif (isset($_GET['SelectedTerms'])){
	$SelectedTerms = mb_strtoupper($_GET['SelectedTerms']);
}

if (isset($Errors)) {
	unset($Errors);
}

$Errors = array();

echo '<p class="page_title_text"><img src="'.$RootPath.'/css/'.$Theme.'/images/maintenance.png" title="' . _('Shipment Terms')
	. '" alt="" />' . _('Shipment Terms Setup') . '</p>
	<div class="page_help_text">' . _('Add/edit/delete Shipment Terms') . '</div>
	<br />';

if (isset($_POST['submit'])) {

	//initialise no input errors assumed initially before we test
	$InputError = 0;

	/* actions to take once the user has clicked the submit button
	ie the page has called itself with some user input */

	//first off validate inputs sensible
	$i=1;
	if (mb_strlen($_POST['ShipmentTermsName']) >40) {
		$InputError = 1;
		echo prnMsg(_('The Shipment Terms name description must be 40 characters or less long'),'error');
		$Errors[$i] = 'ShipmentTerms';
		$i++;
	}

	if (mb_strlen(trim($_POST['ShipmentTermsName']))==0) {
		$InputError = 1;
		echo prnMsg(_('The Shipment Terms name description must contain at least one character'),'error');
		$Errors[$i] = 'ShipmentTerms';
		$i++;
	}

	$CheckSQL = "SELECT count(*)
		     FROM shipmentterms
		     WHERE shipmenttermsname = '" . $_POST['ShipmentTermsName'] . "'";
	$CheckResult=DB_query($CheckSQL);
	$CheckRow=DB_fetch_row($CheckResult);
	if ($CheckRow[0]>0) {
		$InputError = 1;
		echo prnMsg(_('You already have a shipment terms called').' '.$_POST['ShipmentTermsName'],'error');
		$Errors[$i] = 'ShipmentTermsName';
		$i++;
	}

	if (isset($SelectedTerms) AND $InputError !=1) {

		$sql = "UPDATE shipmentterms
			SET shipmenttermsname = '" . $_POST['ShipmentTermsName'] . "'
			WHERE shipmenttermsid = '" . $SelectedTerms . "'";

		prnMsg(_('The shipment terms') . ' ' . $SelectedTerms . ' ' .  _('has been updated'),'success');
	} elseif ($InputError !=1){
		// Add new record on submit

		$sql = "INSERT INTO shipmentterms
					(shipmenttermsname)
				VALUES ('" . $_POST['ShipmentTermsName'] . "')";


		$msg = _('shipment terms') . ' ' . $_POST['ShipmentTermsName'] .  ' ' . _('has been created');
		$CheckSQL = "SELECT count(shipmenttermsid) FROM shipmentterms";
		$result = DB_query($CheckSQL);
		$row = DB_fetch_row($result);
	}

	if ( $InputError !=1) {
	//run the SQL from either of the above possibilites
		$result = DB_query($sql);


	// Fetch the default shipment terms
	/*	$sql = "SELECT confvalue
					FROM config
					WHERE confname='Defaultshipmentterms'";
		$result = DB_query($sql);
		$shipmenttermsRow = DB_fetch_row($result);
		$Defaultshipmentterms = $shipmenttermsRow[0];
	*/
	// Does it exist
		$CheckSQL = "SELECT count(*)
			     FROM shipmentterms
			     WHERE shipmenttermsid = '" . $Defaultshipmentterms . "'";
		$CheckResult = DB_query($CheckSQL);
		$CheckRow = DB_fetch_row($CheckResult);
	
	// If it doesnt then update config with newly created one.
	/*	if ($CheckRow[0] == 0) {
			$sql = "UPDATE config
					SET confvalue='" . $_POST['ShipmentTermsID'] . "'
					WHERE confname='Defaultshipmentterms'";
			$result = DB_query($sql);
			$_SESSION['Defaultshipmentterms'] = $_POST['ShipmentTermsID'];
		}
	*/
		unset($SelectedTerms);
		unset($_POST['ShipmentTermsID']);
		unset($_POST['ShipmentTermsName']);
	}

} elseif ( isset($_GET['delete']) ) {

	$sql = "SELECT COUNT(*) FROM suppliers WHERE supptype='" . $SelectedTerms . "'";

	$ErrMsg = _('The number of suppliers using this Type record could not be retrieved because');
	$result = DB_query($sql,$ErrMsg);
	$myrow = DB_fetch_row($result);
	if ($myrow[0]>0) {
		prnMsg (_('Cannot delete this type because suppliers are currently set up to use this type') . '<br />' .
			_('There are') . ' ' . $myrow[0] . ' ' . _('suppliers with this type code'));
	} else {

		$sql="DELETE FROM shipmentterms WHERE shipmenttermsid='" . $SelectedTerms . "'";
		$ErrMsg = _('The Type record could not be deleted because');
		$result = DB_query($sql,$ErrMsg);
		prnMsg(_('shipment terms') . $SelectedTerms  . ' ' . _('has been deleted') ,'success');

		unset ($SelectedTerms);
		unset($_GET['delete']);

	}
}

if (!isset($SelectedTerms)){

/* It could still be the second time the page has been run and a record has been selected for modification - SelectedTerms will
 *  exist because it was sent with the new call. If its the first time the page has been displayed with no parameters then
 * none of the above are true and the list of sales types will be displayed with links to delete or edit each. These will call
 * the same page again and allow update/input or deletion of the records
 */

	$sql = "SELECT shipmenttermsid, shipmenttermsname FROM shipmentterms";
	$result = DB_query($sql);

	echo '<table class="selection">';
	echo '<tr>
		<th class="ascending" >' . _('Terms ID') . '</th>
		<th class="ascending" >' . _('Shipment Terms Name') . '</th>
		</tr>';

$k=0; //row colour counter

while ($myrow = DB_fetch_row($result)) {
	if ($k==1){
		echo '<tr class="EvenTableRows">';
		$k=0;
	} else {
		echo '<tr class="OddTableRows">';
		$k=1;
	}

	printf('<td>%s</td>
			<td>%s</td>
			<td><a href="%sSelectedTerms=%s">' . _('Edit') . '</a></td>
			<td><a href="%sSelectedTerms=%s&amp;delete=yes" onclick="return confirm(\'' .
				_('Are you sure you wish to delete this shipment terms?') . '\');">' . _('Delete') . '</a></td>
		</tr>',
		$myrow[0],
		$myrow[1],
		htmlspecialchars($_SERVER['PHP_SELF'],ENT_QUOTES,'UTF-8') . '?',
		$myrow[0],
		htmlspecialchars($_SERVER['PHP_SELF'],ENT_QUOTES,'UTF-8') . '?',
		$myrow[0]);
	}
	//END WHILE LIST LOOP
	echo '</table>';
}

//end of ifs and buts!
if (isset($SelectedTerms)) {

	echo '<div class="centre">
			<p><a href="' . htmlspecialchars($_SERVER['PHP_SELF'],ENT_QUOTES,'UTF-8') . '">' . _('Show All Types Defined') . '</a></p>
		</div>';
}
if (! isset($_GET['delete'])) {

	echo '<form method="post" action="' . htmlspecialchars($_SERVER['PHP_SELF'],ENT_QUOTES,'UTF-8') . '">';
    echo '<div>';
	echo '<input type="hidden" name="FormID" value="' . $_SESSION['FormID'] . '" />';
	echo '<br />
		<table class="selection">'; //Main table

	// The user wish to EDIT an existing type
	if ( isset($SelectedTerms) AND $SelectedTerms!='' ) {

		$sql = "SELECT shipmenttermsid,
			       shipmenttermsname
		        FROM shipmentterms
		        WHERE shipmenttermsid='" . $SelectedTerms . "'";

		$result = DB_query($sql);
		$myrow = DB_fetch_array($result);

		$_POST['ShipmentTermsID'] = $myrow['shipmenttermsid'];
		$_POST['ShipmentTermsName']  = $myrow['shipmenttermsname'];

		echo '<input type="hidden" name="SelectedTerms" value="' . $SelectedTerms . '" />';
		echo '<input type="hidden" name="ShipmentTermsID" value="' . $_POST['ShipmentTermsID'] . '" />';

		// We dont allow the user to change an existing type code

		echo '<tr>
				<td>' ._('Terms ID') . ': </td>
				<td>' . $_POST['ShipmentTermsID'] . '</td>
			</tr>';
	}

	if (!isset($_POST['ShipmentTermsName'])) {
		$_POST['ShipmentTermsName']='';
	}
	echo '<tr> 
			<td>' . _('Shipment Terms Name') . ':</td>
			<td><input type="text"  required="true" pattern="(?!^\s+$)[^<>+-]{1,40}" title="'._('The input should not be over 40 characters and contains illegal characters').'" name="ShipmentTermsName" placeholder="'._('less than 40 characters').'" value="' . $_POST['ShipmentTermsName'] . '" /></td>
		</tr>';

	echo '<tr>
			<td colspan="2">
				<div class="centre">
					<input type="submit" name="submit" value="' . _('Accept') . '" />
				</div>
			</td>
		</tr>
		</table>
		</div>
		</form>';

} // end if user wish to delete

include('includes/footer.inc');
?>
