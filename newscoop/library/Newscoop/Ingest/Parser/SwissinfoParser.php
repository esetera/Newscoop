<?php
/**
 * @package Newscoop
 * @copyright 2011 Sourcefabric o.p.s.
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 */

namespace Newscoop\Ingest\Parser;

use Newscoop\Ingest\Parser;

/**
 * Swissinfo parser
 */
class SwissinfoParser implements Parser
{

    /** @var SimpleXMLElement */
    private $story;

    /** @var DateTime */
    private $date;

    /**
     * @param string $content
     */
    public function __construct($story)
    {
        $this->story = $story;

        try {
            $date_string = (string) array_shift($this->story->xpath('./property[8]/value/date'));
            //echo $date_string ."\n";
            $this->date = new \DateTime($date_string);
        }
        catch (Exception $e) {
            $this->date = new \DateTime();
            //echo $e->getMessage();
        }
    }

    /**
     * Get title
     *
     * @return string
     */
    public function getTitle()
    {
        return (string) array_shift($this->story->xpath('./property[1]/value/string'));
    }

    /**
     * Get content
     *
     * @return string
     */
    public function getContent()
    {
        $content = array();

        $lead_section = $this->story->xpath('./property[4]');
        $content[]= '<p>';
        $content[]= (string) array_shift($lead_section[0]->xpath('.//content[@type="TextBlock"]/property[1]/value/string'));
        $content[]= '</p>';

        $main_section = $this->story->xpath('./property[3]');
        $main_section_content = $main_section[0]->xpath('.//content[@type="TextBlock"]/property[1]/value/string');
        $main_section_titles = $main_section[0]->xpath('.//content[@type="TextBlock"]/property[2]');

        $main_titles = array();
        foreach ($main_section_titles as $title) {
            $key = (string) array_shift($title->xpath('.//key'));
            if ($key == "title") {
                $value = (string) array_shift($title->xpath('.//value/string'));
                $main_titles[] = $value;
            }
            else {
                //maybe they have something weird there...
                $main_titles[] = null;
            }
        }

        $i = 0;
        foreach($main_section_content as $section) {
            $title = (string) $main_titles[$i];
            if (!empty($title)) {
                if (preg_match('/^<h[2|3|4]>.*<\/h[2|3|4]>/', $title)) {
                    $content[] = $title;
                } else {
                    $content[]= '<p><strong>';
                    $content[]= $title;
                    $content[]= '</strong></p>';
                }
            }
            $content[]= '<p>';
            $content[]= (string) $section;
            $content[]= '</p>';

            $i = $i + 1;
        }

        $content[]= '<p class="swiss-info-free">';
        $free_section = $this->story->xpath('./property[5]');
        $content[]= (string) array_shift($free_section[0]->xpath('.//content[@type="TextBlock"]/property[1]/value/string'));
        $content[]= '</p>';

        $content = implode("", $content);

        return $content;
    }

    /**
     * Get created
     *
     * @return DateTime
     */
    public function getCreated()
    {
        return $this->date;
    }

    /**
     * Get updated
     *
     * @return DateTime
     */
    public function getUpdated()
    {
        return $this->date;
    }

     /**
     * Get date id yyyymmdd
     *
     * @return string
     */
    public function getDateId()
    {
        return $this->date->format('Ymd');
    }

     /**
     * Get news item id
     *
     * @return string
     */
    public function getNewsItemId()
    {
        return $this->story['id'];
    }

    public function getPriority()
    {
        return null;
    }

    public function getSummary()
    {
        $lead_section = $this->story->xpath('./property[4]');
        return (string) array_shift($lead_section[0]->xpath('.//content[@type="TextBlock"]/property[2]/value/string'));
    }

    public function getStatus()
    {
        return "Usable";
    }

    public function getLiftEmbargo()
    {
        return null;
    }

    //Attributes of articles

    public function getService()
    {
        return (string) array_shift($this->story->xpath('./property[2]/value/string'));
    }

    public function getLanguage()
    {
        return "de";
    }

    public function getSubject()
    {
        return "";
    }

    public function getCountry()
    {
        return "";
    }

    public function getProduct()
    {
        return "swissinfo";
    }

    public function getSubtitle()
    {
        return "";
    }

    public function getProviderId()
    {
        return "";
    }

    public function getRevisionId()
    {
        return "";
    }

    public function getLocation()
    {
        return "";
    }

    public function getProvider()
    {
        return (string) array_shift($this->story->xpath('./property[2]/value/string'));
    }

    public function getSource()
    {
        return (string) array_shift($this->story->xpath('./property[2]/value/string'));
    }

    public function getCatchLine()
    {
        $lead_section = $this->story->xpath('./property[4]');
        return (string) array_shift($lead_section[0]->xpath('.//content[@type="TextBlock"]/property[2]/value/string'));
    }

    public function getCatchWord()
    {
        $free_section = $this->story->xpath('./property[5]');
        return (string) array_shift($free_section[0]->xpath('.//content[@type="TextBlock"]/property[2]/value/string'));
    }

    public function getAuthors()
    {
        return (string) array_shift($this->story->xpath('./property[7]/value/string'));
    }

    public function getImages()
    {
        return null;
    }

    /**
     * Get all story objects from the xml string.
     *
     * @param string $xml
     */
    public static function getStories($xml)
    {
        $xml = simplexml_load_string($xml);

        if(!$xml) {
            return array();
        }

        return $xml->xpath('//content[@type="Story"]');
    }
}
