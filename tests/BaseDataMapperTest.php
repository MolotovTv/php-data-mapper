<?php

namespace Asticode\DataMapper\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Description of BaseReplayTest
 *
 * @author apally
 */
abstract class BaseDataMapperTest extends TestCase
{

    /** @var Application */
    private static $__application;

    public static function setUpBeforeClass()
    {
        $params = array_flip($_SERVER['argv']);
        if (isset($params['log=1'])) {
            self::$printLog = true;
        }
        parent::setUpBeforeClass();
    }

    protected function tearDown()
    {
        parent::tearDown();
//        $this->getLogs();
    }

    /**
     * Call protected/private method of a class.
     *
     * @param object &$object    Instantiated object that we will run method on.
     * @param string $methodName Method name to call
     * @param array  $parameters Array of parameters to pass into method.
     *
     * @return mixed Method return.
     */
    protected function invokeMethod(&$object, $methodName, array $parameters = array())
    {
        $reflection = new \ReflectionClass(get_class($object));
        $method     = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $parameters);
    }
    /**
     * recursive method for merge datas
     * 
     * @param array $defaultDatas
     * @param array $ownDatas
     * @return array
     */
    public static function mergeDatas($defaultDatas = [], $ownDatas = [])
    {
        $datas = [];

        foreach ($defaultDatas as $key => $value) {
            if (array_key_exists($key, $ownDatas)) {
                if (is_array($value)) {
                    $datas[$key] = self::mergeDatas($value, (array) $ownDatas[$key]);
                } else {
                    $datas[$key] = $ownDatas[$key];
                }
            } else {
                $datas[$key] = $value;
            }
        }
        return $datas;
    }

    protected function assertArrayHaveSameInternalType($expected, $result, $oldKey = '$result')
    {
//        echo print_r($expected, true);
//        echo print_r($result, true);
//        die('ok');

        foreach ($expected as $key => $value) {
            $this->assertArrayHasKey($key, $result);
            $this->assertInternalType(gettype($value), $result[$key], "Fail on key `$key` for the n-1 key `$oldKey`");
            if (is_array($value)) {
                $this->assertArrayHaveSameInternalType($value, $result[$key], $oldKey . "['" . $key . "']");
            }
        }
    }

    public static function now()
    {
        return gmdate('Y-m-d H:i:s');
    }

    /**
     * 
     * @param type $obj
     * @param type $method
     * @param type $exc
     * @return $this
     */
    protected function mustThrowException($obj, $method, $exc = null)
    {
        if (!$exc) {
            $exc = new \Exception();
        }
        $obj->method($method)->willThrowException($exc);
        return $this;
    }

    protected function listMethods($obj)
    {
        $reflection = new \ReflectionClass(get_class($obj));
        foreach ($reflection->getMethods() as $oMethod) {
            echo PHP_EOL . $oMethod->class . ' : ' . $oMethod->getName();
        }
    }

    protected function must($count)
    {
        return new Matcher_InvokedMust($count);
    }

    public static final function getTestsDirectory($subFolder = '')
    {
        return sprintf('%s/%s', dirname(__FILE__), $subFolder);
    }

}

class Matcher_InvokedMust extends \PHPUnit_Framework_MockObject_Matcher_InvokedRecorder
{

    /**
     * @var int
     */
    protected $expectedCount;

    /**
     * @param int $expectedCount
     */
    public function __construct($expectedCount)
    {
        $this->expectedCount = $expectedCount;
    }

    /**
     * @return bool
     */
    public function isNever()
    {
        return $this->getExpectedCount() == 0;
    }

    /**
     * @return string
     */
    public function toString()
    {
        return 'invoked ' . $this->getExpectedCount() . ' time(s)';
    }

    /**
     * @param PHPUnit_Framework_MockObject_Invocation $invocation
     *
     * @throws PHPUnit_Framework_ExpectationFailedException
     */
    public function invoked(\PHPUnit_Framework_MockObject_Invocation $invocation)
    {
        parent::invoked($invocation);

        $count = $this->getInvocationCount();

        if ($count > $this->getExpectedCount()) {
            $message = $invocation->toString() . ' ';

            switch ($this->getExpectedCount()) {
                case 0: {
                        $message .= 'was not expected to be called.';
                    }
                    break;

                case 1: {
                        $message .= 'was not expected to be called more than once.';
                    }
                    break;

                default: {
                        $message .= sprintf(
                                'was not expected to be called more than %d times.', $this->getExpectedCount()
                        );
                    }
            }

            throw new \PHPUnit_Framework_ExpectationFailedException($message);
        }
    }

    /**
     * Verifies that the current expectation is valid. If everything is OK the
     * code should just return, if not it must throw an exception.
     *
     * @throws PHPUnit_Framework_ExpectationFailedException
     */
    public function verify()
    {
        $count = $this->getInvocationCount();

        if ($count !== $this->getExpectedCount()) {
            throw new \PHPUnit_Framework_ExpectationFailedException(
            sprintf(
                    'Method was expected to be called %d times, ' .
                    'actually called %d times.', $this->getExpectedCount(), $count
            )
            );
        }
    }

    private function getExpectedCount()
    {
        return (int) (string) $this->expectedCount;
    }

}
