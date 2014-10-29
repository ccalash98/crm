<?php
/************************************************************************
 * This file is part of EspoCRM.
 *
 * EspoCRM - Open Source CRM application.
 * Copyright (C) 2014  Yuri Kuznetsov, Taras Machyshyn, Oleksiy Avramenko
 * Website: http://www.espocrm.com
 *
 * EspoCRM is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * EspoCRM is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with EspoCRM. If not, see http://www.gnu.org/licenses/.
 ************************************************************************/

namespace tests\Espo\Core\Upgrades\Actions;

use Espo\Core\Utils\File\Manager;
use Espo\Core\Utils\Util;
use PHPUnit_Framework_TestCase;
use tests\ReflectionHelper;


class BaseTest extends
    PHPUnit_Framework_TestCase
{

    protected $object;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject[]
     */
    protected $objects;

    protected $fileManager;

    protected $actionManagerParams = array(
        'name' => 'Extension',
        'packagePath' => 'tests/testData/Upgrades/data/upload/extensions',
        'backupPath' => 'tests/testData/Upgrades/data/.backup/extensions',
        'scriptNames' => array(
            'before' => 'BeforeInstall',
            'after' => 'AfterInstall',
            'beforeUninstall' => 'BeforeUninstall',
            'afterUninstall' => 'AfterUninstall',
        )
    );

    /**
     * @var ReflectionHelper
     */
    protected $reflection;

    public function testCreateProcessIdWithExists()
    {
        $this->setExpectedException('\Espo\Core\Exceptions\Error');
        $processId = $this->reflection->invokeMethod('createProcessId', array());
    }

    public function testCreateProcessId()
    {
        $processId = $this->reflection->setProperty('processId', null);
        $processId = $this->reflection->invokeMethod('createProcessId');
        $this->assertEquals($processId, $this->reflection->invokeMethod('getProcessId'));
    }

    public function testGetProcessId()
    {
        $this->setExpectedException('\Espo\Core\Exceptions\Error');
        $this->reflection->setProperty('processId', null);
        $this->reflection->invokeMethod('getProcessId');
    }

    public function testGetManifestIncorrect()
    {
        $this->setExpectedException('\Espo\Core\Exceptions\Error');
        $manifest = '{
            "name": "Upgrade 1.0-b3 to 1.0-b4"
        }';
        $this->objects['fileManager']
            ->expects($this->once())
            ->method('getContents')
            ->will($this->returnValue($manifest));
        $this->reflection->invokeMethod('getManifest', array());
    }

    public function testGetManifest()
    {
        $manifest = '{
            "name": "Extension Test",
            "version": "1.2.0",
            "acceptableVersions": [
            ],
            "releaseDate": "2014-09-25",
            "author": "EspoCRM",
            "description": "My Description"
        }';
        $this->objects['fileManager']
            ->expects($this->once())
            ->method('getContents')
            ->will($this->returnValue($manifest));
        $this->assertEquals(json_decode($manifest, true), $this->reflection->invokeMethod('getManifest'));
    }

    public function acceptableData()
    {
        return array(
            array('11.5.2'),
            array(array('11.5.2')),
            array(array('1.4', '11.5.2')),
            array('11.*'),
            array('11\.*'),
            array('11.5*'),
            // array( ),
        );
    }

    /**
     * @dataProvider acceptableData
     */
    public function testIsAcceptable($version)
    {
        $this->objects['config']
            ->expects($this->once())
            ->method('get')
            ->will($this->returnValue('11.5.2'));
        $this->reflection->setProperty('data', array('manifest' => array('acceptableVersions' => $version)));
        $this->assertTrue($this->reflection->invokeMethod('isAcceptable'));
    }

    public function testIsAcceptableEmpty()
    {
        $version = array();
        $this->reflection->setProperty('data', array('manifest' => array('acceptableVersions' => $version)));
        $this->assertTrue($this->reflection->invokeMethod('isAcceptable'));
    }

    public function acceptableDataFalse()
    {
        return array(
            array('1.*'),
        );
    }

    /**
     * @dataProvider acceptableDataFalse
     */
    public function testIsAcceptableFalse($version)
    {
        $this->setExpectedException('\Espo\Core\Exceptions\Error');
        $this->objects['config']
            ->expects($this->once())
            ->method('get')
            ->will($this->returnValue('11.5.2'));
        $this->reflection->setProperty('data', array('manifest' => array('acceptableVersions' => $version)));
        $this->assertFalse($this->reflection->invokeMethod('isAcceptable', array()));
    }

    public function testGetPath()
    {
        $packageId = $this->reflection->invokeMethod('getProcessId');
        $packagePath = $this->actionManagerParams['packagePath'] . DIRECTORY_SEPARATOR . $packageId;
        $packagePath = Util::fixPath($packagePath);
        $this->assertEquals($packagePath, $this->reflection->invokeMethod('getPath', array()));
        $this->assertEquals($packagePath, $this->reflection->invokeMethod('getPath', array('packagePath')));
        $postfix = $this->reflection->getProperty('packagePostfix');
        $this->assertEquals($packagePath . $postfix,
            $this->reflection->invokeMethod('getPath', array('packagePath', true)));
        $backupPath = $this->actionManagerParams['backupPath'] . DIRECTORY_SEPARATOR . $packageId;
        $backupPath = Util::fixPath($backupPath);
        $this->assertEquals($backupPath, $this->reflection->invokeMethod('getPath', array('backupPath')));
    }

    public function testCheckPackageType()
    {
        $this->reflection->setProperty('data', array('manifest' => array()));
        $this->assertTrue($this->reflection->invokeMethod('checkPackageType'));
        $this->reflection->setProperty('data', array('manifest' => array('type' => 'extension')));
        $this->assertTrue($this->reflection->invokeMethod('checkPackageType'));
    }

    public function testCheckPackageTypeUpgrade()
    {
        $this->setExpectedException('\Espo\Core\Exceptions\Error');
        $this->reflection->setProperty('data', array('manifest' => array('type' => 'upgrade')));
        $this->assertTrue($this->reflection->invokeMethod('checkPackageType'));
    }

    protected function setUp()
    {
        $this->objects['container'] = $this->getMockBuilder('\Espo\Core\Container')
            ->disableOriginalConstructor()
            ->getMock();
        $this->objects['actionManager'] = $this->getMockBuilder('\Espo\Core\Upgrades\ActionManager')
            ->disableOriginalConstructor()
            ->getMock();
        $this->objects['config'] = $this->getMockBuilder('\Espo\Core\Utils\Config')
            ->disableOriginalConstructor()
            ->getMock();
        $this->objects['fileManager'] = $this->getMockBuilder('\Espo\Core\Utils\File\Manager')->disableOriginalConstructor()->getMock();
        $map = array(
            array('config', $this->objects['config']),
            array('fileManager', $this->objects['fileManager']),
        );
        $GLOBALS['log'] = $this->getMockBuilder('\Espo\Core\Utils\Log')->disableOriginalConstructor()->getMock();
        $this->objects['container']
            ->expects($this->any())
            ->method('get')
            ->will($this->returnValueMap($map));
        $actionManagerParams = $this->actionManagerParams;
        $this->objects['actionManager']
            ->expects($this->once())
            ->method('getParams')
            ->will($this->returnValue($actionManagerParams));
        $this->object = new Base($this->objects['container'], $this->objects['actionManager']);
        $this->reflection = new ReflectionHelper($this->object);
        $this->reflection->setProperty('processId', 'ngkdf54n566n45');
        /* create a package durectory with manifest.json file */
        $packagePath = $this->reflection->invokeMethod('getPath');
        $manifestName = $this->reflection->getProperty('manifestName');
        $filename = $packagePath . '/' . $manifestName;
        $this->fileManager = new Manager();
        $this->fileManager->putContents($filename, '');
        /* END */
    }

    protected function tearDown()
    {
        $this->object = null;
        $processId = $this->reflection->getProperty('processId');
        if (isset($processId)) {
            $packagePath = $this->reflection->invokeMethod('getPath');
            $this->fileManager->removeInDir($packagePath, true);
        }
    }
}

?>
