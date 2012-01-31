<?php
/**
 * @package Newscoop
 * @copyright 2011 Sourcefabric o.p.s.
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 */

namespace Newscoop\Services;

use Doctrine\ORM\EntityManager,
    Newscoop\Entity\Ingest\Feed,
    Newscoop\Entity\Ingest\Feed\Entry,
    Newscoop\Ingest\Parser,
    Newscoop\Ingest\Parser\NewsMlParser,
    Newscoop\Ingest\Parser\SwissinfoParser,
    Newscoop\Ingest\Publisher,
    Newscoop\Services\Ingest\PublisherService;

/**
 * Ingest service
 */
class IngestService
{
    const IMPORT_DELAY = 180;
    const MODE_SETTING = 'IngestAutoMode';

    /** @var array */
    private $config = array();

    /** Doctrine\ORM\EntityManager */
    private $em;

    /** @var Newscoop\Services\Ingest\PublisherService */
    private $publisher;


    /**
     * @param array $config
     * @param Doctrine\ORM\EntityManager $em
     */
    public function __construct($config, EntityManager $em, PublisherService $publisher)
    {
        $this->config = $config;
        $this->em = $em;
        $this->publisher = $publisher;
    }

    /**
     * Add feed
     *
     * @param Newscoop\Entity\Ingest\Feed $feed
     * @return void
     */
    public function addFeed(Feed $feed)
    {
        $this->em->persist($feed);
        $this->em->flush();
    }

    public function getFeeds()
    {
        return $this->em->getRepository('Newscoop\Entity\Ingest\Feed')
                    ->findAll();
    }

    /**
     * Update all feeds
     *
     * @return void
     */
    public function updateSDA()
    {
        $feed = $this->em->getRepository('Newscoop\Entity\Ingest\Feed')
                    ->findBy(array(
                        'title' => 'SDA',
                    ));

        if (count($feed) > 0) {
            $this->updateSDAFeed($feed[0]);
        }
    }

    /**
     * Update all swissinfo feeds
     *
     * @return void
     */
    public function updateSwissinfo()
    {
        $feed = $this->em->getRepository('Newscoop\Entity\Ingest\Feed')
                    ->findBy(array(
                        'title' => 'swissinfo',
                    ));

        if (count($feed) > 0) {
            $this->updateSwissinfoFeed($feed[0]);
        }
    }

    /**
     * Update feed
     *
     * @param Newscoop\Entity\Ingest\Feed $feed
     * @return void
     */
    private function updateSwissinfoFeed(Feed $feed)
    {
        try {
            $http = new \Zend_Http_Client($this->config['swissinfo_sections']);
            $response = $http->request();
            if ($response->isSuccessful()) {
                $available_sections = $response->getBody();
                $available_sections = json_decode($available_sections, true);
            } else {
                return;
            }
        } catch (\Zend_Http_Client_Exception $e) {
            return;
            //throw new \Exception("Swiss info http error {$e->getMessage()}");
        } catch(\Exception $e) {
            return;
        }

        // get articles for each available section
        $url = $this->config['swissinfo_latest'];

        foreach ($available_sections as $section) {
            try {
                $request_url = str_replace('{{section_id}}', $section['id'], $url);
                $http = new \Zend_Http_Client($request_url);
                $response = $http->request();

                if ($response->isSuccessful()) {
                    $section_xml = $response->getBody();
                    $stories = Parser\SwissinfoParser::getStories($section_xml);

                    foreach ($stories as $story) {
                        $parser = new SwissinfoParser($story);
                        $entry = $this->getPrevious($parser, $feed);

                        if ($entry->isPublished()) {
                            $this->updatePublished($entry);
                        } elseif ($feed->isAutoMode()) {
                            $this->publish($entry);
                        }
                    }
                }
            } catch (\Exception $e) {
                //throw new \Exception("Swiss info feed error {$e->getMessage()}");
                return;
            }
        }

        $feed->setUpdated(new \DateTime());
        $this->em->flush();
    }

    /**
     * Update feed
     *
     * @param Newscoop\Entity\Ingest\Feed $feed
     * @return void
     */
    private function updateSDAFeed(Feed $feed)
    {
        foreach (glob($this->config['path'] . '/*.xml') as $file) {
            if ($feed->getUpdated() && $feed->getUpdated()->getTimestamp() > filectime($file) + self::IMPORT_DELAY) {
                continue;
            }

            if (time() < filectime($file) + self::IMPORT_DELAY) {
                continue;
            }

            $handle = fopen($file, 'r');
            if (flock($handle, LOCK_EX | LOCK_NB)) {
                $parser = new NewsMlParser($file);
                if (!$parser->isImage()) {
                    $entry = $this->getPrevious($parser, $feed);
                    switch ($parser->getInstruction()) {
                        case 'Rectify':
                        case 'Update':
                            $entry->update($parser);

                        case '':
                            if ($entry->isPublished()) {
                                $this->updatePublished($entry);
                            } else if ($feed->isAutoMode()) {
                                $this->publish($entry);
                            }
                            break;

                        case 'Delete':
                            $this->deletePublished($entry);
                            $this->em->remove($entry);
                            break;

                        default:
                            throw new \InvalidArgumentException("Instruction '{$parser->getInstruction()}' not implemented.");
                            break;
                    }
                }

                flock($handle, LOCK_UN);
                fclose($handle);
            } else {
                continue;
            }
        }

        $feed->setUpdated(new \DateTime());
        $this->getEntryRepository()->liftEmbargo();
        $this->em->flush();
    }

    /**
     * Get previous version of entry
     *
     * @param Newscoop\Ingest\Parser $parser
     * @param Newscoop\Entity\Ingest\Feed $feed
     * @return Newscoop\Entity\Ingest\Feed\Entry
     */
    public function getPrevious(Parser $parser, Feed $feed)
    {
        $previous = array_shift($this->getEntryRepository()->findBy(array(
            'date_id' => $parser->getDateId(),
            'news_item_id' => $parser->getNewsItemId(),
        )));

        if (empty($previous)) {
            $previous = Entry::create($parser);
            $previous->setFeed($feed);
            $this->em->persist($previous);
            $this->em->flush();
        }

        return $previous;
    }

    /**
     * Find by id
     *
     * @param int $id
     * @return Newscoop\Entity\Ingest\Feed\Entry
     */
    public function find($id)
    {
        return $this->getEntryRepository()
            ->find($id);
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
        return $this->getEntryRepository()
            ->findBy($criteria, $orderBy, $limit, $offset);
    }

    /**
     * Switch mode
     *
     * @return void
     */
    public function switchMode($feed_id)
    {
        $feed = $this->em->getRepository('Newscoop\Entity\Ingest\Feed')->find($feed_id);

        if ($feed->getMode() === "auto") {
            $feed = $feed->setMode("manual");
        }
        else {
            $feed = $feed->setMode("auto");
        }

        $this->em->persist($feed);
        $this->em->flush();
    }

    /**
     * Publish entry
     *
     * @param Newscoop\Entity\Ingest\Feed\Entry $entry
     * @param string $workflow
     * @return Article
     */
    public function publish(Entry $entry, $workflow = 'Y')
    {
        $article = $this->publisher->publish($entry, $workflow);
        $entry->setPublished(new \DateTime());
        $this->em->persist($entry);
        $this->em->flush();
        return $article;
    }

    /**
     * Delete entry by id
     *
     * @param int $id
     * @return void
     */
    public function deleteEntryById($id)
    {
        $entry = $this->find($id);
        if (!empty($entry)) {
            $this->em->remove($entry);
            $this->em->flush();
        }
    }

    /**
     * Updated published entry
     *
     * @param Newscoop\Entity\Ingest\Feed\Entry $entry
     * @return void
     */
    private function updatePublished(Entry $entry)
    {
        if ($entry->isPublished()) {
            $this->publisher->update($entry);
        }
    }

    /**
     * Delete published entry
     *
     * @param Newscoop\Entity\Ingest\Feed\Entry $entry
     * @return void
     */
    private function deletePublished(Entry $entry)
    {
        if ($entry->isPublished()) {
            $this->publisher->delete($entry);
        }
    }

    /**
     * Get feed entry repository
     *
     * @return Doctrine\ORM\EntityRepository
     */
    private function getEntryRepository()
    {
        return $this->em->getRepository('Newscoop\Entity\Ingest\Feed\Entry');
    }
}
