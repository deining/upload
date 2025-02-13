<?php

namespace Sirius\Upload;

use Laminas\Diactoros\StreamFactory;
use Laminas\Diactoros\UploadedFile;
use Symfony\Component\HttpFoundation\File\UploadedFile as SymfonyUploadedFile;

class HandlerTest extends \PHPUnit\Framework\TestCase
{

    /**
     * @var Handler
     */
    protected $handler;

    protected function setUp(): void
    {
        $this->tmpFolder = realpath(__DIR__ . '/../fixitures/');
        @mkdir($this->tmpFolder . '/container');
        $this->uploadFolder = realpath(__DIR__ . '/../fixitures/container/');
        $this->handler      = new Handler(
            $this->uploadFolder, array(
                Handler::OPTION_PREFIX      => '',
                Handler::OPTION_OVERWRITE   => false,
                Handler::OPTION_AUTOCONFIRM => false
            )
        );
    }

    protected function tearDown(): void
    {
        $files = glob($this->uploadFolder . '/*'); // get all file names
        foreach ($files as $file) { // iterate files
            if (is_file($file)) {
                unlink($file);
            } // delete file
        }
    }

    function createTemporaryFile($name, $content = "")
    {
        file_put_contents($this->tmpFolder . '/' . $name, $content);
    }

    function testBasicUploadWithPrefix()
    {
        $this->handler->setPrefix('subfolder/');
        $this->createTemporaryFile('abc.tmp');

        $result = $this->handler->process(
            array(
                'name'     => 'abc.jpg',
                'tmp_name' => $this->tmpFolder . '/abc.tmp'
            )
        );

        $this->assertTrue(file_exists($this->uploadFolder . '/' . $result->name));
        $this->assertTrue(file_exists($this->uploadFolder . '/' . $result->name . '.lock'));
        // tearDown does not clean the subfolders
        unlink($this->uploadFolder . '/' . $result->name);
        unlink($this->uploadFolder . '/' . $result->name . '.lock');
    }

    function testUploadOverwrite()
    {
        $this->createTemporaryFile('abc.tmp', 'first_file');

        $result = $this->handler->process(
            array(
                'name'     => 'abc.jpg',
                'tmp_name' => $this->tmpFolder . '/abc.tmp'
            )
        );

        $this->assertEquals(file_get_contents($this->uploadFolder . '/abc.jpg'), 'first_file');

        // no overwrite, the first upload should be preserved
        $this->handler->setOverwrite(false);
        $this->createTemporaryFile('abc.tmp', 'second_file');

        $result = $this->handler->process(
            array(
                'name'     => 'abc.jpg',
                'tmp_name' => $this->tmpFolder . '/abc.tmp'
            )
        );

        $this->assertEquals(file_get_contents($this->uploadFolder . '/abc.jpg'), 'first_file');

        // overwrite, the first uploaded file should be changed
        $this->handler->setOverwrite(true);
        $this->createTemporaryFile('abc.tmp', 'second_file');

        $result = $this->handler->process(
            array(
                'name'     => 'abc.jpg',
                'tmp_name' => $this->tmpFolder . '/abc.tmp'
            )
        );

        $this->assertEquals(file_get_contents($this->uploadFolder . '/abc.jpg'), 'second_file');
    }

    function testUploadAutoconfirm()
    {
        $this->handler->setAutoconfirm(true);
        $this->createTemporaryFile('abc.tmp', 'first_file');

        $result = $this->handler->process(
            array(
                'name'     => 'abc.jpg',
                'tmp_name' => $this->tmpFolder . '/abc.tmp'
            )
        );

        $this->assertTrue(file_exists($this->uploadFolder . '/' . $result->name));
        $this->assertFalse(file_exists($this->uploadFolder . '/' . $result->name . '.lock'));
    }

    function testSingleUploadConfirmation()
    {
        $this->createTemporaryFile('abc.tmp', 'first_file');

        $result = $this->handler->process(
            array(
                'name'     => 'abc.jpg',
                'tmp_name' => $this->tmpFolder . '/abc.tmp'
            )
        );

        $this->assertTrue(file_exists($this->uploadFolder . '/' . $result->name));
        $this->assertTrue(file_exists($this->uploadFolder . '/' . $result->name . '.lock'));

        $result->confirm();
        $this->assertFalse(file_exists($this->uploadFolder . '/' . $result->name . '.lock'));
    }


    function testSingleUploadClearing()
    {
        $this->createTemporaryFile('abc.tmp', 'first_file');

        $result = $this->handler->process(
            array(
                'name'     => 'abc.jpg',
                'tmp_name' => $this->tmpFolder . '/abc.tmp'
            )
        );

        $this->assertTrue(file_exists($this->uploadFolder . '/' . $result->name));
        $this->assertTrue(file_exists($this->uploadFolder . '/' . $result->name . '.lock'));

        $fileName = $result->name;
        $result->clear();

        $this->assertFalse(file_exists($this->uploadFolder . '/' . $fileName));
        $this->assertFalse(file_exists($this->uploadFolder . '/' . $fileName . '.lock'));
    }

    function testMultiUpload()
    {
        $this->createTemporaryFile('abc.tmp', 'first_file');
        $this->createTemporaryFile('def.tmp', 'first_file');

        // array is already properly formated
        $result = $this->handler->process(
            array(
                array(
                    'name'     => 'abc.jpg',
                    'tmp_name' => $this->tmpFolder . '/abc.tmp'
                ),
                array(
                    'name'     => 'def.jpg',
                    'tmp_name' => $this->tmpFolder . '/def.tmp'
                )
            )
        );

        $this->assertTrue($result->isValid());

#        var_dump(glob($this->uploadFolder . '/*'));
        foreach ($result as $file) {
            $this->assertTrue(file_exists($this->uploadFolder . '/' . $file->name));
            $this->assertTrue(file_exists($this->uploadFolder . '/' . $file->name . '.lock'));
        }

        // confirmation removes the .lock files
        $result->confirm();
        foreach ($result as $file) {
            $this->assertTrue(file_exists($this->uploadFolder . '/' . $file->name));
            $this->assertFalse(file_exists($this->uploadFolder . '/' . $file->name . '.lock'));
        }

        // clearing removes the uploaded files and their locks (which are already removed)
        $result->clear();
        foreach ($result as $file) {
            $this->assertNull($file->name);
        }
    }

    function testOriginalMultiUpload()
    {
        $this->createTemporaryFile('abc.tmp', 'first_file');
        $this->createTemporaryFile('def.tmp', 'first_file');

        // array is as provided by PHP
        $result = $this->handler->process(
            array(
                'name'     => array(
                    'abc.jpg',
                    'def.jpg',
                ),
                'tmp_name' => array(
                    $this->tmpFolder . '/abc.tmp',
                    $this->tmpFolder . '/def.tmp'
                ),
            )
        );

        $this->assertEquals(count($result), 2);
        foreach ($result as $file) {
            $this->assertTrue(file_exists($this->uploadFolder . '/' . $file->name));
            $this->assertTrue(file_exists($this->uploadFolder . '/' . $file->name . '.lock'));
        }
    }

    function testWrongFilesArray()
    {
        $result = $this->handler->process(array('names' => 'abc.jpg'));
        $this->assertEquals(count($result), 0);
    }

    function testExceptionTrwonForInvalidContainer()
    {
        $this->expectException('Sirius\Upload\Exception\InvalidContainerException');

        $handler = new Handler(new \stdClass());
    }

    function testSingleUploadValidation()
    {
        $this->createTemporaryFile('abc.tmp', 'non image file');

        // uploaded files must be an image
        $this->handler->addRule(Handler::RULE_IMAGE);

        $result = $this->handler->process(
            array(
                'name'     => 'abc.jpg',
                'tmp_name' => $this->tmpFolder . '/abc.tmp'
            )
        );

        $this->assertFalse($result->isValid());
        $this->assertEquals(count($result->getMessages()), 1);
        $this->assertNull($result->nonAttribute);
    }


    function testMultiUploadValidation()
    {
        $this->createTemporaryFile('abc.tmp', 'first_file');
        $this->createTemporaryFile('def.tmp', 'second_file');

        // uploaded file must be an image
        $this->handler->addRule(Handler::RULE_IMAGE);

        // array is as provided by PHP
        $result   = $this->handler->process(
            array(
                'name'     => array(
                    'abc.jpg',
                    'def.jpg',
                ),
                'tmp_name' => array(
                    $this->tmpFolder . '/abc.tmp',
                    $this->tmpFolder . '/def.tmp'
                ),
            )
        );
        $messages = $result->getMessages();

        $this->assertFalse($result->isValid());
        $this->assertEquals(count($messages), 2);
        $this->assertEquals(count($messages[0]), 1);
    }

    function testCustomSanitizationCallback()
    {
        $this->handler->setSanitizerCallback(function ($name, $file) {
            return preg_replace(
                '/[^A-Za-z0-9\.]+/', '-',
                substr(md5_file($file['tmp_name']), 0, 8) . '-' . strtolower($name)
            );
        });
        $this->createTemporaryFile('ABC 123.tmp', 'non image file');

        $result = $this->handler->process(
            array(
                'name'     => 'ABC 123.tmp',
                'tmp_name' => $this->tmpFolder . '/ABC 123.tmp'
            )
        );

        $this->assertTrue(file_exists($this->uploadFolder . '/35d41ded-abc-123.tmp'));
    }

    function testExceptionThrownForInvalidSanitizationCallback()
    {
        $this->expectException('InvalidArgumentException');
        $this->handler->setSanitizerCallback('not a callable');
    }

    function testPsr7UploadedFiles()
    {
        $files = ['abc.tmp', 'def.tmp'];

        $psr7Files = [];

        foreach ($files as $file) {
            $this->createTemporaryFile($file, 'first_file');

            $factory     = new StreamFactory();
            $stream      = $factory->createStreamFromFile($this->tmpFolder . '/' . $file);
            $psr7Files[] = new UploadedFile(
                $stream,
                $stream->getSize(),
                UPLOAD_ERR_OK,
                $file
            );
        }

        $result = $this->handler->process($psr7Files);

        foreach ($result as $item) {
            $this->assertTrue(file_exists($this->uploadFolder . '/' . $item->name));
            $this->assertTrue(file_exists($this->uploadFolder . '/' . $item->name . '.lock'));

            $item->confirm();
            $this->assertFalse(file_exists($this->uploadFolder . '/' . $item->name . '.lock'));
        }
    }

    function testSinglePsr7UploadedFile()
    {
        $file = 'abc.tmp';

        $this->createTemporaryFile($file, 'first_file');

        $factory  = new StreamFactory();
        $stream   = $factory->createStreamFromFile($this->tmpFolder . '/' . $file);
        $psr7File = new UploadedFile(
            $stream,
            $stream->getSize(),
            UPLOAD_ERR_OK,
            $file
        );

        $result = $this->handler->process($psr7File);

        $this->assertTrue(file_exists($this->uploadFolder . '/' . $result->name));
        $this->assertTrue(file_exists($this->uploadFolder . '/' . $result->name . '.lock'));

        $result->confirm();
        $this->assertFalse(file_exists($this->uploadFolder . '/' . $result->name . '.lock'));
    }

    function testSymfonyUploadedFiles()
    {
        $files = ['abc.tmp', 'def.tmp'];

        $symfonyFiles = [];

        foreach ($files as $file) {
            $this->createTemporaryFile($file, 'first_file');

            $symfonyFiles[] = new SymfonyUploadedFile($this->tmpFolder . '/' . $file, $file);
        }

        $result = $this->handler->process($symfonyFiles);

        foreach ($result as $item) {
            $this->assertTrue(file_exists($this->uploadFolder . '/' . $item->name));
            $this->assertTrue(file_exists($this->uploadFolder . '/' . $item->name . '.lock'));

            $item->confirm();
            $this->assertFalse(file_exists($this->uploadFolder . '/' . $item->name . '.lock'));
        }
    }

    function testSingleSymfonyUploadedFile()
    {
        $file = 'abc.tmp';

        $this->createTemporaryFile($file, 'first_file');

        $symfonyFile = new SymfonyUploadedFile($this->tmpFolder . '/' . $file, $file);

        $result = $this->handler->process($symfonyFile);

        $this->assertTrue(file_exists($this->uploadFolder . '/' . $result->name));
        $this->assertTrue(file_exists($this->uploadFolder . '/' . $result->name . '.lock'));

        $result->confirm();
        $this->assertFalse(file_exists($this->uploadFolder . '/' . $result->name . '.lock'));
    }

}
