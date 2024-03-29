<?php

/* $Id PO_Header.php 4183 2010-12-14 09:30:20Z daintree $ */

include('includes/DefinePOClass.php');
include('includes/session.inc');

function ConverToRoman($num){ 
    $n = intval($num); 
    $res = ''; 

    //array of roman numbers
    $romanNumber_Array = array( 
        'M'  => 1000, 
        'CM' => 900, 
        'D'  => 500, 
        'CD' => 400, 
        'C'  => 100, 
        'XC' => 90, 
        'L'  => 50, 
        'XL' => 40, 
        'X'  => 10, 
        'IX' => 9, 
        'V'  => 5, 
        'IV' => 4, 
        'I'  => 1); 

    foreach ($romanNumber_Array as $roman => $number){ 
        //divide to get  matches
        $matches = intval($n / $number); 

        //assign the roman char * $matches
        $res .= str_repeat($roman, $matches); 

        //substract from the number
        $n = $n % $number; 
    } 

    // return the result
    return $res; 
} 

//KL ADA MODIFY ORDER NUMBER,JUDULNY BERUBAH
if (isset($_GET['ModifyOrderNumber'])) {
	$Title = _('Modify Purchase Order') . ' ' . $_GET['ModifyOrderNumber'];
} else {
	$Title = _('Purchase Order Entry');
}

if (isset($_GET['SupplierID'])) {
	$_POST['Select'] = $_GET['SupplierID'];
}

/* webERP manual links before header.inc */
$ViewTopic= 'PurchaseOrdering';
$BookMark = 'PurchaseOrdering';

include('includes/header.inc');
include('includes/SQL_CommonFunctions.inc');

/*If the page is called is called without an identifier being set then
 * it must be either a new order, or the start of a modification of an
 * order, and so we must create a new identifier.
 *
 * The identifier only needs to be unique for this php session, so a
 * unix timestamp will be sufficient.
 */

if (empty($_GET['identifier'])) {
	$identifier = date('U');
} else {
	$identifier = $_GET['identifier'];
}

/*Page is called with NewOrder=Yes when a new order is to be entered
 * the session variable that holds all the PO data $_SESSION['PO'][$identifier]
 * is unset to allow all new details to be created */

if (isset($_GET['NewOrder']) AND isset($_SESSION['PO' . $identifier])) {
	unset($_SESSION['PO' . $identifier]);
	$_SESSION['ExistingOrder'] = 0;
}

if (isset($_POST['Select']) AND empty($_POST['SupplierContact'])) {
	$sql = "SELECT contact
				FROM suppliercontacts
				WHERE supplierid='" . $_POST['Select'] . "'";

	$SuppCoResult = DB_query($sql);
	if (DB_num_rows($SuppCoResult) > 0) {
		$myrow = DB_fetch_row($SuppCoResult);
		$_POST['SupplierContact'] = $myrow[0];
	} else {
		$_POST['SupplierContact'] = '';
	}
}

if ((isset($_POST['UpdateStatus']) AND $_POST['UpdateStatus'] != '')) {

	if ($_SESSION['ExistingOrder'] == 0) {
		prnMsg(_('This is a new order. It must be created before you can change the status'), 'warn');
		$OKToUpdateStatus = 0;
	} elseif ($_SESSION['PO' . $identifier]->Status != $_POST['Status']) { //the old status  != new status
		$OKToUpdateStatus = 1;
		$AuthSQL = "SELECT authlevel
					FROM purchorderauth
					WHERE userid='" . $_SESSION['UserID'] . "'
					AND currabrev='" . $_SESSION['PO' . $identifier]->CurrCode . "'";

		$AuthResult = DB_query($AuthSQL);
		$myrow = DB_fetch_array($AuthResult);
		$AuthorityLevel = $myrow['authlevel'];
		$OrderTotal = $_SESSION['PO' . $identifier]->Order_Value();

		if ($_POST['StatusComments'] != '') {
			$_POST['StatusComments'] = ' - ' . $_POST['StatusComments'];
		}
		if (IsEmailAddress($_SESSION['UserEmail'])) {
			$UserChangedStatus = ' <a href="mailto:' . $_SESSION['UserEmail'] . '">' . $_SESSION['UsersRealName'] . '</a>';
		} else {
			$UserChangedStatus = ' ' . $_SESSION['UsersRealName'] . ' ';
		}

		if ($_POST['Status'] == 'Authorised') {
			if ($AuthorityLevel > $OrderTotal) {
				$_SESSION['PO' . $identifier]->StatusComments = date($_SESSION['DefaultDateFormat']) . ' - ' . _('Authorised by') . $UserChangedStatus . $_POST['StatusComments'] . '<br />' . html_entity_decode($_POST['StatusCommentsComplete'], ENT_QUOTES, 'UTF-8');
				$_SESSION['PO' . $identifier]->AllowPrintPO = 1;
			} else {
				$OKToUpdateStatus = 0;
				prnMsg(_('You do not have permission to authorise this purchase order') . '.<br />' . _('This order is for') . ' ' . $_SESSION['PO' . $identifier]->CurrCode . ' ' . $OrderTotal . '. ' . _('You can only authorise up to') . ' ' . $_SESSION['PO' . $identifier]->CurrCode . ' ' . $AuthorityLevel . '.<br />' . _('If you think this is a mistake please contact the systems administrator'), 'warn');
			}
		}

		if ($_POST['Status'] == 'Rejected' OR $_POST['Status'] == 'Cancelled') {
			if (!isset($_SESSION['ExistingOrder']) OR $_SESSION['ExistingOrder'] != 0) {
				/* need to check that not already dispatched or invoiced by the supplier */
				if ($_SESSION['PO' . $identifier]->Any_Already_Received() == 1) {
					$OKToUpdateStatus = 0; //not ok to update the status
					prnMsg(_('This order cannot be cancelled or rejected because some of it has already been received') . '. ' . _('The line item quantities may be modified to quantities more than already received') . '. ' . _('Prices cannot be altered for lines that have already been received') . ' ' . _('and quantities cannot be reduced below the quantity already received'), 'warn');
				}
				$ShipmentExists = $_SESSION['PO' . $identifier]->Any_Lines_On_A_Shipment();
				if ($ShipmentExists != false) {
					$OKToUpdateStatus = 0; //not ok to update the status
					prnMsg(_('This order cannot be cancelled or rejected because there is at least one line that is allocated to a shipment') . '. ' . _('See shipment number') . ' ' . $ShipmentExists, 'warn');
				}
			} //!isset($_SESSION['ExistingOrder']) OR $_SESSION['ExistingOrder'] != 0
			if ($OKToUpdateStatus == 1) { // none of the order has been received
				if ($AuthorityLevel > $OrderTotal) {
					$_SESSION['PO' . $identifier]->StatusComments = date($_SESSION['DefaultDateFormat']) . ' - ' . $_POST['Status'] . ' ' . _('by') . $UserChangedStatus . $_POST['StatusComments'] . '<br />' . html_entity_decode($_POST['StatusCommentsComplete'], ENT_QUOTES, 'UTF-8');
				} else {
					$OKToUpdateStatus = 0;
					prnMsg(_('You do not have permission to reject this purchase order') . '.<br />' . _('This order is for') . ' ' . $_SESSION['PO' . $identifier]->CurrCode . ' ' . $OrderTotal . '. ' . _('Your authorisation limit is set at') . ' ' . $_SESSION['PO' . $identifier]->CurrCode . ' ' . $AuthorityLevel . '.<br />' . _('If you think this is a mistake please contact the systems administrator'), 'warn');
				}
			} //$OKToUpdateStatus == 1
		} //$_POST['Status'] == 'Rejected' OR $_POST['Status'] == 'Cancelled'

		if ($_POST['Status'] == 'Pending') {

			if ($_SESSION['PO' . $identifier]->Any_Already_Received() == 1) {
				$OKToUpdateStatus = 0; //not OK to update status
				prnMsg(_('This order could not have the status changed back to pending because some of it has already been received. Quantities received will need to be returned to change the order back to pending.'), 'warn');
			}

			if (($AuthorityLevel > $OrderTotal OR $_SESSION['UserID'] == $_SESSION['PO' . $identifier]->Initiator) AND $OKToUpdateStatus == 1) {
				$_SESSION['PO' . $identifier]->StatusComments = date($_SESSION['DefaultDateFormat']) . ' - ' . _('Order set to pending status by') . $UserChangedStatus . $_POST['StatusComments'] . '<br />' . html_entity_decode($_POST['StatusCommentsComplete'], ENT_QUOTES, 'UTF-8');

			} elseif ($AuthorityLevel < $OrderTotal AND $_SESSION['UserID'] != $_SESSION['PO' . $identifier]->Initiator) {
				$OKToUpdateStatus = 0;
				prnMsg(_('You do not have permission to change the status of this purchase order') . '.<br />' . _('This order is for') . ' ' . $_SESSION['PO' . $identifier]->CurrCode . ' ' . $OrderTotal . '. ' . _('Your authorisation limit is set at') . ' ' . $_SESSION['PO' . $identifier]->CurrCode . ' ' . $AuthorityLevel . '.<br />' . _('If you think this is a mistake please contact the systems administrator'), 'warn');
			} //$AuthorityLevel < $OrderTotal AND $_SESSION['UserID'] != $_SESSION['PO' . $identifier]->Initiator
		} //$_POST['Status'] == 'Pending'

		if ($OKToUpdateStatus == 1) {
			$_SESSION['PO' . $identifier]->Status = $_POST['Status'];
			if ($_SESSION['PO' . $identifier]->Status == 'Authorised') {
				$AllowPrint = 1;
			} //$_SESSION['PO' . $identifier]->Status == 'Authorised'
			else {
				$AllowPrint = 0;
			}
			$SQL = "UPDATE purchorders SET status='" . $_POST['Status'] . "',
							stat_comment='" . $_SESSION['PO' . $identifier]->StatusComments . "',
							allowprint='" . $AllowPrint . "'
					WHERE purchorders.orderno ='" . $_SESSION['ExistingOrder'] . "'";

			$ErrMsg = _('The order status could not be updated because');
			$UpdateResult = DB_query($SQL, $ErrMsg);

			if ($_POST['Status']=='Completed' OR $_POST['Status']=='Cancelled' OR $_POST['Status']=='Rejected') {
				$SQL = "UPDATE purchorderdetails SET completed=1 WHERE orderno='" . $_SESSION['ExistingOrder'] . "'";
				$UpdateResult =DB_query($SQL,$ErrMsg);
			} else {//To ensure that the purchorderdetails status is correct when it is recovered from a cancelled orders
				$SQL = "UPDATE purchorderdetails SET completed=0 WHERE orderno='" . $_SESSION['ExistingOrder'] . "'";
				$UpdateResult = DB_query($SQL,$ErrMsg);
			}
		} //$OKToUpdateStatus == 1
	} //end if there is actually a status change the class Status != the POST['Status']
} //End if user hit Update Status

if (isset($_GET['NewOrder']) AND isset($_GET['StockID']) AND isset($_GET['SelectedSupplier'])) {
	/*
	 * initialise a new order
	 */
	$_SESSION['ExistingOrder'] = 0;
	unset($_SESSION['PO' . $identifier]);
	/* initialise new class object */
	$_SESSION['PO' . $identifier] = new PurchOrder;
	/*
	 * and fill it with essential data
	 */
	$_SESSION['PO' . $identifier]->AllowPrintPO = 1;
	/* Of course 'cos the order aint even started !!*/
	$_SESSION['PO' . $identifier]->GLLink = $_SESSION['CompanyRecord']['gllink_stock'];
	/* set the SupplierID we got */
	$_SESSION['PO' . $identifier]->SupplierID = $_GET['SelectedSupplier'];
	$_SESSION['PO' . $identifier]->DeliveryDate = date($_SESSION['DefaultDateFormat']);
	$_SESSION['PO' . $identifier]->Initiator = $_SESSION['UserID'];
	$_SESSION['RequireSupplierSelection'] = 0;
	$_POST['Select'] = $_GET['SelectedSupplier'];

	/*
	 * the item (it's item code) that should be purchased
	 */
	$Purch_Item = $_GET['StockID'];

} //End if it's a new order sent with supplier code and the item to order

if (isset($_POST['EnterLines']) OR isset($_POST['AllowRePrint'])) {
	/*User hit the button to enter line items -
	 *  ensure session variables updated then meta refresh to PO_Items.php*/

	$_SESSION['PO' . $identifier]->Location = $_POST['StkLocation'];
	$_SESSION['PO' . $identifier]->SupplierContact = $_POST['SupplierContact'];
	$_SESSION['PO' . $identifier]->DelAdd1 = $_POST['DelAdd1'];
	$_SESSION['PO' . $identifier]->DelAdd2 = $_POST['DelAdd2'];
	$_SESSION['PO' . $identifier]->DelAdd3 = $_POST['DelAdd3'];
	$_SESSION['PO' . $identifier]->DelAdd4 = $_POST['DelAdd4'];
	$_SESSION['PO' . $identifier]->DelAdd5 = $_POST['DelAdd5'];
	$_SESSION['PO' . $identifier]->DelAdd6 = $_POST['DelAdd6'];
	$_SESSION['PO' . $identifier]->SuppDelAdd1 = $_POST['SuppDelAdd1'];
	$_SESSION['PO' . $identifier]->SuppDelAdd2 = $_POST['SuppDelAdd2'];
	$_SESSION['PO' . $identifier]->SuppDelAdd3 = $_POST['SuppDelAdd3'];
	$_SESSION['PO' . $identifier]->SuppDelAdd4 = $_POST['SuppDelAdd4'];
	$_SESSION['PO' . $identifier]->SuppDelAdd5 = $_POST['SuppDelAdd5'];
	$_SESSION['PO' . $identifier]->SuppTel = $_POST['SuppTel'];
	$_SESSION['PO' . $identifier]->Initiator = $_POST['Initiator'];
	$_SESSION['PO' . $identifier]->RequisitionNo = $_POST['Requisition'];
	$_SESSION['PO' . $identifier]->Version = $_POST['Version'];
	$_SESSION['PO' . $identifier]->DeliveryDate = $_POST['DeliveryDate'];
	$_SESSION['PO' . $identifier]->Revised = $_POST['Revised'];
	$_SESSION['PO' . $identifier]->ExRate = filter_number_format($_POST['ExRate']);
	$_SESSION['PO' . $identifier]->Comments = $_POST['Comments'];
	$_SESSION['PO' . $identifier]->DeliveryBy = $_POST['DeliveryBy'];
	if (isset($_POST['StatusComments'])) {
		$_SESSION['PO' . $identifier]->StatusComments = $_POST['StatusComments'];
	}
	$_SESSION['PO' . $identifier]->PaymentTerms = $_POST['PaymentTerms'];
	$_SESSION['PO' . $identifier]->Contact = $_POST['Contact'];
	$_SESSION['PO' . $identifier]->Tel = $_POST['Tel'];
	//TAMBAHAN FIELD YG MW SUBMIT
	$_SESSION['PO' . $identifier]->Port = $_POST['Port'];
	$_SESSION['PO' . $identifier]->Ponumb = $_POST['Ponumb'];
	$_SESSION['PO' . $identifier]->Insurance = $_POST['Insurance'];
	$_SESSION['PO' . $identifier]->ShipmentTermsName = $_POST['ShipmentTermsName'];
	$_SESSION['PO' . $identifier]->CountryOrigin = $_POST['CountryOrigin'];
	if (isset($_POST['RePrint']) AND $_POST['RePrint'] == 1) {
		$_SESSION['PO' . $identifier]->AllowPrintPO = 1;

		$sql = "UPDATE purchorders
				SET purchorders.allowprint='1'
				WHERE purchorders.orderno='" . $_SESSION['PO' . $identifier]->OrderNo . "'";

		$ErrMsg = _('An error occurred updating the purchase order to allow reprints') . '. ' . _('The error says');
		$UpdateResult = DB_query($sql, $ErrMsg);
	} //end if change to allow reprint
	else {
		$_POST['RePrint'] = 0;
	}
	if (!isset($_POST['AllowRePrint'])) { // user only hit update not "Enter Lines"
		echo '<meta http-equiv="Refresh" content="0; url=' . $RootPath . '/PO_Items.php?identifier=' . $identifier . '">';
		echo '<p>';
		prnMsg(_('You should automatically be forwarded to the entry of the purchase order line items page') . '. ' . _('If this does not happen') . ' (' . _('if the browser does not support META Refresh') . ') ' . '<a href="' . $RootPath . '/PO_Items.php?identifier=' . $identifier . '">' . _('click here') . '</a> ' . _('to continue'), 'info');
		include('includes/footer.inc');
		exit;
	} // end if reprint not allowed
} //isset($_POST['EnterLines']) OR isset($_POST['AllowRePrint'])

/* end of if isset _POST'EnterLines' */

echo '<span style="float:left"><a href="' . $RootPath . '/PO_SelectOSPurchOrder.php?identifier=' . $identifier . '">' . _('Back to Purchase Orders') . '</a></span>';

/*The page can be called with ModifyOrderNumber=x where x is a purchase
 * order number. The page then looks up the details of order x and allows
 * these details to be modified */

if (isset($_GET['ModifyOrderNumber'])) {
	include('includes/PO_ReadInOrder.inc');
}


if (!isset($_SESSION['PO' . $identifier])) {
	/* It must be a new order being created
	 * $_SESSION['PO'.$identifier] would be set up from the order modification
	 * code above if a modification to an existing order. Also
	 * $ExistingOrder would be set to 1. The delivery check screen
	 * is where the details of the order are either updated or
	 * inserted depending on the value of ExistingOrder
	 * */

	$_SESSION['ExistingOrder'] = 0;
	$_SESSION['PO' . $identifier] = new PurchOrder;
	$_SESSION['PO' . $identifier]->AllowPrintPO = 1;
	/*Of course cos the order aint even started !!*/
	$_SESSION['PO' . $identifier]->GLLink = $_SESSION['CompanyRecord']['gllink_stock'];

	if ($_SESSION['PO' . $identifier]->SupplierID == '' OR !isset($_SESSION['PO' . $identifier]->SupplierID)) {
		/* a session variable will have to maintain if a supplier
		 * has been selected for the order or not the session
		 * variable supplierID holds the supplier code already
		 * as determined from user id /password entry  */
		$_SESSION['RequireSupplierSelection'] = 1;
	} else {
		$_SESSION['RequireSupplierSelection'] = 0;
	}

} //end if initiating a new PO

if (isset($_POST['ChangeSupplier'])) {
	if ($_SESSION['PO' . $identifier]->Status == 'Pending' AND $_SESSION['UserID'] == $_SESSION['PO' . $identifier]->Initiator) {

		if ($_SESSION['PO' . $identifier]->Any_Already_Received() == 0) {

			$_SESSION['RequireSupplierSelection'] = 1;
			$_SESSION['PO' . $identifier]->Status = 'Pending';
			$_SESSION['PO' . $identifier]->StatusComments == date($_SESSION['DefaultDateFormat']) . ' - ' . _('Supplier changed by') . ' <a href="mailto:' . $_SESSION['UserEmail'] . '">' . $_SESSION['UserID'] . '</a> - ' . $_POST['StatusComments'] . '<br />' . html_entity_decode($_POST['StatusCommentsComplete'], ENT_QUOTES, 'UTF-8');

		} else {

			echo '<br /><br />';
			prnMsg(_('Cannot modify the supplier of the order once some of the order has been received'), 'warn');
		}
	}
} //user hit ChangeSupplier

if (isset($_POST['SearchSuppliers'])) {
	if (mb_strlen($_POST['Keywords']) > 0 AND mb_strlen($_SESSION['PO' . $identifier]->SupplierID) > 0) {
		prnMsg(_('Supplier name keywords have been used in preference to the supplier code extract entered'), 'warn');
	}
	if (mb_strlen($_POST['Keywords']) > 0) {
		//insert wildcard characters in spaces
		$SearchString = '%' . str_replace(' ', '%', $_POST['Keywords']) . '%';

		$SQL = "SELECT suppliers.supplierid,
							suppliers.suppname,
							suppliers.supptype,
							suppliers.address1,
							suppliers.address2,
							suppliers.address3,
							suppliers.address4,
							suppliers.address5,
							suppliers.address6,
							suppliers.currcode
						FROM suppliers
						WHERE suppliers.suppname " . LIKE . " '" . $SearchString . "'
						ORDER BY suppliers.suppname";

	} elseif (mb_strlen($_POST['SuppCode']) > 0) {

		$SQL = "SELECT suppliers.supplierid,
							suppliers.suppname,
							suppliers.supptype,
							suppliers.address1,
							suppliers.address2,
							suppliers.address3,
							suppliers.address4,
							suppliers.address5,
							suppliers.address6,
							suppliers.currcode
						FROM suppliers
						WHERE suppliers.supplierid " . LIKE . " '%" . $_POST['SuppCode'] . "%'
						ORDER BY suppliers.supplierid";
	} else {

		$SQL = "SELECT suppliers.supplierid,
						suppliers.suppname,
						suppliers.supptype,
						suppliers.address1,
						suppliers.address2,
						suppliers.address3,
						suppliers.address4,
						suppliers.address5,
						suppliers.address6,
						suppliers.currcode
					FROM suppliers
					ORDER BY suppliers.supplierid";
	}

	$ErrMsg = _('The searched supplier records requested cannot be retrieved because');
	$result_SuppSelect = DB_query($SQL, $ErrMsg);
	$SuppliersReturned = DB_num_rows($result_SuppSelect);
	if (DB_num_rows($result_SuppSelect) == 1) {
		$myrow = DB_fetch_array($result_SuppSelect);
		$_POST['Select'] = $myrow['supplierid'];
		$_POST['Supptype'] = $myrow['supptype'];
	} elseif (DB_num_rows($result_SuppSelect) == 0) {
		prnMsg(_('No supplier records contain the selected text') . ' - ' . _('please alter your search criteria and try again'), 'info');
	}
} /*end of if search for supplier codes/names */


if ((!isset($_POST['SearchSuppliers']) or $_POST['SearchSuppliers'] == '') AND (isset($_SESSION['PO' . $identifier]->SupplierID) AND $_SESSION['PO' . $identifier]->SupplierID != '')) {
	/*	The session variables are set but the form variables could have been lost
		need to restore the form variables from the session */
	$_POST['SupplierID'] = $_SESSION['PO' . $identifier]->SupplierID;
	$_POST['SupplierName'] = $_SESSION['PO' . $identifier]->SupplierName;
	$_POST['CurrCode'] = $_SESSION['PO' . $identifier]->CurrCode;
	$_POST['ExRate'] = $_SESSION['PO' . $identifier]->ExRate;
	$_POST['PaymentTerms'] = $_SESSION['PO' . $identifier]->PaymentTerms;
	$_POST['DelAdd1'] = $_SESSION['PO' . $identifier]->DelAdd1;
	$_POST['DelAdd2'] = $_SESSION['PO' . $identifier]->DelAdd2;
	$_POST['DelAdd3'] = $_SESSION['PO' . $identifier]->DelAdd3;
	$_POST['DelAdd4'] = $_SESSION['PO' . $identifier]->DelAdd4;
	$_POST['DelAdd5'] = $_SESSION['PO' . $identifier]->DelAdd5;
	$_POST['DelAdd6'] = $_SESSION['PO' . $identifier]->DelAdd6;
	$_POST['SuppDelAdd1'] = $_SESSION['PO' . $identifier]->SuppDelAdd1;
	$_POST['SuppDelAdd2'] = $_SESSION['PO' . $identifier]->SuppDelAdd2;
	$_POST['SuppDelAdd3'] = $_SESSION['PO' . $identifier]->SuppDelAdd3;
	$_POST['SuppDelAdd4'] = $_SESSION['PO' . $identifier]->SuppDelAdd4;
	$_POST['SuppDelAdd5'] = $_SESSION['PO' . $identifier]->SuppDelAdd5;
	$_POST['SuppDelAdd6'] = $_SESSION['PO' . $identifier]->SuppDelAdd6;
	if(!isset($_POST['DeliveryDate'])){
		$_POST['DeliveryDate'] = $_SESSION['PO' . $identifier]->DeliveryDate;
	}

}

if (isset($_POST['Select'])) {
	
	/* will only be true if page called from supplier selection form or item purchasing data order link
	 * or set because only one supplier record returned from a search
	 */

	$sql = "SELECT suppliers.suppname,
					suppliers.supptype,
					suppliers.currcode,
					currencies.rate,
					currencies.decimalplaces,
					suppliers.paymentterms,
					suppliers.address1,
					suppliers.address2,
					suppliers.address3,
					suppliers.address4,
					suppliers.address5,
					suppliers.address6,
					suppliers.telephone,
					suppliers.port
				FROM suppliers INNER JOIN currencies
				ON suppliers.currcode=currencies.currabrev
				WHERE supplierid='" . $_POST['Select'] . "'";

	$ErrMsg = _('The supplier record of the supplier selected') . ': ' . $_POST['Select'] . ' ' . _('cannot be retrieved because');
	$DbgMsg = _('The SQL used to retrieve the supplier details and failed was');
	$result = DB_query($sql, $ErrMsg, $DbgMsg);
	$myrow = DB_fetch_array($result);
	// added for suppliers lookup fields

	$AuthSql = "SELECT cancreate
				FROM purchorderauth
				WHERE userid='" . $_SESSION['UserID'] . "'
				AND currabrev='" . $myrow['currcode'] . "'";

	$AuthResult = DB_query($AuthSql);

	if (($AuthRow = DB_fetch_array($AuthResult) and $AuthRow['cancreate'] == 0)) {
		$_POST['SupplierName'] = $myrow['suppname'];
		$_POST['CurrCode'] = $myrow['currcode'];
		$_POST['CurrDecimalPlaces'] = $myrow['decimalplaces'];
		$_POST['ExRate'] = $myrow['rate'];
		$_POST['PaymentTerms'] = $myrow['paymentterms'];
		$_POST['SuppDelAdd1'] = $myrow['address1'];
		$_POST['SuppDelAdd2'] = $myrow['address2'];
		$_POST['SuppDelAdd3'] = $myrow['address3'];
		$_POST['SuppDelAdd4'] = $myrow['address4'];
		$_POST['SuppDelAdd5'] = $myrow['address5'];
		$_POST['SuppDelAdd6'] = $myrow['address6'];
		$_POST['SuppTel'] = $myrow['telephone'];
		$_POST['Port'] = $myrow['port'];
		$_POST['Supptype'] = $myrow['supptype'];
		

		$_SESSION['PO' . $identifier]->SupplierID = $_POST['Select'];
		$_SESSION['RequireSupplierSelection'] = 0;
		$_SESSION['PO' . $identifier]->SupplierName = $_POST['SupplierName'];
		$_SESSION['PO' . $identifier]->CurrCode = $_POST['CurrCode'];
		$_SESSION['PO' . $identifier]->CurrDecimalPlaces = $_POST['CurrDecimalPlaces'];
		$_SESSION['PO' . $identifier]->ExRate = $_POST['ExRate'];
		$_SESSION['PO' . $identifier]->PaymentTerms = $_POST['PaymentTerms'];
		$_SESSION['PO' . $identifier]->SuppDelAdd1 = $_POST['SuppDelAdd1'];
		$_SESSION['PO' . $identifier]->SuppDelAdd2 = $_POST['SuppDelAdd2'];
		$_SESSION['PO' . $identifier]->SuppDelAdd3 = $_POST['SuppDelAdd3'];
		$_SESSION['PO' . $identifier]->SuppDelAdd4 = $_POST['SuppDelAdd4'];
		$_SESSION['PO' . $identifier]->SuppDelAdd5 = $_POST['SuppDelAdd5'];
		$_SESSION['PO' . $identifier]->SuppDelAdd6 = $_POST['SuppDelAdd6'];
		$_SESSION['PO' . $identifier]->SuppTel = $_POST['SuppTel'];
		$_SESSION['PO' . $identifier]->Port = $_POST['Port'];
		$_SESSION['PO' . $identifier]->Supptype = $_POST['Supptype'];
		

	} else {

		prnMsg(_('You do not have the authority to raise Purchase Orders for') . ' ' . $myrow['suppname'] . '. ' . _('Please Consult your system administrator for more information.') . '<br />' . _('You can setup authorisations') . ' ' . '<a href="PO_AuthorisationLevels.php">' . _('here') . '</a>', 'warn');
		include('includes/footer.inc');
		exit;
	}

	// end of added for suppliers lookup fields

} /* isset($_POST['Select'])  will only be true if page called from supplier selection form or item purchasing data order link
   * or set because only one supplier record returned from a search
   */
else {
	$_POST['Select'] = $_SESSION['PO' . $identifier]->SupplierID;
	$sql = "SELECT suppliers.suppname,
					suppliers.currcode,
					currencies.decimalplaces,
					suppliers.paymentterms,
					suppliers.address1,
					suppliers.address2,
					suppliers.address3,
					suppliers.address4,
					suppliers.address5,
					suppliers.address6,
					suppliers.telephone,
					suppliers.port
			FROM suppliers INNER JOIN currencies
			ON suppliers.currcode=currencies.currabrev
			WHERE supplierid='" . $_POST['Select'] . "'";

	$ErrMsg = _('The supplier record of the supplier selected') . ': ' . $_POST['Select'] . ' ' . _('cannot be retrieved because');
	$DbgMsg = _('The SQL used to retrieve the supplier details and failed was');
	$result = DB_query($sql, $ErrMsg, $DbgMsg);

	$myrow = DB_fetch_array($result);

	// added for suppliers lookup fields
	if (!isset($_SESSION['PO' . $identifier])) {
		$_POST['SupplierName'] = $myrow['suppname'];
		$_POST['CurrCode'] = $myrow['currcode'];
		$_POST['CurrDecimalPlaces'] = $myrow['decimalplaces'];
		$_POST['ExRate'] = $myrow['rate'];
		$_POST['PaymentTerms'] = $myrow['paymentterms'];
		$_POST['SuppDelAdd1'] = $myrow['address1'];
		$_POST['SuppDelAdd2'] = $myrow['address2'];
		$_POST['SuppDelAdd3'] = $myrow['address3'];
		$_POST['SuppDelAdd4'] = $myrow['address4'];
		$_POST['SuppDelAdd5'] = $myrow['address5'];
		$_POST['SuppDelAdd6'] = $myrow['address6'];
		$_POST['SuppTel'] = $myrow['telephone'];
		$_POST['Port'] = $myrow['port'];


		$_SESSION['PO' . $identifier]->SupplierID = $_POST['Select'];
		$_SESSION['RequireSupplierSelection'] = 0;
		$_SESSION['PO' . $identifier]->SupplierName = $_POST['SupplierName'];
		$_SESSION['PO' . $identifier]->CurrCode = $_POST['CurrCode'];
		$_SESSION['PO' . $identifier]->CurrDecimalPlaces = $_POST['CurrDecimalPlaces'];
		$_SESSION['PO' . $identifier]->ExRate = filter_number_format($_POST['ExRate']);
		$_SESSION['PO' . $identifier]->PaymentTerms = $_POST['PaymentTerms'];
		$_SESSION['PO' . $identifier]->SuppDelAdd1 = $_POST['SuppDelAdd1'];
		$_SESSION['PO' . $identifier]->SuppDelAdd2 = $_POST['SuppDelAdd2'];
		$_SESSION['PO' . $identifier]->SuppDelAdd3 = $_POST['SuppDelAdd3'];
		$_SESSION['PO' . $identifier]->SuppDelAdd4 = $_POST['SuppDelAdd4'];
		$_SESSION['PO' . $identifier]->SuppDelAdd5 = $_POST['SuppDelAdd5'];
		$_SESSION['PO' . $identifier]->SuppDelAdd6 = $_POST['SuppDelAdd6'];
		$_SESSION['PO' . $identifier]->SuppTel = $_POST['SuppTel'];
		$_SESSION['PO' . $identifier]->Port = $_POST['Port'];
		// end of added for suppliers lookup fields
	}
} // NOT isset($_POST['Select']) - not called with supplier selection so update variables

// part of step 1
if ($_SESSION['RequireSupplierSelection'] == 1 OR !isset($_SESSION['PO' . $identifier]->SupplierID) OR $_SESSION['PO' . $identifier]->SupplierID == '') {
	echo '<p class="page_title_text"><img src="' . $RootPath . '/css/' . $Theme . '/images/supplier.png" title="' . _('Purchase Order') . '" alt="" />' . ' ' . _('Purchase Order: Select Supplier') . '</p>';
	echo '<form action="' . htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8') . '?identifier=' . $identifier . '" method="post" id="choosesupplier">';
	echo '<div>';
	echo '<input type="hidden" name="FormID" value="' . $_SESSION['FormID'] . '" />';

	if (isset($SuppliersReturned)) {
		echo '<input type="hidden" name="SuppliersReturned" value="' . $SuppliersReturned . '" />';
	}

	echo '<table cellpadding="3" class="selection">
	<tr>
		<td>' . _('Enter text in the supplier name') . ':</td>
		<td><input type="text" name="Keywords" autofocus="autofocus" size="20" maxlength="25" /></td>
		<td><h3><b>' . _('OR') . '</b></h3></td>
		<td>' . _('Enter text extract in the supplier code') . ':</td>
		<td><input type="text" name="SuppCode" size="15" maxlength="18" /></td>
	</tr>
	</table>
	<br />
	<div class="centre">
	<input type="submit" name="SearchSuppliers" value="' . _('Search Now') . '" />
	<input type="submit" value="' . _('Reset') . '" /></div>';

	if (isset($result_SuppSelect)) {
		echo '<br /><table cellpadding="3" class="selection">';

		echo '<tr>
				<th class="ascending">' . _('Code') . '</th>
				<th class="ascending">' . _('Supplier Name') . '</th>
				<th class="ascending">' . _('Address') . '</th>
				<th class="ascending">' . _('Currency') . '</th>
			</tr>';
		$j = 1;
		$k = 0;
		/*row counter to determine background colour */

		while ($myrow = DB_fetch_array($result_SuppSelect)) {
			if ($k == 1) {
				echo '<tr class="EvenTableRows">';
				$k = 0;
			} else {
				echo '<tr class="OddTableRows">';
				$k++;
			}

			echo '<td><input type="submit" style="width:100%" name="Select" value="' . $myrow['supplierid'] . '" /></td>
				<td>' . $myrow['suppname'] . '</td><td>';

			for ($i = 1; $i <= 6; $i++) {
				if ($myrow['address' . $i] != '') {
					echo $myrow['address' . $i] . '<br />';
				}
			}
			echo '</td>
					<td>' . $myrow['currcode'] . '</td>
				</tr>';

			//end of page full new headings if
		} //end of while loop

		echo '</table>';

	}
	//end if results to show

	//end if RequireSupplierSelection
} else {
	/* everything below here only do if a supplier is selected */

	echo '<form id="form1" action="' . htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8') . '?identifier=' . $identifier . '" method="post">';
	echo '<div>';
	echo '<input type="hidden" name="FormID" value="' . $_SESSION['FormID'] . '" />';

	echo '<p class="page_title_text">
			<img src="' . $RootPath . '/css/' . $Theme . '/images/supplier.png" title="' . _('Purchase Order') . '" alt="" />
			' . $_SESSION['PO' . $identifier]->SupplierName . ' - ' . _('All amounts stated in') . '
			' . $_SESSION['PO' . $identifier]->CurrCode . '</p>';

	if ($_SESSION['ExistingOrder']) {
		/*echo _(' Modify Purchase Order Number') . ' ' . $_SESSION['PO' . $identifier]->OrderNo;*/
		echo _(' Modify Purchase Order Number') . ' ' . $_SESSION['PO' . $identifier]->Ponumb;
	}

	if (isset($Purch_Item)) {
		/*This is set if the user hits the link from the supplier purchasing info shown on SelectProduct.php */
		prnMsg(_('Purchase Item(s) with this code') . ': ' . $Purch_Item, 'info');

		echo '<div class="centre">';
		echo '<br />
				<table class="table_index">
				<tr>
					<td class="menu_group_item">';

		/* the link */
		echo '<a href="' . $RootPath . '/PO_Items.php?NewItem=' . $Purch_Item . '&identifier=' . $identifier . '">' . _('Enter Line Item to this purchase order') . '</a>';

		echo '</td>
			</tr>
			</table>
			</div>
			<br />';

		if (isset($_GET['Quantity'])) {
			$Qty = $_GET['Quantity'];
		} else {
			$Qty = 1;
		}

		$sql = "SELECT stockmaster.controlled,
						stockmaster.serialised,
						stockmaster.description,
						stockmaster.units ,
						stockmaster.decimalplaces,
						purchdata.price,
						purchdata.suppliersuom,
						purchdata.suppliers_partno,
						purchdata.conversionfactor,
						purchdata.leadtime,
						stockcategory.stockact
				FROM stockmaster INNER JOIN stockcategory
					ON stockmaster.categoryid=stockcategory.categoryid
				LEFT JOIN purchdata
					ON stockmaster.stockid = purchdata.stockid
				WHERE stockmaster.stockid='" . $Purch_Item . "'
				AND purchdata.supplierno ='" . $_GET['SelectedSupplier'] . "'";
		$result = DB_query($sql);
		$PurchItemRow = DB_fetch_array($result);

		if (!isset($PurchItemRow['conversionfactor'])) {
			$PurchItemRow['conversionfactor'] = 1;
		}

		if (!isset($PurchItemRow['leadtime'])) {
			$PurchItemRow['leadtime'] = 1;
		}

		$_SESSION['PO' . $identifier]->add_to_order(1,
													$Purch_Item,
													$PurchItemRow['serialised'],
													$PurchItemRow['controlled'],
													$Qty * $PurchItemRow['conversionfactor'],
													$PurchItemRow['description'],
													$PurchItemRow['price'] / $PurchItemRow['conversionfactor'],
													$PurchItemRow['units'],
													$PurchItemRow['stockact'],
													$_SESSION['PO' . $identifier]->DeliveryDate, 0,
													0,
													'',
													0,
													0,
													'',
													$PurchItemRow['decimalplaces'],
													$PurchItemRow['suppliersuom'],
													$PurchItemRow['conversionfactor'],
													$PurchItemRow['leadtime'],
													$PurchItemRow['suppliers_partno']);

		echo '<meta http-equiv="refresh" content="0; url=' . $RootPath . '/PO_Items.php?identifier=' . $identifier . '">';
	}

	/*Set up form for entry of order header stuff */

	if (!isset($_POST['LookupDeliveryAddress']) and (!isset($_POST['StkLocation']) or $_POST['StkLocation']) AND (isset($_SESSION['PO' . $identifier]->Location) AND $_SESSION['PO' . $identifier]->Location != '')) {
		/* The session variables are set but the form variables have
		 * been lost --
		 * need to restore the form variables from the session */
		$_POST['StkLocation'] = $_SESSION['PO' . $identifier]->Location;
		$_POST['SupplierContact'] = $_SESSION['PO' . $identifier]->SupplierContact;
		$_POST['DelAdd1'] = $_SESSION['PO' . $identifier]->DelAdd1;
		$_POST['DelAdd2'] = $_SESSION['PO' . $identifier]->DelAdd2;
		$_POST['DelAdd3'] = $_SESSION['PO' . $identifier]->DelAdd3;
		$_POST['DelAdd4'] = $_SESSION['PO' . $identifier]->DelAdd4;
		$_POST['DelAdd5'] = $_SESSION['PO' . $identifier]->DelAdd5;
		$_POST['DelAdd6'] = $_SESSION['PO' . $identifier]->DelAdd6;
		$_POST['Initiator'] = $_SESSION['PO' . $identifier]->Initiator;
		$_POST['Requisition'] = $_SESSION['PO' . $identifier]->RequisitionNo;
		$_POST['Version'] = $_SESSION['PO' . $identifier]->Version;
		$_POST['DeliveryDate'] = $_SESSION['PO' . $identifier]->DeliveryDate;
		$_POST['Revised'] = $_SESSION['PO' . $identifier]->Revised;
		$_POST['ExRate'] = $_SESSION['PO' . $identifier]->ExRate;
		$_POST['Comments'] = $_SESSION['PO' . $identifier]->Comments;
		$_POST['DeliveryBy'] = $_SESSION['PO' . $identifier]->DeliveryBy;
		$_POST['PaymentTerms'] = $_SESSION['PO' . $identifier]->PaymentTerms;
		
		$sql = "SELECT realname FROM www_users WHERE userid='" . $_POST['Initiator'] . "'";
		$result = DB_query($sql);
		$myrow = DB_fetch_array($result);
		$_POST['InitiatorName'] = $myrow['realname'];
	}

	echo '<br />
		<table width="80%">
		<tr>
			<th><h3>' . _('Order Initiation Details') . '</h3></th>
			<th><h3>' . _('Order Status') . '</h3></th>
		</tr>
		<tr><td style="width:50%">';
	//sub table starts
	echo '<table class="selection" width="100%">';
	
	echo '<tr>
			<td>' . _('PO Date') . ':</td>
			<td>';
	if ($_SESSION['ExistingOrder'] != 0) {
		echo ConvertSQLDate($_SESSION['PO' . $identifier]->Orig_OrderDate);
	} else {
		/* DefaultDateFormat defined in config.php */
		echo Date($_SESSION['DefaultDateFormat']);
	}
	echo '</td></tr>';
	
	//KL ISSET MODIFY ORDER NUMBER,BERARTI EDIT PO,AMBIL DATANYA DARI PO_READINORDER.INC,SESSION DISET DIFILE ITU
	if (isset($_GET['ModifyOrderNumber']) AND $_GET['ModifyOrderNumber'] != '') {
		$ponumb=$_SESSION['PO' . $identifier]->Ponumb;
		$_POST['Insurance']= $_SESSION['PO' . $identifier]->Insurance;
		$_POST['Port']= $_SESSION['PO' . $identifier]->Port;
		$_POST['ShipmentTermsName']= $_SESSION['PO' . $identifier]->ShipmentTermsName;
		$_POST['CountryOrigin']= $_SESSION['PO' . $identifier]->CountryOrigin;
		$_SESSION['PO' . $identifier]->Version += 1;
		$_POST['Version'] = $_SESSION['PO' . $identifier]->Version;
	} elseif (isset($_SESSION['PO' . $identifier]->Version) AND $_SESSION['PO' . $identifier]->Version != '') {
		$_POST['Version'] = $_SESSION['PO' . $identifier]->Version;
	} else {
		$_POST['Version'] = '1';
	}

	if (!isset($_POST['DeliveryDate'])) {
		$_POST['DeliveryDate'] = date($_SESSION['DefaultDateFormat']);
	}
echo '<tr>
			<td>'."PO Number : </td><td>".'';
			$tgl=Date($_SESSION['DefaultDateFormat']);
			$tgl=explode('/',$tgl);
			if($_POST['Supptype']==1 && !isset($_GET['ModifyOrderNumber']))
			{
				//AMBIL TOTAL PURCHORDER IMPORT
				$sql='SELECT COUNT(*) as total FROM `suppliers` sup,purchorders purch WHERE sup.supplierid=purch.supplierno AND MID(purch.orddate,1,4)='."'".$tgl[2]."'".' AND sup.supptype=1';
				
				$sql1 ='SELECT COUNT(*) as total FROM `suppliers` sup,purchorders purch WHERE sup.supplierid=purch.supplierno AND sup.supptype=1';

				$jumlah = DB_query($sql);
				$Row = DB_fetch_array($jumlah);
				$totalbaru=$Row['total']+1;
				$ponumb="PO/TU-IM/".substr($tgl[2],2)."/".ConverToRoman($tgl[1])."/".$totalbaru;
				//PENGECEKAN APAKAH MODIFYORDER
			}
			else if($_POST['Supptype']==2 && !isset($_GET['ModifyOrderNumber'])){
				//AMBIL TOTAL PURCHORDER LOKAL
				$sql='SELECT COUNT(*) as total FROM `suppliers` sup,purchorders purch WHERE sup.supplierid=purch.supplierno AND MID(purch.orddate,1,4)='."'".$tgl[2]."'".' AND sup.supptype=2';
				
				$sql1 ='SELECT COUNT(*) as total FROM `suppliers` sup,purchorders purch WHERE sup.supplierid=purch.supplierno AND sup.supptype=2';
				
				$jumlah = DB_query($sql);
				$Row = DB_fetch_array($jumlah);
				$totalbaru=$Row['total']+1;
				$ponumb="PO/TU-L/".substr($tgl[2],2)."/".ConverToRoman($tgl[1])."/".$totalbaru;
			}
			//PO NUMBER DAPET DR ISSET MODIFY ORDER ATAU ISSET MODIFYNUMBER (DIDEFINISIIN DI ATAS SEBELUMNYA)
			echo '<input type="hidden" name="Ponumb" value="'.$ponumb.'" />'.$ponumb.'</td>';
		echo '</tr>';
	echo '<tr>
			<td>' . _('Version') . ' #' . ':</td>
			<td><input type="hidden" name="Version" size="16" maxlength="15" value="' . $_POST['Version'] . '" />' . $_POST['Version'] . '</td>
		</tr>
		<tr>
			<td>' . _('Revised') . ':</td>
			<td><input type="hidden" name="Revised" size="11" maxlength="15" value="' . date($_SESSION['DefaultDateFormat']) . '" />' . date($_SESSION['DefaultDateFormat']) . '</td>
		</tr>';
		

	if (!isset($_POST['Initiator'])) {
		$_POST['Initiator'] = $_SESSION['UserID'];
		$_POST['InitiatorName'] = $_SESSION['UsersRealName'];
		$_POST['Requisition'] = '';
	}
	if (!isset($_POST['InitiatorName'])){
		$_POST['InitiatorName'] = $_SESSION['UsersRealName'];
	}

	echo '<tr>
			<td>' . _('Initiated By') . ':</td>
			<td><input type="hidden" name="Initiator" size="11" maxlength="10" value="' . $_POST['Initiator'] . '" />' . $_POST['InitiatorName'] . '</td>
		</tr>
		<tr>
			<td>' . _('Requisition Ref') . ':</td>
			<td><input type="text" name="Requisition" size="16" maxlength="15" title="' . _('Enter our purchase requisition reference if needed') . '" value="' . $_POST['Requisition'] . '" /></td>
		</tr>
		<tr>
			<td>' . _('Date Printed') . ':</td>
			<td>';

	if (isset($_SESSION['PO' . $identifier]->DatePurchaseOrderPrinted) AND mb_strlen($_SESSION['PO' . $identifier]->DatePurchaseOrderPrinted) > 6) {
		echo ConvertSQLDate($_SESSION['PO' . $identifier]->DatePurchaseOrderPrinted);
		$Printed = True;
	} else {
		$Printed = False;
		echo _('Not yet printed') . '</td></tr>';
	}

	if (isset($_POST['AllowRePrint'])) {
		$sql = "UPDATE purchorders SET allowprint=1 WHERE orderno='" . $_SESSION['PO' . $identifier]->OrderNo . "'";
		$result = DB_query($sql);
	}

	if ($_SESSION['PO' . $identifier]->AllowPrintPO == 0 AND empty($_POST['RePrint'])) {
		echo '<tr>
				<td>' . _('Allow Reprint') . ':</td>
				<td><select name="RePrint" onchange="ReloadForm(form1.AllowRePrint)">
					<option selected="selected" value="0">' . _('No') . '</option>
					<option value="1">' . _('Yes') . '</option>
				</select></td>';
		echo '<td><input type="submit" name="AllowRePrint" value="Update" /></td></tr>';
	} elseif ($Printed) {
		echo '<tr>
				<td colspan="2"><a target="_blank"  href="' . $RootPath . '/PO_PDFPurchOrder.php?OrderNo=' . $_SESSION['ExistingOrder'] . '&amp;identifier=' . $identifier . '">' . _('Reprint Now') . '</a></td></tr>';
	} //$Printed

	echo '</table></td>';
	//Set up the next column with a sub-table in it too
	echo '<td style="width:50%" valign="top">
            <table class="selection" width="100%">';

	if ($_SESSION['ExistingOrder'] != 0 AND $_SESSION['PO' . $identifier]->Status == 'Printed') {
		echo '<tr>
				<td><a href="' . $RootPath . '/GoodsReceived.php?PONumber=' . $_SESSION['PO' . $identifier]->OrderNo . '&amp;identifier=' . $identifier . '">' . _('Receive this order') . '</a></td>
			</tr>';
	}

	if ($_SESSION['PO' . $identifier]->Status == '') { //then its a new order
		echo '<tr>
				<td><input type="hidden" name="Status" value="NewOrder" />' . _('New Purchase Order') . '</td>
			</tr>';
	} else {
		echo '<tr>
				<td>' . _('Status') . ' :  </td>
				<td><select name="Status" onchange="ReloadForm(form1.UpdateStatus)">';

		switch ($_SESSION['PO' . $identifier]->Status) {
			case 'Pending':
				echo '<option selected="selected" value="Pending">' . _('Pending') . '</option>
						<option value="Authorised">' . _('Authorised') . '</option>
						<option value="Rejected">' . _('Rejected') . '</option>';
				break;
			case 'Authorised':
				echo '<option value="Pending">' . _('Pending') . '</option>
						<option selected="selected" value="Authorised">' . _('Authorised') . '</option>
						<option value="Cancelled">' . _('Cancelled') . '</option>';
				break;
			case 'Printed':
				echo '<option value="Pending">' . _('Pending') . '</option>
						<option selected="selected" value="Printed">' . _('Printed') . '</option>
						<option value="Cancelled">' . _('Cancelled') . '</option>';
				break;
			case 'Completed':
				echo '<option selected="selected" value="Completed">' . _('Completed') . '</option>';
				break;
			case 'Rejected':
				echo '<option selected="selected" value="Rejected">' . _('Rejected') . '</option>
						<option value="Pending">' . _('Pending') . '</option>
						<option value="Authorised">' . _('Authorised') . '</option>';
				break;
			case 'Cancelled':
				echo '<option selected="selected" value="Cancelled">' . _('Cancelled') . '</option>
						<option value="Authorised">' . _('Authorised') . '</option>
						<option value="Pending">' . _('Pending') . '</option>';
				break;
		}
		echo '</select></td>
			</tr>
			<tr>
				<td>' . _('Status Comment') . ':</td>
				<td><input type="text" name="StatusComments" size="50" /></td>
			</tr>
			<tr>
				<td colspan="2">' . html_entity_decode($_SESSION['PO' . $identifier]->StatusComments, ENT_QUOTES, 'UTF-8') . '</td>
			</tr>
			<input type="hidden" name="StatusCommentsComplete" value="' . htmlspecialchars($_SESSION['PO' . $identifier]->StatusComments, ENT_QUOTES, 'UTF-8') . '" />
			<tr>
				<td><input type="submit" name="UpdateStatus" value="' . _('Status Update') . '" /></td>
			</tr>';
	} //end its not a new order
		/*<th><h3>' . _('Delivery To') . '</h3></th>*/
	echo '</table></td>
		</tr>
		<tr>
			<th><h3>' . _('Warehouse Info') . '</h3></th>
			<th><h3>' . _('Supplier Info') . '</h3></th>
		</tr>
		<tr><td valign="top">';
	/*nested table level1 */

	echo '<table class="selection" width="100%">
			<tr>
				<td>' . _('Warehouse') . ':</td>
				<td><select required="required" name="StkLocation" onchange="ReloadForm(form1.LookupDeliveryAddress)">';

	$sql = "SELECT locations.loccode,
					locationname
			FROM locations INNER JOIN locationusers ON locationusers.loccode=locations.loccode AND locationusers.userid='" .  $_SESSION['UserID'] . "' AND locationusers.canupd=1";
	$LocnResult = DB_query($sql);

	while ($LocnRow = DB_fetch_array($LocnResult)) {
		if (isset($_POST['StkLocation']) AND ($_POST['StkLocation'] == $LocnRow['loccode']) OR (empty($_POST['StkLocation']) AND $LocnRow['loccode'] == $_SESSION['UserStockLocation'])) {
			echo '<option selected="selected" value="' . $LocnRow['loccode'] . '">' . $LocnRow['locationname'] . '</option>';
		} else {
			echo '<option value="' . $LocnRow['loccode'] . '">' . $LocnRow['locationname'] . '</option>';
		}
	}

	echo '</select>
		<input type="submit" name="LookupDeliveryAddress" value="' . _('Select') . '" /></td>
		</tr>';

	/* If this is the first time
	 * the form loaded set up defaults */

	if (!isset($_POST['StkLocation']) OR $_POST['StkLocation'] == '') {
		$_POST['StkLocation'] = $_SESSION['UserStockLocation'];

		$sql = "SELECT deladd1,
			 			deladd2,
						deladd3,
						deladd4,
						deladd5,
						deladd6,
						tel,
						contact
					FROM locations
					WHERE loccode='" . $_POST['StkLocation'] . "'";

		$LocnAddrResult = DB_query($sql);
		if (DB_num_rows($LocnAddrResult) == 1) {
			$LocnRow = DB_fetch_array($LocnAddrResult);
			$_POST['DelAdd1'] = $LocnRow['deladd1'];
			$_POST['DelAdd2'] = $LocnRow['deladd2'];
			$_POST['DelAdd3'] = $LocnRow['deladd3'];
			$_POST['DelAdd4'] = $LocnRow['deladd4'];
			$_POST['DelAdd5'] = $LocnRow['deladd5'];
			$_POST['DelAdd6'] = $LocnRow['deladd6'];
			$_POST['Tel'] = $LocnRow['tel'];
			$_POST['Contact'] = $LocnRow['contact'];

			$_SESSION['PO' . $identifier]->Location = $_POST['StkLocation'];
			$_SESSION['PO' . $identifier]->DelAdd1 = $_POST['DelAdd1'];
			$_SESSION['PO' . $identifier]->DelAdd2 = $_POST['DelAdd2'];
			$_SESSION['PO' . $identifier]->DelAdd3 = $_POST['DelAdd3'];
			$_SESSION['PO' . $identifier]->DelAdd4 = $_POST['DelAdd4'];
			$_SESSION['PO' . $identifier]->DelAdd5 = $_POST['DelAdd5'];
			$_SESSION['PO' . $identifier]->DelAdd6 = $_POST['DelAdd6'];
			$_SESSION['PO' . $identifier]->Tel = $_POST['Tel'];
			$_SESSION['PO' . $identifier]->Contact = $_POST['Contact'];

		} //end a location record was returned
		else {
			/*The default location of the user is crook */
			prnMsg(_('The default stock location set up for this user is not a currently defined stock location') . '. ' . _('Your system administrator needs to amend your user record'), 'error');
		}


	} //end StkLocation was not set
	elseif (isset($_POST['LookupDeliveryAddress'])) {
		$sql = "SELECT deladd1,
						deladd2,
						deladd3,
						deladd4,
						deladd5,
						deladd6,
						tel,
						contact
					FROM locations
					WHERE loccode='" . $_POST['StkLocation'] . "'";

		$LocnAddrResult = DB_query($sql);
		if (DB_num_rows($LocnAddrResult) == 1) {
			$LocnRow = DB_fetch_array($LocnAddrResult);
			$_POST['DelAdd1'] = $LocnRow['deladd1'];
			$_POST['DelAdd2'] = $LocnRow['deladd2'];
			$_POST['DelAdd3'] = $LocnRow['deladd3'];
			$_POST['DelAdd4'] = $LocnRow['deladd4'];
			$_POST['DelAdd5'] = $LocnRow['deladd5'];
			$_POST['DelAdd6'] = $LocnRow['deladd6'];
			$_POST['Tel'] = $LocnRow['tel'];
			$_POST['Contact'] = $LocnRow['contact'];

			$_SESSION['PO' . $identifier]->Location = $_POST['StkLocation'];
			$_SESSION['PO' . $identifier]->DelAdd1 = $_POST['DelAdd1'];
			$_SESSION['PO' . $identifier]->DelAdd2 = $_POST['DelAdd2'];
			$_SESSION['PO' . $identifier]->DelAdd3 = $_POST['DelAdd3'];
			$_SESSION['PO' . $identifier]->DelAdd4 = $_POST['DelAdd4'];
			$_SESSION['PO' . $identifier]->DelAdd5 = $_POST['DelAdd5'];
			$_SESSION['PO' . $identifier]->DelAdd6 = $_POST['DelAdd6'];
			$_SESSION['PO' . $identifier]->Tel = $_POST['Tel'];
			$_SESSION['PO' . $identifier]->Contact = $_POST['Contact'];
		} //There was a location record returned
	} //user clicked  Lookup Delivery Address


	echo '<tr>
			<td>' . _('Delivery Contact') . ':</td>
			<td><input type="text" name="Contact" size="41"  title="' . _('Enter the name of the contact at the delivery address - normally our warehouse person at that warehouse') .  '" value="' . $_SESSION['PO' . $identifier]->Contact . '" /></td>
		</tr>
		<tr>
			<td>' . _('Address') . ' 1 :</td>
			<td><input type="text" name="DelAdd1" size="41" maxlength="40" value="' . $_POST['DelAdd1'] . '" /></td>
		</tr>
		<tr>
			<td>' . _('Address') . ' 2 :</td>
			<td><input type="text" name="DelAdd2" size="41" maxlength="40" value="' . $_POST['DelAdd2'] . '" /></td>
		</tr>
		<tr>
			<td>' . _('Address') . ' 3 :</td>
			<td><input type="text" name="DelAdd3" size="41" maxlength="40" value="' . $_POST['DelAdd3'] . '" /></td>
		</tr>
		<tr>
			<td>' . _('Address') . ' 4 :</td>
			<td><input type="text" name="DelAdd4" size="41" maxlength="40" value="' . $_POST['DelAdd4'] . '" /></td>
		</tr>
		<tr>
			<td>' . _('Address') . ' 5 :</td>
			<td><input type="text" name="DelAdd5" size="21" maxlength="20" value="' . $_POST['DelAdd5'] . '" /></td>
		</tr>
		<tr>
			<td>' . _('Country  ') . '  :</td>
			<td><input type="text" name="DelAdd6" size="16" maxlength="15" value="' . $_POST['DelAdd6'] . '" /></td>
		</tr>
		<tr>
			<td>' . _('Phone') . ':</td>
			<td><input type="tel" name="Tel" pattern="[0-9+\-\s()]*" size="31" maxlength="30" value="' . $_SESSION['PO' . $identifier]->Tel . '" /></td>
		</tr>
		<tr>
			<td>' . _('Delivery By') . ':</td>
			<td><select name="DeliveryBy">';

	$ShipperResult = DB_query("SELECT shipper_id, shippername FROM shippers");

	while ($ShipperRow = DB_fetch_array($ShipperResult)) {
		if (isset($_POST['DeliveryBy']) and ($_POST['DeliveryBy'] == $ShipperRow['shipper_id'])) {
			echo '<option selected="selected" value="' . $ShipperRow['shipper_id'] . '">' . $ShipperRow['shippername'] . '</option>';
		} else {
			echo '<option value="' . $ShipperRow['shipper_id'] . '">' . $ShipperRow['shippername'] . '</option>';
		}
	}

	echo '</select></td>
		</tr>
		</table>';
	/* end of sub table */

	echo '</td><td>';
	/*sub table nested */
	echo '<table class="selection" width="100%">
			<tr>
				<td>' . _('Supplier Selection') . ':</td>
				<td><select name="Keywords" onchange="ReloadForm(form1.SearchSuppliers)">';

	$SuppCoResult = DB_query("SELECT supplierid, suppname FROM suppliers ORDER BY suppname");

	while ($SuppCoRow = DB_fetch_array($SuppCoResult)) {
		if ($SuppCoRow['suppname'] == $_SESSION['PO' . $identifier]->SupplierName) {
			echo '<option selected="selected" value="' . $SuppCoRow['suppname'] . '">' . $SuppCoRow['suppname'] . '</option>';
		} else {
			echo '<option value="' . $SuppCoRow['suppname'] . '">' . $SuppCoRow['suppname'] . '</option>';
		}
	}

	echo '</select> ';
	echo '<input type="submit" name="SearchSuppliers" value="' . _('Select Now') . '" /></td>
		</tr>';

	echo '<tr>
				<td>' . _('Supplier Contact') . ':</td>
				<td><select name="SupplierContact">';

	$sql = "SELECT contact FROM suppliercontacts WHERE supplierid='" . $_POST['Select'] . "'";
	$SuppCoResult = DB_query($sql);

	while ($SuppCoRow = DB_fetch_array($SuppCoResult)) {
		if ($_POST['SupplierContact'] == $SuppCoRow['contact'] OR ($_POST['SupplierContact'] == '' AND $SuppCoRow['contact'] == $_SESSION['PO' . $identifier]->SupplierContact)) {
			echo '<option selected="selected" value="' . $SuppCoRow['contact'] . '">' . $SuppCoRow['contact'] . '</option>';
		} else {
			echo '<option value="' . $SuppCoRow['contact'] . '">' . $SuppCoRow['contact'] . '</option>';
		}
	}

	echo '</select> </td>
		</tr>
		<tr>
			<td>' . _('Address') . ' 1 :</td>
			<td><input type="text" name="SuppDelAdd1" size="41" maxlength="40" value="' . $_POST['SuppDelAdd1'] . '" /></td>
		</tr>
		<tr>
			<td>' . _('Address') . ' 2 :</td>
			<td><input type="text" name="SuppDelAdd2" size="41" maxlength="40" value="' . $_POST['SuppDelAdd2'] . '" /></td>
		</tr>
		<tr>
			<td>' . _('Address') . ' 3 :</td>
			<td><input type="text" name="SuppDelAdd3" size="41" maxlength="40" value="' . $_POST['SuppDelAdd3'] . '" /></td>
		</tr>
		<tr>
			<td>' . _('Address') . ' 4 :</td>
			<td><input type="text" name="SuppDelAdd4" size="41" maxlength="40" value="' . $_POST['SuppDelAdd4'] . '" /></td>
		</tr>
		<tr>
			<td>' . _('Address') . ' 5 :</td>
			<td><input type="text" name="SuppDelAdd5" size="41" maxlength="20" value="' . $_POST['SuppDelAdd5'] . '" /></td>
		</tr>
		<tr>
			<td>' . _('Country') . '   :</td>
			<td><input type="text" name="SuppDelAdd5" size="16" maxlength="15" value="' . $_POST['SuppDelAdd6'] . '" /></td>
		</tr>
		<tr>
			<td>' . _('Phone') . ':</td>
			<td><input type="tel" name="SuppTel" pattern="[0-9+\-\s()]*" size="31" maxlength="30" value="' . $_SESSION['PO' . $identifier]->SuppTel . '" /></td>
		</tr>';

	// Term & Condition title section
		echo '<tr>
			<th><h3>' . _('Term and Condition') . '</h3></th>
		  </tr>';
	// end of Term & Condition title section
	
	/*  Shipment Terms */
	$result = DB_query("SELECT shipmenttermsname, shipmenttermsid FROM shipmentterms");
	echo '<tr>
			<td>' . _('Term Of Shipment') . ':</td>
			<td><select name="ShipmentTermsName">';
			
	while ($myrow = DB_fetch_array($result)) {
		if ($myrow['shipmenttermsid'] == $_SESSION['PO' . $identifier]->ShipmentTermsName) {
			echo '<option selected="selected" value="' . $myrow['shipmenttermsid'] . '">' . $myrow['shipmenttermsname'] . '</option>';
		} else {
			echo '<option value="' . $myrow['shipmenttermsid'] . '">' . $myrow['shipmenttermsname'] . '</option>';
		} //end while loop
	}
	DB_data_seek($result, 0);
	echo '</select></td></tr>';
	/* end for Shipment Terms */
	
	// Insurance section
	echo '<tr>
			<td>'._('Insurance') .':</td>
			<td><input id="Insurance" type="text" name="Insurance" value="'.$_POST['Insurance'].'"/></td>
		</tr>';
	// end of Insurance section
	
	// Country of Origin inputing section
	echo '<tr>
			<td>'._('Country of Origin') .':</td>
			<td><input id="CountryOrigin" type="text" name="CountryOrigin" value="'.$_POST['CountryOrigin'].'"/></td>
		</tr>';
	// end of Country of Origin inputing section
	
	// Shipment Date section
	echo '<tr>
			<td>' . _('Shipment Date') . ':</td>
			<td><input type="text" required="required" autofocus="autofocus" class="date" alt="' . $_SESSION['DefaultDateFormat'] . '" name="DeliveryDate" size="11" value="' . $_POST['DeliveryDate'] . '" /></td>
		</tr>';
	// end of Shipment Date section
	
	/*  Destination Port section*/
	$result = DB_query("SELECT loccode,
							locationname
						FROM locations WHERE loccode='" . $_SESSION['PO' . $identifier]->Port . "'");
	$myrow = DB_fetch_array($result);
	//$_POST['Port'] = $myrow['locationname'];

	echo '<tr>
			<td>' . _('Destination') . ':</td>
			<td><input id="Port" type="text" name="Port" value="'.$_POST['Port'].'" /></td>
		</tr>';
	/*  end of Destination Port section*/	
	
	// Term Of Payment section
	$result = DB_query("SELECT terms, termsindicator FROM paymentterms");

	echo '<tr>
			<td>' . _('Term Of Payment ') . ':</td>
			<td><select name="PaymentTerms">';

	while ($myrow = DB_fetch_array($result)) {
		if ($myrow['termsindicator'] == $_SESSION['PO' . $identifier]->PaymentTerms) {
			echo '<option selected="selected" value="' . $myrow['termsindicator'] . '">' . $myrow['terms'] . '</option>';
		} else {
			echo '<option value="' . $myrow['termsindicator'] . '">' . $myrow['terms'] . '</option>';
		} //end while loop
	}
	DB_data_seek($result, 0);
	echo '</select></td></tr>';
	// end of Term Of Payment section

	if ($_SESSION['PO' . $identifier]->CurrCode != $_SESSION['CompanyRecord']['currencydefault']) {
		echo '<tr><td>' . _('Exchange Rate') . ':' . '</td>
				<td><input type="text" name="ExRate" value="' . locale_number_format($_POST['ExRate'], 5) . '" class="number" size="11" /></td>
			</tr>';
	} else {
		echo '<tr>
				<td><input type="hidden" name="ExRate" value="1" /></td>
			</tr>';
	}
	echo '</table>';
	/*end of sub table*/

	echo '</td></tr>
			<tr>
				<th colspan="4"><h3>' . _('Comments');

	$Default_Comments = '';

	if (!isset($_POST['Comments'])) {
		$_POST['Comments'] = $Default_Comments;
	}

	echo ':</h3></th>
			</tr>
			<tr>
				<td colspan="4"><textarea name="Comments" style="width:100%" rows="5" cols="200">' . stripcslashes($_POST['Comments']) . '</textarea></td>
			</tr>
			</table>
			<br />';
	/* end of main table */

	echo '<div class="centre">
			<input type="submit" name="EnterLines" value="' . _('Enter Line Items') . '" />
		</div>';

}
/*end of if supplier selected */

echo '</div>
      </form>';
include('includes/footer.inc');
?>