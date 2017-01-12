<?php
/*
 * This file is part of DBUnit.
 *
 * (c) Sebastian Bergmann <sebastian@phpunit.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
use PHPUnit\DbUnit\Database\IConnection;
use PHPUnit\DbUnit\DataSet\IDataSet;
use PHPUnit\DbUnit\DataSet\ITable;
use PHPUnit\DbUnit\Operation\Exception;
use PHPUnit\DbUnit\Operation\Operation;

/**
 * Executes a truncate against all tables in a dataset.
 */
class PHPUnit_Extensions_Database_Operation_Truncate implements Operation
{
    protected $useCascade = false;

    public function setCascade($cascade = true)
    {
        $this->useCascade = $cascade;
    }

    public function execute(IConnection $connection, IDataSet $dataSet)
    {
        foreach ($dataSet->getReverseIterator() as $table) {
            /* @var $table ITable */
            $query = "
                {$connection->getTruncateCommand()} {$connection->quoteSchemaObject($table->getTableMetaData()->getTableName())}
            ";

            if ($this->useCascade && $connection->allowsCascading()) {
                $query .= ' CASCADE';
            }

            try {
                $this->disableForeignKeyChecksForMysql($connection);
                $connection->getConnection()->query($query);
                $this->enableForeignKeyChecksForMysql($connection);
            } catch (\Exception $e) {
                $this->enableForeignKeyChecksForMysql($connection);

                if ($e instanceof PDOException) {
                    throw new Exception('TRUNCATE', $query, [], $table, $e->getMessage());
                }

                throw $e;
            }
        }
    }

    private function disableForeignKeyChecksForMysql(IConnection $connection)
    {
        if ($this->isMysql($connection)) {
            $connection->getConnection()->query('SET FOREIGN_KEY_CHECKS = 0');
        }
    }

    private function enableForeignKeyChecksForMysql(IConnection $connection)
    {
        if ($this->isMysql($connection)) {
            $connection->getConnection()->query('SET FOREIGN_KEY_CHECKS = 1');
        }
    }

    private function isMysql(IConnection $connection)
    {
        return $connection->getConnection()->getAttribute(PDO::ATTR_DRIVER_NAME) == 'mysql';
    }
}
