<?php
namespace Asticode\DataMapper\Mapper;

use Asticode\Toolbox\ExtendedString;
use Aura\Sql\ConnectionLocatorInterface;
use RuntimeException;

class MapperFactory
{
    // Attributes
    private $aMappers;
    private $oDbConnectionLocator;
    private $sNamespace;

    // Construct
    public function __construct(
        ConnectionLocatorInterface $oDbConnectionLocator,
        $sNamespace
    ) {
        // Initialize
        $this->aMappers = [];
        $this->oDbConnectionLocator = $oDbConnectionLocator;
        $this->sNamespace = $sNamespace;
    }

    public function getMapper($sMapperName, $sNamespace = '')
    {
        if (empty($this->aMappers[$sMapperName])) {
            // Get class name
            $sClassName = sprintf(
                '\\%1$s\\Mapper\\%2$s',
                $sNamespace === '' ? $this->sNamespace : $sNamespace,
                ExtendedString::toCamelCase($sMapperName, '_', true)
            );

            // Check class exists
            if (!class_exists($sClassName)) {
                throw new RuntimeException(sprintf(
                    'Invalid class name %s',
                    $sClassName
                ));
            }

            // Create mapper
            $this->aMappers[$sMapperName] = new $sClassName(
                $this->oDbConnectionLocator->getWrite(
                    ExtendedString::toSnakeCase(explode('\\', $sMapperName)[0], '_', true)
                )
            );
        }
        return $this->aMappers[$sMapperName];
    }
}