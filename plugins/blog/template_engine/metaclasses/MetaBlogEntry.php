<?php
/**
 * @package Campsite
 *
 * @author Sebastian Goebel <sebastian.goebel@web.de>
 * @copyright 2007 MDLF, Inc.
 * @license http://www.gnu.org/licenses/gpl.txt
 * @version $Revision$
 * @link http://www.sourcefabric.org
 */

final class MetaBlogEntry extends MetaDbObject {

    private function InitProperties()
    {
        if (!is_null($this->m_properties)) {
            return;
        }

        $this->m_properties['identifier'] = 'entry_id';
        $this->m_properties['blog_id'] = 'fk_blog_id';
        $this->m_properties['language_id'] = 'fk_language_id';
        $this->m_properties['user_id'] = 'fk_user_id';
        $this->m_properties['date'] = 'date';
        $this->m_properties['released'] = 'released';
        $this->m_properties['status'] = 'status';
        $this->m_properties['title'] = 'title';
        $this->m_properties['name'] = 'title';
        $this->m_properties['content'] = 'content';
        $this->m_properties['mood_id'] = 'fk_mood_id';
        $this->m_properties['images'] = 'images';
        $this->m_properties['admin_status'] = 'admin_status';
        $this->m_properties['comments_online'] = 'comments_online';
        $this->m_properties['comments_offline'] = 'comments_offline';
        $this->m_properties['feature'] = 'feature';
    }


    public function __construct($p_entry_id=null)
    {
        $this->m_dbObject = new BlogEntry($p_entry_id);

        $this->InitProperties();
        $this->m_customProperties['defined'] = 'defined';
        $this->m_customProperties['blog'] = 'getBlog';
        $this->m_customProperties['language'] = 'getLanguage';
        $this->m_customProperties['user'] = 'getUser';
        $this->m_customProperties['comments'] = 'getCommentsCount';
        $this->m_customProperties['mood'] = 'getMood';

        $this->m_skipFilter = array('content');
    } // fn __construct
    
    public function getBlog()
    {
        $MetaBlog = new MetaBlog($this->fk_blog_id);
        return $MetaBlog;   
    }
    
    public function getLanguage()
    {
        $MetaLanguage = new MetaLanguage($this->language_id);
        return $MetaLanguage;   
    }
    
    public function getUser()
    {
        $MetaUser = new MetaUser($this->user_id);
        return $MetaUser;   
    }
    
    public function getCommentsCount()
    {
        return $this->comments_online + $this->comments_offline;
    }
    
    public function getMood()
    {
        $MetaTopic = new MetaTopic($this->mood_id);
        return $MetaTopic;   
    }

} // class MetaBlogEntry

?>
