<?php
/**
 * Magento
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magentocommerce.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Magento to newer
 * versions in the future. If you wish to customize Magento for your
 * needs please refer to http://www.magentocommerce.com for more information.
 *
 * @category    Magento
 * @package     Mage_Core
 * @subpackage  unit_tests
 * @copyright   Copyright (c) 2013 X.commerce, Inc. (http://www.magentocommerce.com)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Core_Block_AbstractTest extends PHPUnit_Framework_TestCase
{
    /**
     * @param string $expectedResult
     * @param string $nameInLayout
     * @param array $methodArguments
     * @dataProvider getUiIdDataProvider
     */
    public function testGetUiId($expectedResult, $nameInLayout, $methodArguments)
    {
        /** @var $block Mage_Core_Block_Abstract|PHPUnit_Framework_MockObject_MockObject */
        $block = $this->getMockForAbstractClass('Mage_Core_Block_Abstract', array(), '', false);
        $block->setNameInLayout($nameInLayout);

        $this->assertEquals(
            $expectedResult,
            call_user_func_array(array($block, 'getUiId'), $methodArguments)
        );
    }

    /**
     * @return array
     */
    public function getUiIdDataProvider()
    {
        return array(
            array(' data-ui-id="" ', null, array()),
            array(' data-ui-id="block" ', 'block', array()),
            array(' data-ui-id="block" ', 'block---', array()),
            array(' data-ui-id="block" ', '--block', array()),
            array(' data-ui-id="bl-ock" ', '--bl--ock---', array()),
            array(' data-ui-id="bl-ock" ', '--bL--Ock---', array()),
            array(' data-ui-id="b-l-o-c-k" ', '--b!@#$%^&**()L--O;:...c<_>k---', array()),
            array(' data-ui-id="a0b1c2d3e4f5g6h7-i8-j9k0l1m2n-3o4p5q6r7-s8t9u0v1w2z3y4x5" ',
                'a0b1c2d3e4f5g6h7', array('i8-j9k0l1m2n-3o4p5q6r7', 's8t9u0v1w2z3y4x5')
            ),
            array(' data-ui-id="capsed-block-name-cap-ed-param1-caps2-but-ton" ',
                'CaPSed BLOCK NAME', array('cAp$Ed PaRaM1', 'caPs2', 'bUT-TOn')
            ),
            array(' data-ui-id="block-0-1-2-3-4-5-6-7-8-9-10-11-12-13-14-15-16-17-18-19-20" ',
                '!block!', range(0, 20)
            ),
        );
    }

    public function testGetVar()
    {
        $config = $this->getMock('Magento_Config_View', array('getVarValue'), array(), '', false);
        $module = uniqid();
        $config->expects($this->at(0))->method('getVarValue')->with('Mage_Core', 'v1')->will($this->returnValue('one'));
        $config->expects($this->at(1))->method('getVarValue')->with($module, 'v2')->will($this->returnValue('two'));

        $configManager = $this->getMock('Mage_Core_Model_View_Config', array(), array(), '', false);
        $configManager->expects($this->exactly(2))->method('getViewConfig')->will($this->returnValue($config));

        /** @var $block Mage_Core_Block_Abstract|PHPUnit_Framework_MockObject_MockObject */
        $params = array(
            'viewConfig' => $configManager,
        );
        $helper = new Magento_Test_Helper_ObjectManager($this);
        $block = $this->getMockForAbstractClass('Mage_Core_Block_Abstract',
            $helper->getConstructArguments('Mage_Core_Block_Abstract', $params),
            uniqid('Mage_Core_Block_Abstract_')
        );

        $this->assertEquals('one', $block->getVar('v1'));
        $this->assertEquals('two', $block->getVar('v2', $module));
    }
}
