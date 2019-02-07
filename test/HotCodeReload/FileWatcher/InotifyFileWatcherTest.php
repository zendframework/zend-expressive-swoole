<?php
/**
 * @see       https://github.com/zendframework/zend-expressive-swoole for the canonical source repository
 * @copyright Copyright (c) 2019 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive-swoole/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace ZendTest\Expressive\Swoole\HotCodeReload\FileWatcher;

use Zend\Expressive\Swoole\HotCodeReload\FileWatcher\InotifyFileWatcher;
use PHPUnit\Framework\TestCase;

class InotifyFileWatcherTest extends TestCase
{
    /** @var resource */
    private $file;

    public function testReadChangedFilePathsIsNonBlocking() : void
    {
        $path = stream_get_meta_data($this->file)['uri'];
        $subject = new InotifyFileWatcher();
        $subject->addFilePath($path);

        static::assertEmpty($subject->readChangedFilePaths());
        fwrite($this->file, 'foo');
        static::assertEquals([$path], $subject->readChangedFilePaths());
    }

    protected function setUp()
    {
        if (! extension_loaded('inotify')) {
            static::markTestSkipped('The Inotify extension is not available');
        }
        $file = tmpfile();
        if (false === $file) {
            static::markTestSkipped('Unable to create a temporary file');
        }
        $this->file = $file;

        parent::setUp();
    }

    protected function tearDown()
    {
        fclose($this->file);
        parent::tearDown();
    }
}
