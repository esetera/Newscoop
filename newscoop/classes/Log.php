<?php
/**
 * @package Campsite
 */

/**
 * Includes
 */
require_once($GLOBALS['g_campsiteDir'].'/db_connect.php');
require_once($GLOBALS['g_campsiteDir'].'/classes/DatabaseObject.php');

/**
 * @package Campsite
 */
class Log extends DatabaseObject {
	var $m_keyColumnNames = array('time_created', 'fk_event_id', 'text');
	var $m_keyIsAutoIncrement = false;
	var $m_dbTableName = 'Log';
	var $m_columnNames = array(
		'time_created',
		'fk_event_id',
		'fk_user_id',
		'text',
		'user_ip');


	/**
	 * This is a static function.
	 * Write a message to the log table.
	 *
	 * @param string $p_text
	 * @param string $p_userName
	 * @param int $p_eventId
	 *
	 * @return void
	 */
	public static function Message($p_text, $p_userId = null, $p_eventId = 0)
	{
		global $g_ado_db;
		if (is_null($p_userId)) {
			$p_userId = 0;

			// try to get the user name from the global environment
			if (isset($_REQUEST['LoginUserId'])) {
				$p_userId = $_REQUEST['LoginUserId'];
			} elseif (isset($_COOKIE['LoginUserId'])) {
			    $p_userId = $_COOKIE['LoginUserId'];
			}
		}
		if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
			$userIP = $_SERVER['HTTP_X_FORWARDED_FOR'];
		} elseif (isset($_SERVER['REMOTE_ADDR'])) {
			$userIP = $_SERVER['REMOTE_ADDR'];
		} else {
			$userIP = null;
		}
		$queryStr = "INSERT INTO Log SET "
					." time_created=NOW(), "
					." fk_event_id=$p_eventId,"
					." fk_user_id=$p_userId, "
					." text='".$g_ado_db->escape($p_text)."'";
		if (!is_null($userIP)) {
			$queryStr .= ", user_ip=INET_ATON('$userIP')";
		}
		$g_ado_db->Execute($queryStr);
	} // fn Message


    /**
     * Log article related event.
     *
     * @param Article $p_article
     * @param string $p_text
     * @param int $p_userId
     * @param int $p_eventId
     * @param bool $p_short
     *
     * @return void
     */
    public static function ArticleMessage(Article $p_article, $p_text, $p_userId = NULL, $p_eventId = 0, $p_short = FALSE)
    {
        ob_start();

        echo getGS('Article'), ': ', $p_article->getTitle();

        if (!$p_short) { // add publication, issue, section
            echo ' (';
            echo getGS('Publication'), ': ', $p_article->getPublicationId();
            echo ', ';
            echo getGS('Issue'), ': ', $p_article->getIssueNumber();
            echo ', ';
            echo getGS('Section'), ': ', $p_article->getSectionNumber();
            echo ")\n";
        }

        // generate url
        $url = ShortURL::GetURL($p_article->getPublicationId(),
            $p_article->getLanguageId(),
            $p_article->getIssueNumber(),
            $p_article->getSectionNumber(),
            $p_article->getArticleNumber());
        if (strpos($url, 'http') !== FALSE) { // no url for deleted
            echo getGS('Article URL'), ': ', $url, "\n";
        }

        echo getGS('Article Number'), ': ', $p_article->getArticleNumber(), "\n";
        echo getGS('Language'), ': ', $p_article->getLanguageName(), "\n";

        echo "\n";
        echo getGS('Action') . ': ', $p_text;

        $message = ob_get_clean();
        self::Message($message, $p_userId, $p_eventId);
    }


	/**
	 * Get the time the log message was created.
	 * @return string
	 */
	public function getTimeStamp()
	{
		return $this->m_data['time_created'];
	} // fn getTimeStamp


	/**
	 * Return the log message.
	 * @return string
	 */
	public function getText()
	{
		return $this->m_data['text'];
	} // fn getText


	/**
	 * Get the event ID which cooresponds to an entry in the "Events" table.
	 * @return int
	 */
	public function getEventId()
	{
		return $this->m_data['fk_event_id'];
	} // fn getEventId


	public function getClientIP()
	{
	    return $this->m_data['user_ip_addr'];
	}


	/**
	 * Return the number of log lines.
	 * @param int $p_eventId
	 * @return int
	 */
	public static function GetNumLogs($p_eventId = null)
	{
		global $g_ado_db;
		$queryStr = 'SELECT COUNT(*) FROM Log';
		if (!is_null($p_eventId)) {
			$queryStr .= " WHERE fk_event_id=$p_eventId";
		}
		$total = $g_ado_db->GetOne($queryStr);
		return $total;
	} // fn GetNumLogs


	/**
	 * Get the logs.
	 *
	 * @param int $p_eventId
	 * @param array $p_sqlOptions
	 *
	 * @return array
	 */
	public static function GetLogs($p_eventId = null, $p_sqlOptions = null)
	{
		if (is_null($p_sqlOptions) || !isset($p_sqlOptions['ORDER BY'])) {
			$p_sqlOptions['ORDER BY'] = array('time_created' => 'DESC');
		}
		$tmpLog = new Log();
		$columns = $tmpLog->getColumnNames(true);
		$queryStr = "SELECT ".implode(", ", $columns)
		    .", INET_NTOA(Log.user_ip) AS user_ip_addr"
		    .", liveuser_users.Name as full_name"
		    .", liveuser_users.UName as user_name"
		    ." FROM Log"
		    ." LEFT JOIN liveuser_users"
		    ." ON Log.fk_user_id = liveuser_users.Id";
		if (!is_null($p_eventId)) {
			$queryStr .= " WHERE Log.fk_event_id=$p_eventId";
		}
		$queryStr = DatabaseObject::ProcessOptions($queryStr, $p_sqlOptions);
		$logLines = DbObjectArray::Create('Log', $queryStr);
		return $logLines;
	} // fn GetLogs

} // class Log

?>
