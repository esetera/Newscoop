<?php
camp_load_translation_strings("plugin_interview");
?>
<html>
<head>
    <META http-equiv="Content-Type" content="text/html; charset=UTF-8">
	<META HTTP-EQUIV="Expires" CONTENT="now">
	<LINK rel="stylesheet" type="text/css" href="<?php echo $Campsite['WEBSITE_URL']; ?>/css/admin_stylesheet.css">
	<title><?php putGS("Edit Interview Item"); ?></title>
	<?php include_once($GLOBALS['g_campsiteDir']."/$ADMIN_DIR/javascript_common.php"); ?>
</head>
<body>
<?php

// User role depend on path to this file. Tricky: moderator and guest folders are just symlink to admin files!
if (strpos($call_script, '/interview/admin/') !== false && $g_user->hasPermission('plugin_interview_admin')) {
    $is_admin = true;
    $form_role = 'admin';   
}
if (strpos($call_script, '/interview/moderator/') !== false && $g_user->hasPermission('plugin_interview_moderator')) {
    $is_moderator = true;
    $form_role = 'moderator';   
}
if (strpos($call_script, '/interview/guest/') !== false && $g_user->hasPermission('plugin_interview_guest')) {
    $is_guest = true;
    $form_role = 'guest';   
}

// Check permissions
if (!$is_admin && !$is_moderator && !$is_guest) {
    camp_html_display_error(getGS('You do not have the right to manage interviews.'));
    exit;
}

$f_item_id = Input::Get('f_item_id', 'int');

if (!Input::IsValid()) {
    camp_html_display_error(getGS('Invalid input: $1', Input::GetErrorString()), $_SERVER['REQUEST_URI']);
    exit;
}

$InterviewItem = new InterviewItem(null, $f_item_id);
if ($InterviewItem->getId()==0){
    $InterviewItem->setInterviewId(Input::Get('f_interview_id','int',0));
    $questioneer = Input::Get('questioneer','int',0);
    if ($questioneer>0){
        $InterviewItem->setQuestioneerwId($questioneer);
    }
}
if ($InterviewItem->store()) {
    ?>
    <script language="javascript">
        window.opener.location.reload();
        window.close();
    </script>
    <?php
    exit();
}

?>
<table style="margin-top: 10px; margin-left: 15px; margin-right: 15px;" cellpadding="0" cellspacing="0" width="95%" class="table_input">
    <TR>
    	<TD style="padding: 3px";>
    		<B><?php putGS('Edit Interview Item') ?></B>
    		<hr style="color: #8baed1";>
    	</TD>
    </TR>
    <tr>
        <td>
            <?php p($InterviewItem->getForm($form_role, 'edit_item.php', array(), true)); ?>
        </td>
    </tr>
</table>
</body>
</html>
