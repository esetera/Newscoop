<?php
/**
 * @package Newscoop
 * @copyright 2012 Sourcefabric o.p.s.
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 */

namespace Newscoop\Image;

/**
 */
class ImageServiceTest extends \TestCase
{
    const PICTURE_LANDSCAPE = 'tests/fixtures/picture_landscape.jpg';
    const PICTURE_PORTRAIT = 'tests/fixtures/picture_portrait.jpg';

    /** @var Newscoop\Image\ImageService */
    protected $service;

    /** @var array */
    protected $config = array();

    /** @var Doctrine\ORM\EntityManager */
    protected $orm;

    public function setUp()
    {
        $this->config = array(
            'cache_url' => 'images/cache',
            'cache_path' => sys_get_temp_dir() . '/' . uniqid(),
        );

        $this->orm = $this->setUpOrm('Newscoop\Image\Image');

        $this->service = new ImageService($this->config, $this->orm);
    }

    public function tearDown()
    {
        if (realpath($this->config['cache_path'])) {
            system('rm -r ' . $this->config['cache_path']);
        }
    }

    public function testInstance()
    {
        $this->assertInstanceOf('Newscoop\Image\ImageService', $this->service);
    }

    public function testGetSrc()
    {
        $src = $this->service->getSrc(self::PICTURE_LANDSCAPE, 300, 300);
        $this->assertEquals('300x300/center_center/' . rawurlencode(rawurlencode(self::PICTURE_LANDSCAPE)), $src);
    }

    public function testGenerateImageFill()
    {
        $src = '200x200/fill/' . rawurlencode(rawurlencode(self::PICTURE_LANDSCAPE));

        $image = $this->generateImage($src);
        $info = $this->getInfo($image);

        $this->assertFileExists($this->config['cache_path'] . "/$src");
        $this->assertEquals(200, $info[0], 'width');
        $this->assertEquals(200, $info[1], 'height');
    }

    public function testGenerateImageFit()
    {
        $src = '200x200/fit/' . rawurlencode(rawurlencode(self::PICTURE_LANDSCAPE));
        $image = $this->generateImage($src);
        $info = $this->getInfo($image);

        $this->assertEquals(200, $info[0], 'width');
        $this->assertEquals(133, $info[1], 'height');
    }

    public function testFind()
    {
        $image = new Image('path');
        $this->orm->persist($image);
        $this->orm->flush($image);

        $this->assertContains('path', $this->service->find($image->getId())->getPath());
    }

    public function testGetThumbnail()
    {
        global $application;
        $rendition = new Rendition('test', 300, 300, 'fill');
        $thumbnail = $this->service->getThumbnail(self::PICTURE_LANDSCAPE, $rendition);

        $this->assertInstanceOf('Newscoop\Image\Thumbnail', $thumbnail);
        $this->assertContains('300x300', $thumbnail->src);
        $this->assertEquals(300, $thumbnail->width, 'thumbnail_width');
        $this->assertEquals(300, $thumbnail->height, 'thumbnail_height');

        $img = $thumbnail->getImg($application->getBootstrap()->getResource('view'));
        $this->assertContains('<img', $img);
        $this->assertContains(rawurlencode(rawurlencode(self::PICTURE_LANDSCAPE)), $img);
        $this->assertContains('width="300"', $img);
        $this->assertContains('height="300"', $img);
        $this->assertContains('alt="', $img);
    }

    /**
     * Generates image
     *
     * @param string $url
     * @return string
     */
    private function generateImage($url)
    {
        ob_start();
        $this->service->generateFromSrc($url);
        return ob_get_clean();
    }

    /**
     * Get image info
     *
     * @param string $image
     * @return array
     */
    private function getInfo($image)
    {
        $tmpfile = tempnam(sys_get_temp_dir(), 'img');
        file_put_contents($tmpfile, $image);
        $info = getimagesize($tmpfile);
        unlink($tmpfile);
        return $info;
    }
}
