<?php

declare(strict_types=1);

namespace Jsor\Doctrine\PostGIS\Schema;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Identifier;
use Doctrine\DBAL\Schema\Table;
use Jsor\Doctrine\PostGIS\Types\PostGISType;
use function is_string;

class SpatialColumnSqlGenerator
{
    private AbstractPlatform $platform;

    public function __construct(AbstractPlatform $platform)
    {
        $this->platform = $platform;
    }

    public function getSql(Column $column, Table|Identifier|string $table): array
    {
        if (is_string($table)) {
            $table = new Identifier($table);
        }

        $sql = [];

        /** @var PostGISType $type */
        $type = $column->getType();

        $normalized = $type->getNormalizedPostGISColumnOptions(
            $column->getCustomSchemaOptions()
        );

        $srid = $normalized['srid'];

        // PostGIS 1.5 uses -1 for undefined SRID's
        if ($srid <= 0) {
            $srid = -1;
        }

        $type = strtoupper($normalized['geometry_type']);

        if (str_ends_with($type, 'ZM')) {
            $dimension = 4;
            $type = substr($type, 0, -2);
        } elseif (str_ends_with($type, 'M')) {
            $dimension = 3;
        } elseif (str_ends_with($type, 'Z')) {
            $dimension = 3;
            $type = substr($type, 0, -1);
        } else {
            $dimension = 2;
        }

        // Geometry columns are created by the AddGeometryColumn stored procedure
        $sql[] = sprintf(
            "SELECT AddGeometryColumn('%s', '%s', %d, '%s', %d)",
            $table->getName(),
            $column->getName(),
            $srid,
            $type,
            $dimension
        );

        if ($column->getNotnull()) {
            // Add a NOT NULL constraint to the field
            $sql[] = sprintf(
                'ALTER TABLE %s ALTER %s SET NOT NULL',
                $table->getQuotedName($this->platform),
                $column->getQuotedName($this->platform)
            );
        }

        return $sql;
    }
}
