<?php

namespace Asticode\DataMapper\Tests\Mapper;

use Asticode\DataMapper\Tests\BaseDataMapperTest;
use Asticode\DataMapper\Mapper\AbstractMapper;
use Aura\Sql\ExtendedPdo;
use PDOStatement;

/**
 * @author apally
 */
class BaseMapperTest extends BaseDataMapperTest
{

    public function testTrue()
    {
        $this->assertTrue(true);
    }

    public function getMapper()
    {
        return $this->getMockBuilder(AbstractMapper::class)->getMock();
    }

    /**
     * @return ExtendedPdo
     */
    protected function getMockOfPdo()
    {
        if (!$this->mockOfPdoStatement) {
            $this->getMockOfPdoStatement();
        }
        $this->mockOfPdo = $this->getMockBuilder(ExtendedPdo::class)->disableOriginalConstructor()->getMock();
        $this->mockOfPdo->method('prepare')->willReturn($this->mockOfPdoStatement);
        $this->mockOfPdo->method('getPdo')->willReturn($this->mockOfPdo);
        return $this->mockOfPdo;
    }

    /**
     * @return ExtendedPdo
     */
    protected function getMockOfPdoStatement()
    {
        $this->mockOfPdoStatement = $this->getMockBuilder(PDOStatement::class)->disableOriginalConstructor()->getMock();
        return $this->mockOfPdoStatement;
//        $this->statement->method('fetch')->willReturn([
//            'id' => 1,
//        ]);
//        $pdo             = $this->getMockBuilder(ExtendedPdo::class)->disableOriginalConstructor()->getMock();
//        $pdo->method('prepare')->willReturn($this->statement);
//        return $pdo;
    }

}
