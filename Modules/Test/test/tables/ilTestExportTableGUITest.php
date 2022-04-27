<?php declare(strict_types=1);

/* Copyright (c) 1998-2020 ILIAS open source, Extended GPL, see docs/LICENSE */

/**
 * Class ilTestExportTableGUITest
 * @author Marvin Beym <mbeym@databay.de>
 */
class ilTestExportTableGUITest extends ilTestBaseTestCase
{
    private ilTestExportTableGUI $tableGui;
    private ilObjTestGUI $parentObj_mock;

    protected function setUp() : void
    {
        parent::setUp();

        $lng_mock = $this->createMock(ilLanguage::class);
        $ctrl_mock = $this->createMock(ilCtrl::class);
        $ctrl_mock->expects($this->any())
                  ->method("getFormAction")
                  ->willReturnCallback(function () {
                      return "testFormAction";
                  });

        $this->setGlobalVariable("lng", $lng_mock);
        $this->setGlobalVariable("ilCtrl", $ctrl_mock);
        $this->setGlobalVariable("tpl", $this->createMock(ilGlobalPageTemplate::class));
        $this->setGlobalVariable("component.repository", $this->createMock(ilComponentRepository::class));
        $component_factory = $this->createMock(ilComponentFactory::class);
        $component_factory->method("getActivePluginsInSlot")->willReturn(new ArrayIterator());
        $this->setGlobalVariable("component.factory", $component_factory);
        $this->setGlobalVariable("ilPluginAdmin", new ilPluginAdmin($this->createMock(ilComponentRepository::class)));
        $this->setGlobalVariable("ilDB", $this->createMock(ilDBInterface::class));
        $this->setGlobalVariable("ilAccess", $this->createMock(ilAccessHandler::class));

        $this->parentObj_mock = $this->getMockBuilder(ilObjTestGUI::class)->disableOriginalConstructor()->onlyMethods(array('getObject'))->getMock();
        $this->parentObj_mock->expects($this->any())->method('getObject')->willReturn($this->createMock(ilObjTest::class));
        $this->tableGui = new ilTestExportTableGUI(
            $this->parentObj_mock,
            "",
            $this->createMock(ilObjTest::class)
        );
    }

    public function test_instantiateObject_shouldReturnInstance() : void
    {
        $this->assertInstanceOf(ilTestExportTableGUI::class, $this->tableGui);
    }

    public function testNumericOrdering() : void
    {
        $this->assertTrue($this->tableGui->numericOrdering("size"));
        $this->assertTrue($this->tableGui->numericOrdering("date"));
        $this->assertFalse($this->tableGui->numericOrdering("randomString"));
    }
}