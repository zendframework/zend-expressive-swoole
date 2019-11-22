<?php
/**
 * @see       https://github.com/zendframework/zend-expressive-swoole for the canonical source repository
 * @copyright Copyright (c) 2019 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive-swoole/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace ZendTest\Expressive\Swoole\HotCodeReload\FileWatcher;

use PHPUnit\Framework\TestCase;
use Zend\Expressive\Swoole\HotCodeReload\FileWatcher\InotifyFileWatcher;

class InotifyFileWatcherTest extends TestCase
{
    /** @var resource */
    private $file;

    protected function setUp() : void
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

    protected function tearDown() : void
    {
        fclose($this->file);
        parent::tearDown();
    }

    public function testReadChangedFilePathsIsNonBlocking() : void
    {
        $path = stream_get_meta_data($this->file)['uri'];
        $subject = new InotifyFileWatcher();
        $subject->addFilePath($path);

        static::assertEmpty($subject->readChangedFilePaths());
        fwrite($this->file, 'foo');
        static::assertEquals([$path], $subject->readChangedFilePaths());
    }
}
