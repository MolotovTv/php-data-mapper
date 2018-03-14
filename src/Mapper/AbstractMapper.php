<?php

/*
 *
 * Author of this code is Geoffroy Aubry => https://github.com/geoffroy-aubry
 *
 */

namespace Asticode\DataMapper\Mapper;

use Aura\Sql\ExtendedPdoInterface;
use Aura\Sql\ExtendedPdo;

/**
 * Mapper's common functions.
 */
abstract class AbstractMapper
{

    use TransactionTrait;

    const WHERE_SEPARATOR_AND = ' AND ';
    const WHERE_SEPARATOR_OR  = ' OR ';

    /**
     * @var ExtendedPdoInterface
     */
    private $oPdoSlave;

    /**
     * @var ExtendedPdoInterface
     */
    private $oPdoMaster;

    /**
     * @var ExtendedPdoInterface
     */
    private $oPdoForcedUse;

    /**
     *
     * @var array
     */
    private $aMap;

    /**
     * List of columns to automatically json_encode/decode.
     * @var array
     */
    protected $aJsonColumns;

    /**
     * Options to use when json_encode().
     * @var int
     * @see http://php.net/manual/en/json.constants.php
     */
    private $iJsonEncodeOptions;

    /**
     * @param ExtendedPdoInterface $oPdoMaster
     */
    public function __construct(ExtendedPdoInterface $oPdoMaster, ExtendedPdoInterface $oPdoSlave)
    {
        $this->oPdoSlave          = $oPdoSlave;
        $this->oPdoMaster         = $oPdoMaster;
        $this->iJsonEncodeOptions = JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK | JSON_UNESCAPED_SLASHES;
        $this->aJsonColumns       = [];
        $this->aBinaryColumns     = [];
    }

    /**
     * 
     * @return ExtendedPdoInterface
     */
    protected function getPdoSlave()
    {
        if (!empty($this->oPdoForcedUse)) {
            return $this->oPdoForcedUse;
        }
        return $this->oPdoSlave;
    }

    /**
     * 
     * @return ExtendedPdoInterface
     */
    protected function getPdoMaster()
    {
        if (!empty($this->oPdoForcedUse)) {
            return $this->oPdoForcedUse;
        }
        return $this->oPdoMaster;
    }

    /**
     * @return $this
     */
    public function forcedUseSlave()
    {
        $this->forcedUsePdo($this->oPdoSlave);
        return $this;
    }

    /**
     * @return $this
     */
    public function forcedUseMaster()
    {
        $this->forcedUsePdo($this->oPdoMaster);
        return $this;
    }

    /**
     * @param ExtendedPdoInterface
     * @return ExtendedPdoInterface
     */
    private function forcedUsePdo($oPdo)
    {
        $this->oPdoForcedUse = $oPdo;
    }

    /**
     * 
     */
    private function unsetForcedUsePdo()
    {
        $this->oPdoForcedUse = null;
    }

    /**
     * @param array $aParameters
     */
    public function formatToDb(array &$aParameters)
    {
        foreach ($this->aJsonColumns as $sColumn) {
            if (isset($aParameters[$sColumn])) {
                ksort($aParameters[$sColumn], SORT_STRING);
                $aParameters[$sColumn] = json_encode($aParameters[$sColumn], $this->iJsonEncodeOptions);
            }
        }
        foreach ($this->aBinaryColumns as $sColumn) {
            if (isset($aParameters[$sColumn])) {
                $aParameters[$sColumn] = hex2bin($aParameters[$sColumn]);
            }
        }
    }

    /**
     * @param array $aParameters
     */
    public function formatFromDb(array &$aParameters)
    {
        foreach ($this->aJsonColumns as $sColumn) {
            if (isset($aParameters[$sColumn])) {
                $aParameters[$sColumn] = json_decode($aParameters[$sColumn], true);
            }
        }
        foreach ($this->aBinaryColumns as $sColumn) {
            if (isset($aParameters[$sColumn])) {
                $aParameters[$sColumn] = bin2hex($aParameters[$sColumn]);
            }
        }
    }

    /**
     * Returns a where expression (without WHERE keyword) with placeholders instead of values.
     *
     * @param array $aColumnNames List of column's names.
     * @param string $sSeparator Separator between clauses, e.g. ' AND '.
     * @return string
     */
    public function buildWherePlaceholders(array $aColumnNames, $sSeparator)
    {
        $aQueryWhere = [];
        foreach ($aColumnNames as $sColumnName) {
            $aQueryWhere[] = "`$sColumnName`=:$sColumnName";
        }
        $sQueryWhere = implode($sSeparator, $aQueryWhere);
        return $sQueryWhere;
    }

    /**
     * @param string $sEntityName
     * @param array $aWhere
     * @param string $sOrderBy
     * @param int $iLimit
     * @param int $iOffset
     * @return array
     */
    public function buildSelectQuery($sEntityName, array $aWhere, $sOrderBy = '', $iLimit = 0, $iOffset = 0)
    {
        return $this->buildSelect($sEntityName, $aWhere, self::WHERE_SEPARATOR_AND, $sOrderBy, $iLimit, $iOffset);
    }

    /**
     * @param string $sEntityName
     * @param array $aWhere
     * @param string $sOrderBy
     * @param int $iLimit
     * @param int $iOffset
     * @return array
     */
    public function buildSelectQueryOr($sEntityName, array $aWhere, $sOrderBy = '', $iLimit = 0, $iOffset = 0)
    {
        return $this->buildSelect($sEntityName, $aWhere, self::WHERE_SEPARATOR_OR, $sOrderBy, $iLimit, $iOffset);
    }

    /**
     * @param string $sEntityName
     * @param array $aWhere
     * @param string $sSeparator
     * @param string $sOrderBy
     * @param integer $iLimit
     * @param integer $iOffset
     * @return array
     */
    private function buildSelect($sEntityName, array $aWhere, $sSeparator = self::WHERE_SEPARATOR_AND, $sOrderBy = '', $iLimit = 0, $iOffset = 0)
    {
        $sQueryWhere = $this->buildWherePlaceholders(array_keys($aWhere), $sSeparator);
        if ($iLimit > 0) {
            $sQueryLimit = ' LIMIT ' . $iOffset . ',' . $iLimit;
        } elseif ($iOffset > 0) {
            // Huge limit from official MYSQL team :D http://stackoverflow.com/questions/255517/mysql-offset-infinite-rows
            $sQueryLimit = ' LIMIT ' . $iOffset . ',18446744073709551615';
        } else {
            $sQueryLimit = '';
        }
        $sQuery = "SELECT * FROM `$sEntityName`"
                . (!empty($sQueryWhere) ? ' WHERE ' . $sQueryWhere : '')
                . (!empty($sOrderBy) ? ' ORDER BY ' . $sOrderBy : '')
                . $sQueryLimit;
        return [$sQuery, $aWhere];
    }

    /**
     * @param string $sEntityName
     * @param array $aWhere
     * @return array
     */
    public function buildDeleteQuery($sEntityName, array $aWhere)
    {
        $sQueryWhere = $this->buildWherePlaceholders(array_keys($aWhere), ' AND ');
        $sQuery      = "DELETE FROM `$sEntityName` WHERE $sQueryWhere";
        return [$sQuery, $aWhere];
    }

    /**
     * @param string $sEntityName
     * @param array $aParameters
     * @return array
     */
    public function buildInsertQuery($sEntityName, array $aParameters)
    {
        $sQueryColumnNames = '`' . implode('`, `', array_keys($aParameters)) . '`';
        $sPlaceholders     = ':' . implode(', :', array_keys($aParameters));
        $sQuery            = "INSERT INTO `$sEntityName` ($sQueryColumnNames) VALUES ($sPlaceholders)";
        return [$sQuery, $aParameters];
    }

    /**
     * @param string $sEntityName
     * @param array $aWhere
     * @param array $aToSet
     * @return string
     */
    public function buildUpdateQuery($sEntityName, array $aWhere, array $aToSet)
    {
        $sQuerySet   = $this->buildWherePlaceholders(array_keys($aToSet), ', ');
        $sQueryWhere = $this->buildWherePlaceholders(array_keys($aWhere), ' AND ');
        $sQuery      = "UPDATE `$sEntityName` SET $sQuerySet WHERE $sQueryWhere";
        return [$sQuery, $aToSet + $aWhere];
    }

    /**
     * @param string $sKey
     * @param string $sValue
     * @return $this
     */
    public function set($sKey, $sValue)
    {
        $this->aMap[$sKey] = $sValue;
        return $this;
    }

    /**
     * @param string $sKey
     * @return string
     * @throws \BadMethodCallException
     */
    public function get($sKey)
    {
        if (isset($this->aMap[$sKey])) {
            return $this->aMap[$sKey];
        } else {
            throw new \BadMethodCallException("Key '$sKey' not previously set!");
        }
    }

    /**
     * @param array $aWhere
     * @param string $sOrderBy
     * @param int $iLimit
     * @param int $iOffset
     * @return array
     */
    public function fetchAll(array $aWhere, $sOrderBy = '', $iLimit = 0, $iOffset = 0)
    {
        return $this->fetch($aWhere, self::WHERE_SEPARATOR_AND, $sOrderBy, $iLimit, $iOffset);
    }

    /**
     * @param array $aWhere
     * @param string $sOrderBy
     * @param int $iLimit
     * @param int $iOffset
     * @return array
     */
    public function fetchAllOr(array $aWhere, $sOrderBy = '', $iLimit = 0, $iOffset = 0)
    {
        return $this->fetch($aWhere, self::WHERE_SEPARATOR_OR, $sOrderBy, $iLimit, $iOffset);
    }

    /**
     * @param array $aWhere
     * @param string $sSeparator
     * @param string $sOrderBy
     * @param int $iLimit
     * @param int $iOffset
     * @return array
     */
    private function fetch(array $aWhere, $sSeparator = self::WHERE_SEPARATOR_AND, $sOrderBy = '', $iLimit = 0, $iOffset = 0)
    {
        $this->formatToDb($aWhere);
        if ($sSeparator === self::WHERE_SEPARATOR_OR) {
            list($sQuery, $aParameters) = $this->buildSelectQuery($this->get('entity'), $aWhere, $sOrderBy, $iLimit, $iOffset);
        } else {
            list($sQuery, $aParameters) = $this->buildSelectQueryOr($this->get('entity'), $aWhere, $sOrderBy, $iLimit, $iOffset);
        }
        return $this->fetchAllQuery($sQuery, $aParameters);
    }

    /**
     * @param array $aWhere
     * @param string $sOrderBy
     * @return array
     */
    public function fetchOne(array $aWhere, $sOrderBy = '')
    {
        $aAllRecords = $this->fetchAll($aWhere, $sOrderBy, 1);
        return isset($aAllRecords[0]) ? $aAllRecords[0] : [];
    }

    /**
     * @param array $aWhere
     * @param string $sOrderBy
     * @return array
     */
    public function fetchOneOr(array $aWhere, $sOrderBy = '')
    {
        $aAllRecords = $this->fetchAllOr($aWhere, $sOrderBy, 1);
        return isset($aAllRecords[0]) ? $aAllRecords[0] : [];
    }

    /**
     * @param string $sQuery
     * @param array $aParameters
     * @return array
     * @throws \RuntimeException
     */
    public function fetchAllQuery($sQuery, array $aParameters = [])
    {
        try {
            $aAllRecords = $this->getPdoSlave()->fetchAll($sQuery, $aParameters) ?: [];
        } catch (\PDOException $oException) {
            $sErrMsg = $oException->getMessage()
                    . "\n  Entity: " . $this->get('entity')
                    . "\n  Query: $sQuery"
                    . "\n  Parameters: " . print_r($aParameters, true);
            throw new \RuntimeException($sErrMsg);
        }

        // Format:
        foreach ($aAllRecords as $iIndex => $aRecord) {
            $this->formatFromDb($aAllRecords[$iIndex]);
        }

        $this->unsetForcedUsePdo();

        return $aAllRecords;
    }

    /**
     * @param string $sQuery
     * @param array $aParameters
     * @return array
     */
    public function fetchOneQuery($sQuery, array $aParameters = [])
    {
        $aAllRecords = $this->fetchAllQuery($sQuery, $aParameters);
        return isset($aAllRecords[0]) ? $aAllRecords[0] : [];
    }

    /**
     * @param array $aWhere
     * @return \PDOStatement
     * @throws \RuntimeException
     */
    public function delete(array $aWhere)
    {
        $this->formatToDb($aWhere);
        list($sQuery, $aParameters) = $this->buildDeleteQuery($this->get('entity'), $aWhere);
        try {
            $oPdoStmt = $this->oPdoMaster->perform($sQuery, $aParameters) ?: [];
        } catch (\PDOException $oException) {
            $sErrMsg = $oException->getMessage()
                    . "\n  Entity: " . $this->get('entity')
                    . "\n  Query: $sQuery"
                    . "\n  Parameters: " . print_r($aParameters, true);
            throw new \RuntimeException($sErrMsg);
        }
        return $oPdoStmt;
    }

    /**
     * @param array $aParameters
     * @return \PDOStatement
     * @throws \RuntimeException
     */
    public function insert(array $aParameters)
    {
        $this->formatToDb($aParameters);
        list($sQuery, $aParameters) = $this->buildInsertQuery($this->get('entity'), $aParameters);

        try {
            $this->oPdoMaster->perform($sQuery, $aParameters);
        } catch (\PDOException $oException) {
            $sErrMsg = $oException->getMessage() . "\n  Entity: "
                    . $this->get('entity')
                    . "\n  Query: $sQuery"
                    . "\n  Parameters: " . print_r($aParameters, true);
            throw new \RuntimeException($sErrMsg);
        }

        return $this->oPdoMaster->lastInsertId();
    }

    /**
     * @param array $aWhere
     * @param array $aToSet
     * @return \PDOStatement
     * @throws \RuntimeException
     */
    public function update(array $aWhere, array $aToSet)
    {
        $this->formatToDb($aWhere);
        $this->formatToDb($aToSet);
        list($sQuery, $aParameters) = $this->buildUpdateQuery($this->get('entity'), $aWhere, $aToSet);

        try {
            $oPdoStmt = $this->oPdoMaster->perform($sQuery, $aParameters);
        } catch (\PDOException $oException) {
            $sErrMsg = $oException->getMessage()
                    . "\n  Entity: " . $this->get('entity')
                    . "\n  Query: $sQuery"
                    . "\n  Parameters: " . print_r($aParameters, true);
            throw new \RuntimeException($sErrMsg);
        }

        return $oPdoStmt;
    }

    /**
     * @return ExtendedPdoInterface
     */
    public function getPdo()
    {
        $this->oPdoSlave->connect();
        $this->oPdoMaster->connect();
        return $this->getPdoMaster();
    }

    public function disconnectPdo()
    {
        /** @var $oPdo ExtendedPdo */
        $this->oPdoSlave->disconnect();
        $this->oPdoMaster->disconnect();
    }

}
