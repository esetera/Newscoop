<?php
/**
 * @package Newscoop
 * @copyright 2011 Sourcefabric o.p.s.
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 */

namespace Newscoop\Services;

use Doctrine\ORM\EntityManager,
    Newscoop\Entity\ArticlePopularity,
    Newscoop\Entity\Article,
    Newscoop\Entity\Language,
    Zend_Gdata,
    Zend_Gdata_Query,
    Zend_Gdata_ClientLogin,
    DOMDocument;

/**
 * Article pouplarity service
 */
class ArticlePopularityService
{
    const SITE_URL = 'http://www.tageswoche.ch';

    const TWITTER_QUERY_URL = 'http://urls.api.twitter.com/1/urls/count.json?url=';

    const FACEBOOK_QUERY_URL = 'https://graph.facebook.com/?ids=';

    /** @var array */
    private $criteria = array(
        'unique_views' => array('getter' => 'getUniqueViews', 'factor' => 1.5),
        'avg_time_on_page' => array('getter' => 'getAvgTimeOnPage', 'factor' => 0.5),
        'tweets' => array('getter' => 'getTweets', 'factor' => 1.0),
        'likes' => array('getter' => 'getLikes', 'factor' => 1.0),
        'comments' => array('getter' => 'getComments', 'factor' => 1.0),
    );

    /** @var string */
    private $uri;

    /** @var Zend_Gdata */
    private $gd;

    /** @var Newscoop\Entity\Article */
    private $article;

    /** @var Doctrine\ORM\EntityManager */
    private $em;


    /**
     * @param array $config
     * @param Doctrine\ORM\EntityManager $em
     */
    public function __construct(EntityManager $em)
    {
        $this->em = $em;
        $this->gd = null;
    }

    /**
     * Register a page in the popularity table
     *
     * @param Zend_Controller_Request_Abstract $request
     * @return Newscoop\Entity\ArticlePopularity
     */
    public function register(\Zend_Controller_Request_Abstract $request)
    {
        // get requested uri
        $this->uri = $request->getPathInfo();

        // get language
        $language = $this->em->getRepository('Newscoop\Entity\Language')
            ->findOneBy(array('code' => $request->getParam('language')));

        $article = $this->findArticle($request->getParam('articleNo'), $language);
        if (!$article) {
            return $this;
        }

        $data = array(
            'article_id' => $article->getId(),
            'language_id' => $language->getId(),
            'url' => $this->uri,
            'date' => new \DateTime,
            'unique_views' => 0,
            'avg_time_on_page' => 0,
            'tweets' => 0,
            'likes' => 0,
            'comments' => 0,
            'popularity' => 0,
        );

        $entity = $this->getRepository()->findOneBy(array(
                'article_id' => $article->getId(),
                'language_id' => $language->getId(),
        ));

        try {
            $this->save($data, $entity);
            $this->em->flush();
        } catch (\InvalidArgumentException $e) {
        }

        return $this;
    }

    /**
     * Save article popularity record
     *
     * @param array $data
     * @param Newscoop\Entity\ArticlePopularity $popularity
     * @return Newscoop\Entity\ArticlePopularity
     */
    public function save(array $data, ArticlePopularity $entity = null)
    {
        if ($entity === null) {
            $entity = new ArticlePopularity;
        }

        if ($this->getRepository()->exists($entity) && $entity->getURL() == $data['url']) {
            return;
        }

        $this->getRepository()->save($entity, $data);
        return $entity;
    }

    /**
     * Init session in Analytics
     *
     * @return Zend_Gdata
     */
    private function initGASession()
    {
        if (is_null($this->gd)) {
            $email = '';
            $pass = '';
            $client = Zend_Gdata_ClientLogin::getHttpClient($email, $pass, 'analytics');
            $this->gd = new Zend_Gdata($client);
            $this->gd->useObjectMapping(false);
        }

        return $this->gd;
    }

    /**
     * Update an entry
     *
     * @param Newscoop\Entity\ArticlePopularity $entry
     * @return void
     */
    public function update(ArticlePopularity $entry)
    {
        $url = $entry->getURL();

        $gdClient = $this->initGASession();
        $ga = $this->fetchGAData($gdClient, $url);
        $data = array(
            'unique_views' => $ga['ga:uniquePageviews'],
            'avg_time_on_page' => $ga['ga:avgTimeOnPage'],
            'tweets' => $this->fetchTweets($url),
            'likes' => $this->fetchLikes($url),
            'comments' => $this->fetchComments($entry),
        );

        try {
            $this->getRepository()->save($entry, $data);
        } catch (\InvalidArgumentException $e) {
        }

        print("processed: $url\n");
    }

    /**
     * Update all entries
     *
     * @return void
     */
    public function updateMetrics()
    {
        $entries = $this->getRepository()->findAll();
        if (!is_array($entries)) {
            $entries = array();
        }

        $weekBefore = date('Y-m-d', strtotime('-7 days'));
        foreach($entries as $entry) {
            $article = $this->getRepository()->getArticle($entry);
            if (is_null($article) || $article->getPublishDate() < $weekBefore) {
                print "not processed\n";
                continue;
            }

            $response = $this->ping($entry->getURL());
            if (!$response || !$response->isSuccessful()) {
		continue;
            }

            $this->update($entry);
            $this->em->flush();
        }
    }

    /**
     * @param Newscoop\Entity\ArticlePopularity $entity
     * @param array $maxs
     * @return float
     */
    public function computeRanking(ArticlePopularity $entity, array $maxs)
    {
        $values = array();
        foreach($this->criteria as $criterion => $data) {
            if (isset($maxs[$criterion]) && $maxs[$criterion] <= 0) {
                $values[$criterion] = 0;
                continue;
            }

            $getter = $data['getter'];
            $values[$criterion] = (($entity->$getter() / $maxs[$criterion]) * 100) * $data['factor'];
        }

        $popularity = array_sum($values);

        return $popularity;
    }

    /**
     * Computes the article priority for all registered articles.
     *
     * @return void
     */
    public function updateRanking()
    {
        $maxs = $this->getRepository()->findMax($this->criteria);

        $entries = $this->getRepository()->findAll();
        if (!is_array($entries)) {
            $entries = array();
        }

        $data = array();
        foreach($entries as $entry) {
            $data['popularity'] = $this->computeRanking($entry, $maxs);

            try {
                $this->getRepository()->save($entry, $data);
            } catch (\InvalidArgumentException $e) {
            }
        }

        $this->em->flush();
    }

    /**
     * Find an article
     *
     * @param int $number
     * @param Newscoop\Entity\Language $language
     * @return Newscoop\Entity\Article
     */
    public function findArticle($number, Language $language)
    {
        // get article
        $article = $this->em->getRepository('Newscoop\Entity\Article')
            ->findOneBy(array('language' => $language->getId(), 'number' => $number));

        if (empty($article)) {
            return null;
        }

        return $article;
    }

    /**
     * Read google analytics metrics 
     *
     * @param string $uri 
     * @return array 
     */
    public function fetchGAData($gdClient, $uri)
    {
        $data = array();
        if (is_null($gdClient)) {
            return $data;
        }

        try {
            $dimensions = array('ga:pagePath');
            $metrics = array(
                'ga:uniquePageviews',
                'ga:avgTimeOnPage'
            );
            $reportURL = 'https://www.google.com/analytics/feeds/data?ids=ga:48980166&dimensions=' . @implode(',', $dimensions) . '&metrics=' . @implode(',', $metrics) . '&start-date=2011-11-02&end-date=2011-11-09&filters=ga:pagePath%3D%3D' . urlencode($uri);

            $xml = $gdClient->getFeed($reportURL);
            $dom = new DOMDocument();
            $dom->loadXML($xml); 
            $entries = $dom->getElementsByTagName('entry');
            foreach($entries as $entry) {
                foreach($entry->getElementsByTagName('metric') as $metric) {
                    $data[$metric->getAttribute('name')] = $metric->getAttribute('value');
                }
            }
        } catch (\Zend_Exception $e) {
            echo 'Caught exception: ' . get_class($e) . "\n"; echo 'Message: ' . $e->getMessage() . "\n";
        }

        return $data;
    }

    /**
     * Read re-tweets
     *
     * @param string $uri
     * @return int
     */
    public function fetchTweets($uri)
    {
        $page = self::SITE_URL . $uri;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, self::TWITTER_QUERY_URL . $page);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        $tdata = curl_exec($ch);
        curl_close($ch);

        $tdata = json_decode($tdata);
        return (int) $tdata->count;
    }

    /**
     * Read facebook likes
     *
     * @param string $uri
     * @return int
     */
    public function fetchLikes($uri)
    {
        $page = self::SITE_URL . $uri;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, self::FACEBOOK_QUERY_URL . $page);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        $fdata = curl_exec($ch);
        curl_close($ch);

        $fdata = json_decode($fdata);
        return isset($fdata->$page->shares) ? (int) $fdata->$page->shares : 0;
    }

    /**
     * Read number of comments
     *
     * @return int
     */
    public function fetchComments(ArticlePopularity $entry)
    {
        $comments = 0;
        $article = $this->getRepository()->getArticle($entry);
        if (!is_null($article) && $article->commentsEnabled()) {
            $commentsRepository = $this->em->getRepository('Newscoop\Entity\Comment');
            $filter = array( 'thread' => $entry->getArticleId(), 'language' => $entry->getLanguageId());
            $params = array( 'sFilter' => $filter);
            $comments = $commentsRepository->getCount($params);
        }

        return (int) $comments;
    }

    /**
     * Ping the given URL
     *
     * @param string $uri
     * @return Zend_Http_Response
     */
    public function ping($uri)
    {
        $uri = self::SITE_URL . $uri;
        try {
            $client = new \Zend_Http_Client($uri);
            $response = $client->request();
        } catch(\Zend_Exception $e) {
            echo 'Caught exception: ' . get_class($e) . "\n"; echo 'Message: ' . $e->getMessage() . "\n";
            return false;
        }

        return $response;
    }

    /**
     * Find by given criteria
     *
     * @param array $criteria
     * @param array $orderBy
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function findBy(array $criteria, array $orderBy = array(), $limit = 25, $offset = 0)
    {
        return $this->getRepository()
            ->findBy($criteria, $orderBy, $limit, $offset);
    }

    /**
     * Get repository
     *
     * @return Doctrine\ORM\EntityRepository
     */
    private function getRepository()
    {
        return $this->em->getRepository('Newscoop\Entity\ArticlePopularity');
    }
}
