<?php
/**
 * Created by PhpStorm.
 *
 * @author David Yell <neon1024@gmail.com>
 */

namespace Proffer\Tests\Model\Behavior;

use ArrayObject;
use Cake\Core\Plugin;
use Cake\Event\Event;
use Cake\ORM\Entity;
use Cake\ORM\Table;
use PHPUnit_Framework_TestCase;
use Proffer\Event\ProfferListener;
use Proffer\Model\Behavior\ProfferBehavior;

/**
 * Class ProfferBehaviorTest
 *
 * @package Proffer\Tests\Model\Behavior
 */
class ProfferBehaviorTest extends PHPUnit_Framework_TestCase
{

    private $config = [
        'photo' => [
            'dir' => 'photo_dir',
            'thumbnailSizes' => [
                'square' => ['w' => 200, 'h' => 200, 'crop' => true],
                'portrait' => ['w' => 100, 'h' => 300],
            ]
        ]
    ];

    /**
     * Adjust the default root so that it doesn't overwrite and user files
     */
    public function setUp()
    {
        $this->config['photo']['root'] = TMP . 'ProfferTests' . DS;
    }

    /**
     * Recursively remove files and folders
     *
     * @param $dir
     */
    private function rrmdir($dir)
    {
        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ($object != "." && $object != "..") {
                    if (filetype($dir . "/" . $object) == "dir") {
                        $this->rrmdir($dir . "/" . $object);
                    } else {
                        unlink($dir . "/" . $object);
                    }
                }
            }
            reset($objects);
            rmdir($dir);
        }
    }

    /**
     * Clear up any generated images after each test
     *
     * @return void
     */
    public function tearDown()
    {
        $this->rrmdir(TMP . 'ProfferTests' . DS);
    }

    /**
     * Generate a mock of the ProfferPath class with various set returns to ensure that the path is always consistent
     *
     * @param Table $table Instance of the table
     * @param Entity $entity Instance of the entity
     * @return \PHPUnit_Framework_MockObject_MockObject
     */
    private function getProfferPathMock(Table $table, Entity $entity)
    {
        $path = $this->getMockBuilder('Proffer\Lib\ProfferPath')
            ->setConstructorArgs([$table, $entity, 'photo', $this->config['photo']])
            ->setMethods(['fullPath', 'getFolder'])
            ->getMock();

        $path->expects($this->any())
            ->method('fullPath')
            ->with($this->logicalOr(
                $this->equalTo(null),
                $this->equalTo('square'),
                $this->equalTo('portrait')
            ))
            ->will($this->returnCallback(
                function ($param) use ($table, $entity) {
                    $filename = '';
                    if ($param !== null) {
                        $filename = $param . '_';
                    }

                    $entityFieldData = $entity->get('photo');

                    if (is_array($entityFieldData)) {
                        $filename .= $entityFieldData['name'];
                    } else {
                        $filename .= $entityFieldData;
                    }

                    return TMP . 'ProfferTests' . DS . $table->alias() .
                        DS . 'photo' . DS . 'proffer_test' . DS . $filename;
                }
            ));

        $path->expects($this->any())
            ->method('getFolder')
            ->willReturn(TMP . 'ProfferTests' . DS . $table->alias() . DS . 'photo' . DS . 'proffer_test' . DS);

        return $path;
    }

    /**
     * Data provider method for testing validation
     *
     * @return array
     */
    public function beforeMarshalProvider()
    {
        return [
            [
                ['photo' => ['error' => UPLOAD_ERR_NO_FILE]],
                true,
                []
            ],
            [
                ['photo' => ['error' => UPLOAD_ERR_NO_FILE]],
                false,
                ['photo' => ['error' => UPLOAD_ERR_NO_FILE]]
            ],
            [
                ['photo' => ['error' => UPLOAD_ERR_OK]],
                true,
                ['photo' => ['error' => UPLOAD_ERR_OK]]
            ],
            [
                ['photo' => ['error' => UPLOAD_ERR_OK]],
                false,
                ['photo' => ['error' => UPLOAD_ERR_OK]]
            ],
        ];
    }

    /**
     * @dataProvider beforeMarshalProvider
     */
    public function testBeforeMarshal(array $data, $allowEmpty, array $expected)
    {
        $table = $this->getMock('Cake\ORM\Table', ['alias']);
        $table->method('alias')
            ->willReturn('ProfferTest');

        $Proffer = new ProfferBehavior($table, $this->config);

        $validator = $this->getMock('Cake\Validation\Validator', null);
        $table->validator('test', $validator);

        if ($allowEmpty) {
            $table->validator()->allowEmpty('photo');
        }

        $arrayObject = new ArrayObject($data);

        $Proffer->beforeMarshal(
            $this->getMock('Cake\Event\Event', null, ['beforeValidate']),
            $arrayObject,
            new ArrayObject()
        );
        $result = $arrayObject;

        $this->assertEquals(new ArrayObject($expected), $result);
    }

    /**
     * Data provider method for testing valid file uploads
     *
     * @return array
     */
    public function validFileProvider()
    {
        return [
            [
                [
                    'photo' => [
                        'name' => 'image_640x480.jpg',
                        'tmp_name' => Plugin::path('Proffer') . 'tests' . DS . 'Fixture' . DS . 'image_640x480.jpg',
                        'size' => 33000,
                        'error' => UPLOAD_ERR_OK
                    ],
                    'photo_dir' => 'proffer_test'
                ],
                [
                    'filename' => 'image_640x480.jpg',
                    'dir' => 'proffer_test'
                ]
            ],
            [
                [
                    'photo' => [
                        'name' => 'image_480x640.jpg',
                        'tmp_name' => Plugin::path('Proffer') . 'tests' . DS . 'Fixture' . DS . 'image_480x640.jpg',
                        'size' => 45704,
                        'error' => UPLOAD_ERR_OK
                    ],
                    'photo_dir' => 'proffer_test'
                ],
                [
                    'filename' => 'image_480x640.jpg',
                    'dir' => 'proffer_test'
                ]
            ],
            [
                [
                    'photo' => [
                        'name' => 'image_480x640.jpg',
                        'tmp_name' => Plugin::path('Proffer') . 'tests' . DS . 'Fixture' . DS . 'image_480x640.jpg',
                        'size' => 45704,
                        'error' => UPLOAD_ERR_OK
                    ],
                    'photo_dir' => 'proffer_test'
                ],
                [
                    'filename' => 'image_480x640.jpg',
                    'dir' => 'proffer_test'
                ]
            ],
        ];
    }

    /**
     * A bit of a unit and integration test as it will still dispatch the events to the listener
     *
     * @dataProvider validFileProvider
     */
    public function testBeforeSaveWithValidFile(array $entityData, array $expected)
    {
        $table = $this->getMock('Cake\ORM\Table', ['alias']);
        $table->method('alias')
            ->willReturn('ProfferTest');

        $entity = new Entity($entityData);
        $path = $this->getProfferPathMock($table, $entity, 'photo');

        $Proffer = $this->getMockBuilder('Proffer\Model\Behavior\ProfferBehavior')
            ->setConstructorArgs([$table, $this->config])
            ->setMethods(['moveUploadedFile'])
            ->getMock();

        $Proffer->expects($this->once())
            ->method('moveUploadedFile')
            ->willReturnCallback(function ($source, $destination) {
                if (!file_exists(pathinfo($destination, PATHINFO_DIRNAME))) {
                    mkdir(pathinfo($destination, PATHINFO_DIRNAME), 0777, true);
                }
                return copy($source, $destination);
            });

        $Proffer->beforeSave(
            $this->getMock('Cake\Event\Event', null, ['beforeSave']),
            $entity,
            new ArrayObject(),
            $path
        );

        $this->assertEquals($expected['filename'], $entity->get('photo'));
        $this->assertEquals($expected['dir'], $entity->get('photo_dir'));

        $testUploadPath = $path->getFolder();

        $this->assertFileExists($testUploadPath . $expected['filename']);
        $this->assertFileExists($testUploadPath . 'portrait_' . $expected['filename']);
        $this->assertFileExists($testUploadPath . 'square_' . $expected['filename']);
        
        $portraitSizes = getimagesize($testUploadPath . 'portrait_' . $expected['filename']);
        $this->assertEquals(100, $portraitSizes[0]);
        
        $squareSizes = getimagesize($testUploadPath . 'square_' . $expected['filename']);
        $this->assertEquals(200, $squareSizes[0]);
        $this->assertEquals(200, $squareSizes[1]);
    }

    /**
     * @expectedException Exception
     */
    public function testBeforeSaveWithoutUploadingAFile()
    {
        $table = $this->getMock('Cake\ORM\Table', ['alias']);
        $table->method('alias')
            ->willReturn('ProfferTest');

        $path = $this->getProfferPathMock(
            $table,
            new Entity(['photo' => 'image_640x480.jpg', 'photo_dir' => 'proffer_test']),
            'photo'
        );

        $Proffer = $this->getMockBuilder('Proffer\Model\Behavior\ProfferBehavior')
            ->setConstructorArgs([$table, $this->config])
            ->setMethods(['moveUploadedFile'])
            ->getMock();

        $Proffer->expects($this->once())
            ->method('moveUploadedFile')
            ->willReturn(false);

        $entity = new Entity([
            'photo' => [
                'name' => '',
                'tmp_name' => '',
                'size' => '',
                'error' => UPLOAD_ERR_OK
            ]
        ]);

        $Proffer->beforeSave(
            $this->getMock('Cake\Event\Event', null, ['beforeSave']),
            $entity,
            new ArrayObject(),
            $path
        );
    }

    /**
     * @expectedException Exception
     */
    public function testFailedToMoveFile()
    {
        $table = $this->getMock('Cake\ORM\Table', ['alias']);
        $table->method('alias')
            ->willReturn('ProfferTest');

        $Proffer = $this->getMockBuilder('Proffer\Model\Behavior\ProfferBehavior')
            ->setConstructorArgs([$table, $this->config])
            ->setMethods(['moveUploadedFile'])
            ->getMock();

        $Proffer->expects($this->once())
            ->method('moveUploadedFile')
            ->willReturn(false);

        $entity = new Entity([
            'photo' => [
                'name' => 'image_640x480.jpg',
                'tmp_name' => Plugin::path('Proffer') . 'tests' . DS . 'Fixture' . DS . 'image_640x480.jpg',
                'size' => 33000,
                'error' => UPLOAD_ERR_OK
            ]
        ]);

        $path = $this->getProfferPathMock($table, $entity, 'photo');

        $Proffer->beforeSave(
            $this->getMock('Cake\Event\Event', null, ['beforeSave']),
            $entity,
            new ArrayObject(),
            $path
        );
    }

    /**
     * Test afterDelete
     */
    public function testAfterDelete()
    {
        $table = $this->getMock('Cake\ORM\Table', ['alias']);
        $table->method('alias')
            ->willReturn('ProfferTest');

        $Proffer = new ProfferBehavior($table, $this->config);

        $entity = new Entity([
            'photo' => 'image_640x480.jpg',
            'photo_dir' => 'proffer_test'
        ]);

        $path = $this->getProfferPathMock($table, $entity, 'photo');
        $testUploadPath = $path->getFolder();

        if (!file_exists($testUploadPath)) {
            mkdir($testUploadPath, 0777, true);
        }

        copy(
            Plugin::path('Proffer') . 'tests' . DS . 'Fixture' . DS . 'image_640x480.jpg',
            $testUploadPath . 'image_640x480.jpg'
        );
        copy(
            Plugin::path('Proffer') . 'tests' . DS . 'Fixture' . DS . 'image_640x480.jpg',
            $testUploadPath . 'square_image_640x480.jpg'
        );
        copy(
            Plugin::path('Proffer') . 'tests' . DS . 'Fixture' . DS . 'image_640x480.jpg',
            $testUploadPath . 'portrait_image_640x480.jpg'
        );

        $Proffer->afterDelete(
            $this->getMock('Cake\Event\Event', null, ['afterDelete']),
            $entity,
            new ArrayObject(),
            $path
        );

        $this->assertFileNotExists($testUploadPath . 'image_640x480.jpg');
        $this->assertFileNotExists($testUploadPath . 'square_image_640x480.jpg');
        $this->assertFileNotExists($testUploadPath . 'portrait_image_640x480.jpg');
    }

    public function testAfterDeleteWithMissingFiles()
    {
        $table = $this->getMock('Cake\ORM\Table', ['alias']);
        $table->method('alias')
            ->willReturn('ProfferTest');

        $Proffer = new ProfferBehavior($table, $this->config);

        $entity = new Entity([
            'photo' => 'image_640x480.jpg',
            'photo_dir' => 'proffer_test'
        ]);

        $path = $this->getProfferPathMock($table, $entity, 'photo');
        $testUploadPath = $path->getFolder();

        if (!file_exists($testUploadPath)) {
            mkdir($testUploadPath, 0777, true);
        }

        copy(
            Plugin::path('Proffer') . 'tests' . DS . 'Fixture' . DS . 'image_640x480.jpg',
            $testUploadPath . 'image_640x480.jpg'
        );

        $Proffer->afterDelete(
            $this->getMock('Cake\Event\Event', null, ['afterDelete']),
            $entity,
            new ArrayObject(),
            $path
        );

        $this->assertFileNotExists($testUploadPath . 'image_640x480.jpg');
        $this->assertFileNotExists($testUploadPath . 'square_image_640x480.jpg');
        $this->assertFileNotExists($testUploadPath . 'portrait_image_640x480.jpg');
    }

    public function testBeforePathEvent()
    {
        $entityData = [
            'photo' => [
                'name' => 'image_640x480.jpg',
                'tmp_name' => Plugin::path('Proffer') . 'tests' . DS . 'Fixture' . DS . 'image_640x480.jpg',
                'size' => 33000,
                'error' => UPLOAD_ERR_OK
            ],
            'photo_dir' => 'proffer_test'
        ];
        $entity = new Entity($entityData);

        $eventManager = $this->getMock('Cake\Event\EventManager');

        $table = $this->getMockBuilder('Cake\ORM\Table')
            ->setConstructorArgs([['eventManager' => $eventManager]])
            ->setMethods(['alias'])
            ->getMock();

        $table->method('alias')
            ->willReturn('ProfferTest');

        $path = $this->getProfferPathMock($table, $entity, 'photo');

        $event = new Event('Proffer.afterPath', $entity, ['path' => $path]);

        $eventManager->expects($this->once())
            ->method('dispatch')
            ->with($this->equalTo($event));

        $Proffer = $this->getMockBuilder('Proffer\Model\Behavior\ProfferBehavior')
            ->setConstructorArgs([$table, $this->config])
            ->setMethods(['moveUploadedFile', 'makeThumbs'])
            ->getMock();

        $Proffer->expects($this->once())
            ->method('moveUploadedFile')
            ->will($this->returnCallback(
                function ($param) use ($entity, $path) {
                    return copy($entity->get('photo')['tmp_name'], $path->fullPath());
                }
            ));

        $Proffer->expects($this->once())
            ->method('makeThumbs')
            ->willReturn(null);

        $Proffer->beforeSave(
            $this->getMock('Cake\Event\Event', null, ['beforeSave']),
            $entity,
            new ArrayObject(),
            $path
        );
    }

    public function testAllEvents()
    {
        $entityData = [
            'photo' => [
                'name' => 'image_640x480.jpg',
                'tmp_name' => Plugin::path('Proffer') . 'tests' . DS . 'Fixture' . DS . 'image_640x480.jpg',
                'size' => 33000,
                'error' => UPLOAD_ERR_OK
            ],
            'photo_dir' => 'proffer_test'
        ];
        $entity = new Entity($entityData);

        $table = $this->getMockBuilder('Cake\ORM\Table')
            ->setMethods(['alias'])
            ->getMock();

        $table->method('alias')
            ->willReturn('ProfferTest');

        $path = $this->getProfferPathMock($table, $entity, 'photo');

        $listener = $this->getMockBuilder('Proffer\Event\ProfferListener')
            ->setMethods(['beforeThumbs', 'afterThumbs'])
            ->getMock();

        $table->eventManager()->on($listener);

        // Twice because we are generating two sizes of thumbnail
        $listener->expects($this->exactly(2))
            ->method('beforeThumbs');
        $listener->expects($this->exactly(2))
            ->method('afterThumbs');

        $Proffer = $this->getMockBuilder('Proffer\Model\Behavior\ProfferBehavior')
            ->setConstructorArgs([$table, $this->config])
            ->setMethods(['moveUploadedFile'])
            ->getMock();

        $Proffer->expects($this->once())
            ->method('moveUploadedFile')
            ->will($this->returnCallback(
                function ($param) use ($entity, $path) {
                    return copy($entity->get('photo')['tmp_name'], $path->fullPath());
                }
            ));

        $Proffer->beforeSave(
            $this->getMock('Cake\Event\Event', null, ['beforeSave']),
            $entity,
            new ArrayObject(),
            $path
        );
    }

    public function testThumbsNotCreatedWhenNoSizes()
    {
        $table = $this->getMock('Cake\ORM\Table', ['alias']);
        $table->method('alias')
            ->willReturn('ProfferTest');

        $config = $this->config;
        unset($config['photo']['thumbnailSizes']);

        $entityData = [
            'photo' => [
                'name' => 'image_640x480.jpg',
                'tmp_name' => Plugin::path('Proffer') . 'tests' . DS . 'Fixture' . DS . 'image_640x480.jpg',
                'size' => 33000,
                'error' => UPLOAD_ERR_OK
            ],
            'photo_dir' => 'proffer_test'
        ];
        $entity = new Entity($entityData);
        $path = $this->getProfferPathMock($table, $entity, 'photo');

        $Proffer = $this->getMockBuilder('Proffer\Model\Behavior\ProfferBehavior')
            ->setConstructorArgs([$table, $config])
            ->setMethods(['moveUploadedFile'])
            ->getMock();

        $Proffer->expects($this->once())
            ->method('moveUploadedFile')
            ->willReturnCallback(function ($source, $destination) {
                if (!file_exists(pathinfo($destination, PATHINFO_DIRNAME))) {
                    mkdir(pathinfo($destination, PATHINFO_DIRNAME), 0777, true);
                }
                return copy($source, $destination);
            });

        $Proffer->beforeSave(
            $this->getMock('Cake\Event\Event', null, ['beforeSave']),
            $entity,
            new ArrayObject(),
            $path
        );

        $this->assertEquals('image_640x480.jpg', $entity->get('photo'));
        $this->assertEquals('proffer_test', $entity->get('photo_dir'));

        $testUploadPath = $path->getFolder();

        $this->assertFileExists($testUploadPath . 'image_640x480.jpg');
        $this->assertFileNotExists($testUploadPath . 'portrait_image_640x480.jpg');
        $this->assertFileNotExists($testUploadPath . 'square_image_640x480.jpg');
    }

    public function testChangingThePathUsingEvents()
    {
        $table = $this->getMock('Cake\ORM\Table', ['alias']);
        $table->method('alias')
            ->willReturn('ProfferTest');

        $listener = $this->getMockBuilder('Cake\Event\EventListenerInterface')
            ->setMethods(['implementedEvents', 'filename'])
            ->getMock();

        $listener->expects($this->once())
            ->method('implementedEvents')
            ->willReturn(['Proffer.afterPath' => 'filename']);

        $listener->expects($this->once())
            ->method('filename')
            ->willReturnCallback(function ($event, $path) {
                $path->setTable('proffer_path_event_test');
                $path->setSeed('proffer_event_test');
                $path->setFilename('event_image_640x480.jpg');

                $event->subject()['photo']['name'] = 'event_image_640x480.jpg';

                return $path;
            });

        $table->eventManager()->on($listener);

        $entityData = [
            'photo' => [
                'name' => 'image_640x480.jpg',
                'tmp_name' => Plugin::path('Proffer') . 'tests' . DS . 'Fixture' . DS . 'image_640x480.jpg',
                'size' => 33000,
                'error' => UPLOAD_ERR_OK
            ],
            'photo_dir' => 'proffer_test'
        ];
        $entity = new Entity($entityData);
        $path = $this->getProfferPathMock($table, $entity, 'photo');

        $Proffer = $this->getMockBuilder('Proffer\Model\Behavior\ProfferBehavior')
            ->setConstructorArgs([$table, $this->config])
            ->setMethods(['moveUploadedFile'])
            ->getMock();

        $Proffer->expects($this->once())
            ->method('moveUploadedFile')
            ->willReturnCallback(function ($source, $destination) {
                if (!file_exists(pathinfo($destination, PATHINFO_DIRNAME))) {
                    mkdir(pathinfo($destination, PATHINFO_DIRNAME), 0777, true);
                }
                return copy($source, $destination);
            });

        $Proffer->beforeSave(
            $this->getMock('Cake\Event\Event', null, ['beforeSave']),
            $entity,
            new ArrayObject(),
            $path
        );

        $this->assertEquals('event_image_640x480.jpg', $entity->get('photo'));
        $this->assertEquals('proffer_event_test', $entity->get('photo_dir'));

        $testUploadPath = $path->getFolder();

        $this->assertFileExists($testUploadPath . 'event_image_640x480.jpg');
        $this->assertFileExists($testUploadPath . 'portrait_event_image_640x480.jpg');
        $this->assertFileExists($testUploadPath . 'square_event_image_640x480.jpg');
    }
}
