<?php
/**
 * @package Campsite
 */

/**
 * Includes
 */
// We indirectly reference the DOCUMENT_ROOT so we can enable
// scripts to use this file from the command line, $_SERVER['DOCUMENT_ROOT']
// is not defined in these cases.
$g_documentRoot = $_SERVER['DOCUMENT_ROOT'];

require_once($g_documentRoot.'/db_connect.php');
require_once($g_documentRoot.'/conf/configuration.php');
require_once($g_documentRoot.'/classes/DatabaseObject.php');
require_once($g_documentRoot.'/classes/DbObjectArray.php');
require_once($g_documentRoot.'/classes/Log.php');
require_once($g_documentRoot."/$ADMIN_DIR/localizer/Localizer.php");

/**
 * @package Campsite
 */
class Language extends DatabaseObject {
	var $m_dbTableName = 'Languages';
	var $m_keyColumnNames = array('Id');
	var $m_keyIsAutoIncrement = true;
	var $m_columnNames = array('Id', 'Name', 'CodePage', 'OrigName',
	   'Code', 'Month1', 'Month2', 'Month3', 'Month4', 'Month5',
	   'Month6', 'Month7', 'Month8', 'Month9', 'Month10', 'Month11',
	   'Month12', 'WDay1', 'WDay2', 'WDay3', 'WDay4', 'WDay5', 'WDay6', 'WDay7' );

	/**
	 * Constructor.
	 * @param int $p_languageId
	 */
	public function Language($p_languageId = null)
	{
		parent::DatabaseObject($this->m_columnNames);
		if (!is_null($p_languageId)) {
    		$this->m_data['Id'] = $p_languageId;
			$this->fetch();
		}
	} // constructor


	/**
	 * Create the language.  Creates the directory on disk to store the
	 * translation files.
	 *
	 * @param array $p_values
	 * @return mixed
	 * 		Return TRUE on success and PEAR_Error on failure.
	 */
	public function create($p_values = null)
	{
		$success = parent::create($p_values);
		if ($success) {
	    	$result = Localizer::CreateLanguageFiles($this->m_data['Code']);
	    	if (PEAR::isError($result)) {
	    		$this->delete(false);
	    		return $result;
	    	}
	    	Language::CreateLanguageLinks();
			if (function_exists("camp_load_translation_strings")) {
				camp_load_translation_strings("api");
			}
	        $logtext = getGS('Language $1 added', $this->m_data['Name']." (".$this->m_data['OrigName'].")");
	        Log::Message($logtext, null, 101);
		}
		return $success;
	} // fn create


	/**
	 * Update the language.
	 *
	 * @param array $p_values
	 * @param boolean $p_commit
	 * @param boolean $p_isSql
	 * @return boolean
	 */
	public function update($p_values = null, $p_commit = true, $p_isSql = false)
	{
		$success = parent::update($p_values, $p_commit, $p_isSql);
		if (function_exists("camp_load_translation_strings")) {
			camp_load_translation_strings("api");
		}
        $logtext = getGS('Language $1 modified', $this->m_data['Name']." (".$this->m_data['OrigName'].")");
        Log::Message($logtext, null, 103);
        return $success;
	} // fn update


	/**
	 * Delete the language, this will also delete the language files unless
	 * the parameter specifies otherwise.
	 *
	 * @return boolean
	 */
	public function delete($p_deleteLanguageFiles = true)
	{
		global $g_documentRoot;
		if (is_link($g_documentRoot . "/" . $this->getCode() . ".php")) {
			unlink($g_documentRoot . "/" . $this->getCode() . ".php");
		}
		if ($p_deleteLanguageFiles) {
			$result = Localizer::DeleteLanguageFiles($this->getCode());
			if (PEAR::isError($result)) {
				return result;
			}
		}
		$success = parent::delete();
		if ($success) {
			if (function_exists("camp_load_translation_strings")) {
				camp_load_translation_strings("api");
			}
			$logtext = getGS('Language $1 deleted', $this->m_data['Name']." (".$this->m_data['OrigName'].")");
			Log::Message($logtext, null, 102);
		}
		return $success;
	} // fn delete


	/**
	 * The unique ID of the language in the database.
	 * @return int
	 */
	public function getLanguageId()
	{
		return $this->m_data['Id'];
	} // fn getLanguageId


	/**
	 * Return the english name of this language.
	 * @return string
	 */
	public function getName()
	{
		return $this->m_data['Name'];
	} // fn getName


	/**
	 * Return the name of the language as written in the language itself.
	 * @return string
	 */
	public function getNativeName()
	{
		return $this->m_data['OrigName'];
	} // fn get


	/**
	 * Get the two-letter code for this language.
	 * @return string
	 */
	public function getCode()
	{
		return $this->m_data['Code'];
	} // fn getCode


	/**
	 * Get the page encoding for this language.
	 * @return string
	 */
	public function getCodePage()
	{
	    return $this->m_data['CodePage'];
	} // fn getCodePage


	/**
	 * Return an array of Language objects based on the contraints given.
	 *
	 * @param int $p_id
	 * @param string $p_languageCode
	 * @param string $p_name
	 * @return array
	 */
	public static function GetLanguages($p_id = null, $p_languageCode = null,
	                                    $p_name = null)
	{
	    $constraints = array();
	    if (!is_null($p_id)) {
	    	$constraints[] = array("Id", $p_id);
	    }
	    if (!is_null($p_languageCode)) {
	    	$constraints[] = array("Code", $p_languageCode);
	    }
	    if (!is_null($p_name)) {
	    	$constraints[] = array("Name", $p_name);
	    }
	    return DatabaseObject::Search('Language', $constraints);
	} // fn GetLanguages

	/**
	 * This will create the symbolic links needed for short URLs to work
	 *
	 */
	public static function CreateLanguageLinks()
	{
	    global $g_ado_db;

	    $document_root = $_SERVER['DOCUMENT_ROOT'];

	    $languages = $g_ado_db->GetAll('select Code from Languages');
	    $index_file = "$document_root/index.php";
	    foreach ($languages as $language) {
	        $languageCode = $language["Code"];
	        $link = "$document_root/$languageCode.php";
	        if (file_exists($link) && !is_link($link)) {
	            unlink($link);
	        }
	        if (!is_link($link)) {
	            symlink($index_file, $link);
	        }
	    }
	}

} // class Language

?>