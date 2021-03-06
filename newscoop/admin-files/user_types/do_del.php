<?php
require_once($GLOBALS['g_campsiteDir']. "/$ADMIN_DIR/user_types/utypes_common.php");

if (!SecurityToken::isValid()) {
    camp_html_display_error(getGS('Invalid security token!'));
    exit;
}

$canManage = $g_user->hasPermission('ManageUserTypes');
if (!$canManage) {
	$error = getGS("You do not have the right to delete user types.");
	camp_html_display_error($error);
	exit;
}

$uTypeId = Input::Get('UType', 'string', '');
if (is_numeric($uTypeId) && $uTypeId > 0) {
	$userType = new UserType($uTypeId);
	if (!$userType->exists()) {
		camp_html_display_error(getGS('No such user type.'));
		exit;
	}
	$userType->delete();
} else {
	camp_html_display_error(getGS('No such user type.'));
	exit;
}

$msg = getGS("User Type '$1' successfully deleted", $userType->getName());
camp_html_add_msg($msg, 'ok');
camp_html_goto_page("/$ADMIN/user_types/");

?>