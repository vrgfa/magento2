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
 * @category    Mage
 * @package     Mage_Core
 * @subpackage  unit_tests
 * @copyright   Copyright (c) 2013 X.commerce, Inc. (http://www.magentocommerce.com)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Test class Mage_Core_Controller_Varien_ActionAbstract
 */
class Mage_Core_Controller_Varien_ActionAbstractTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var Mage_Core_Controller_Varien_ActionAbstract
     */
    protected $_actionAbstract;

    /**
     * @var Mage_Core_Controller_Request_Http|PHPUnit_Framework_MockObject_MockObject
     */
    protected $_request;

    /**
     * @var Mage_Core_Controller_Response_Http|PHPUnit_Framework_MockObject_MockObject
     */
    protected $_response;

    /**
     * Setup before tests
     *
     * Create request, response and forward action (child of ActionAbstract)
     */
    public function setUp()
    {
        $this->_request = $this->getMock('Mage_Core_Controller_Request_Http',
            array('getRequestedRouteName', 'getRequestedControllerName', 'getRequestedActionName'), array(), '', false
        );
        $this->_response = $this->getMock('Mage_Core_Controller_Response_Http', array(), array(), '', false);
        $this->_response->headersSentThrowsException = false;
        $this->_actionAbstract = new Mage_Core_Controller_Varien_Action_Forward($this->_request, $this->_response,
            'Area'
        );
    }

    /**
     * Test for __construct method
     *
     * @test
     * @covers Mage_Core_Controller_Varien_ActionAbstract::__construct
     */
    public function testConstruct()
    {
        $this->assertAttributeInstanceOf('Mage_Core_Controller_Request_Http', '_request', $this->_actionAbstract);
        $this->assertAttributeInstanceOf('Mage_Core_Controller_Response_Http', '_response', $this->_actionAbstract);
        $this->assertAttributeEquals('Area', '_currentArea', $this->_actionAbstract);
    }

    /**
     * Test for getRequest method
     *
     * @test
     * @covers Mage_Core_Controller_Varien_ActionAbstract::getRequest
     */
    public function testGetRequest()
    {
        $this->assertEquals($this->_request, $this->_actionAbstract->getRequest());
    }

    /**
     * Test for getResponse method
     *
     * @test
     * @covers Mage_Core_Controller_Varien_ActionAbstract::getResponse
     */
    public function testGetResponse()
    {
        $this->assertEquals($this->_response, $this->_actionAbstract->getResponse());
    }

    /**
     * Test for getResponse method. Checks that response headers are set correctly
     *
     * @test
     * @covers Mage_Core_Controller_Varien_ActionAbstract::getResponse
     */
    public function testResponseHeaders()
    {
        $request = new Mage_Core_Controller_Request_Http();
        $response = new Mage_Core_Controller_Response_Http();
        $response->headersSentThrowsException = false;
        $action = new Mage_Core_Controller_Varien_Action_Forward($request, $response, 'Area');

        $headers = array(
            array(
                'name' => 'X-Frame-Options',
                'value' => 'SAMEORIGIN',
                'replace' => false
            )
        );

        $this->assertEquals($headers, $action->getResponse()->getHeaders());
    }

    /**
     * Test for getFullActionName method
     *
     * @test
     * @covers Mage_Core_Controller_Varien_ActionAbstract::getFullActionName
     */
    public function testGetFullActionName()
    {
        $this->_request->expects($this->once())
            ->method('getRequestedRouteName')
            ->will($this->returnValue('adminhtml'));

        $this->_request->expects($this->once())
            ->method('getRequestedControllerName')
            ->will($this->returnValue('index'));

        $this->_request->expects($this->once())
            ->method('getRequestedActionName')
            ->will($this->returnValue('index'));

        $this->assertEquals('adminhtml_index_index', $this->_actionAbstract->getFullActionName());
    }
}
