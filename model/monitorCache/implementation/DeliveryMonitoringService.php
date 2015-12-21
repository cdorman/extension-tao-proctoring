<?php
/**
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; under version 2
 * of the License (non-upgradable).
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 *
 * Copyright (c) 2015 (original work) Open Assessment Technologies SA;
 *
 *
 */

namespace oat\taoProctoring\model\monitorCache\implementation;

use oat\taoProctoring\model\monitorCache\DeliveryMonitoringServiceInterface;
use oat\taoProctoring\model\monitorCache\DeliveryMonitoringDataInterface;
use oat\oatbox\service\ConfigurableService;

/**
 * Class DeliveryMonitoringService
 *
 * Usage example:
 *
 * Save
 * ----
 *
 * ```php
 * $data = new DeliveryMonitoringData($deliveryExecutionId);
 * $data->setData([
 *  'test_taker' => 'http://sample/first.rdf#i1450190828500474',
 *  'status' => 'ACTIVE',
 *  'current_assessment_item' => 'http://sample/first.rdf#i145018936535755'
 * ]);
 * $deliveryMonitoringService->save($data);
 * ```
 *
 * Find
 * ----
 *
 * ```php
 * $data = $deliveryMonitoringService->find([
 *   'state' => 'ACTIVE'
 * ],[
 *   'limit' => 10,
 *   'order' = >'id ASC',
 * ]);
 * ```
 *
 * @package oat\taoProctoring\model
 * @author Aleh Hutnikau <hutnikau@1pt.com>
 */
class DeliveryMonitoringService extends ConfigurableService implements DeliveryMonitoringServiceInterface
{

    const TABLE_NAME = 'delivery_monitoring';
    const COLUMN_ID = 'id';
    const COLUMN_DELIVERY_EXECUTION_ID = 'delivery_execution_id';
    const COLUMN_STATUS = 'status';
    const COLUMN_CURRENT_ASSESSMENT_ITEM = 'current_assessment_item';
    const COLUMN_TEST_TAKER = 'test_taker';
    const COLUMN_AUTHORIZED_BY = 'authorized_by';
    const COLUMN_START_TIME = 'start_time';
    const COLUMN_END_TIME = 'end_time';

    const KV_TABLE_NAME = 'kv_delivery_monitoring';
    const KV_COLUMN_ID = 'id';
    const KV_COLUMN_PARENT_ID = 'parent_id';
    const KV_COLUMN_KEY = 'monitoring_key';
    const KV_COLUMN_VALUE = 'monitoring_value';
    const KV_FK_PARENT = 'FK_DeliveryMonitoring_kvDeliveryMonitoring';

    private $persistence;

    /**
     * @var array
     */
    private $primaryTableColumns;

    /**
     * Find delivery monitoring data.
     *
     * Examples:
     * Find by delivery execution id:
     * ------------------------------
     * ```php
     * $deliveryMonitoringService->find([
     *     ['delivery_execution_id' => 'http://sample/first.rdf#i1450191587554175']
     * ]);
     * ```
     *
     * Find by two fields with `AND` operator
     * --------------------------------------
     * ```php
     * $deliveryMonitoringService->find([
     *     ['status' => 'active'],
     *     ['start_time' => '>1450428401'],
     * ]);
     * ```
     *
     * Find by two fields with `OR` operator
     * -------------------------------------
     * ```php
     * $deliveryMonitoringService->find([
     *     ['status' => 'active'],
     *     'OR',
     *     ['start_time' => '>1450428401'],
     * ]);
     * ```
     *
     *
     * Combined condition
     * ------------------
     * ```php
     * $deliveryMonitoringService->find([
     *    ['status' => 'finished'],
     *    'AND',
     *    [['error_code' => '0'], 'OR', ['error_code' => '1']],
     * ]);
     * ```
     * @param array $criteria
     * @param array $options - criteria to find data.
     * The comparison operator is determined based on the first few
     * characters in the given value. It recognizes the following operators
     * if they appear as the leading characters in the given value:
     * <ul>
     *   <li><code>&lt;</code>: the column must be less than the given value.</li>
     *   <li><code>&gt;</code>: the column must be greater than the given value.</li>
     *   <li><code>&lt;=</code>: the column must be less than or equal to the given value.</li>
     *   <li><code>&gt;=</code>: the column must be greater than or equal to the given value.</li>
     *   <li><code>&lt;&gt;</code>: the column must not be the same as the given value.</li>
     *   <li><code>=</code>: the column must be equal to the given value.</li>
     *   <li>none of the above: the column must be equal to the given value.</li>
     * </ul>
     * @param boolean $together - whether the secondary data should be fetched together with primary.
     * @return DeliveryMonitoringData[]
     */
    public function find(array $criteria = [], array $options = [], $together = false)
    {
        $result = [];
        $defaultOptions = [
            'order' => self::COLUMN_ID." ASC",
            'offset' => 0,
        ];
        $options = array_merge($defaultOptions, $options);

        $whereClause = 'WHERE ';
        $parameters = [];

        $selectClause = "SELECT DISTINCT t.* ";
        $fromClause = "FROM " . self::TABLE_NAME . " t ";
        $whereClause .= $this->prepareCondition($criteria, $parameters, $selectClause);

        $sql = $selectClause . $fromClause . PHP_EOL .
            "LEFT JOIN " . self::KV_TABLE_NAME . " kv_t ON kv_t. " . self::KV_COLUMN_PARENT_ID . " = t." . self::COLUMN_ID . PHP_EOL .
            $whereClause . PHP_EOL .
            "ORDER BY " . $options['order'];

        if (isset($options['limit']))  {
            $sql = $this->getPersistence()->getPlatForm()->limitStatement($sql, $options['limit'], $options['offset']);
        }

        $stmt = $this->getPersistence()->query($sql, $parameters);

        $data = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        if ($together) {
            foreach ($data as &$row) {
                $row = array_merge($row, $this->getKvData($row['id']));
            }
        }

        foreach($data as $row) {
            $monitoringData = new DeliveryMonitoringData($row[self::COLUMN_DELIVERY_EXECUTION_ID]);
            $monitoringData->set($row);
            $result[] = $monitoringData;
        }
        return $result;
    }

    /**
     * @param DeliveryMonitoringDataInterface $deliveryMonitoring
     * @return boolean whether data is saved
     */
    public function save(DeliveryMonitoringDataInterface $deliveryMonitoring)
    {
        $result = false;
        if ($deliveryMonitoring->validate()) {
            $isNewRecord = $this->isNewRecord($deliveryMonitoring);

            if ($isNewRecord) {
                $result = $this->create($deliveryMonitoring);
            } else {
                $result = $this->update($deliveryMonitoring);
            }
        }
        return $result;
    }

    /**
     * Create new record
     * @param DeliveryMonitoringDataInterface $deliveryMonitoring
     * @return boolean whether data is saved
     */
    private function create(DeliveryMonitoringDataInterface $deliveryMonitoring)
    {
        $data = $deliveryMonitoring->get();

        $primaryTableData = $this->extractPrimaryData($data);

        $result = $this->getPersistence()->insert(self::TABLE_NAME, $primaryTableData) === 1;

        $id = $this->persistence->lastInsertId(self::TABLE_NAME);

        $data[self::COLUMN_ID] = $id;
        $deliveryMonitoring->set($data);

        $this->saveKvData($deliveryMonitoring);

        return $result;
    }

    /**
     * Update existing record
     * @param DeliveryMonitoringDataInterface $deliveryMonitoring
     * @return boolean whether data is saved
     */
    private function update(DeliveryMonitoringDataInterface $deliveryMonitoring)
    {
        $setClause = '';
        $params = [':delivery_execution_id' => $deliveryMonitoring->get()[self::COLUMN_DELIVERY_EXECUTION_ID]];

        $data = $deliveryMonitoring->get();
        $primaryTableData = $this->extractPrimaryData($data);

        unset($primaryTableData['delivery_execution_id']);
        foreach ($primaryTableData as $dataKey => $dataValue) {
            $setClause .= ($setClause === '') ? "$dataKey = :$dataKey" : ", $dataKey = :$dataKey";
            $params[":$dataKey"] = $dataValue;
        }

        $sql = "UPDATE " . self::TABLE_NAME . " SET $setClause
        WHERE " . self::COLUMN_DELIVERY_EXECUTION_ID . '=:delivery_execution_id';

        $this->getPersistence()->exec($sql, $params);

        $this->saveKvData($deliveryMonitoring);

        return true;
    }

    /**
     * Delete all related records from secondary table
     * @param DeliveryMonitoringDataInterface $deliveryMonitoring
     */
    private function saveKvData(DeliveryMonitoringDataInterface $deliveryMonitoring)
    {
        $data = $deliveryMonitoring->get();
        $isNewRecord = $this->isNewRecord($deliveryMonitoring);

        if (!$isNewRecord) {
            $this->deleteKvData($deliveryMonitoring);
            $id = $data[self::COLUMN_ID];
            $kvTableData = $this->extractKvData($data);
            foreach($kvTableData as $kvDataKey => $kvDataValue) {
                $this->getPersistence()->insert(
                    self::KV_TABLE_NAME,
                    array(
                        self::KV_COLUMN_PARENT_ID => $id,
                        self::KV_COLUMN_KEY => $kvDataKey,
                        self::KV_COLUMN_VALUE => $kvDataValue,
                    )
                );
            }
        }
    }

    /**
     * @param DeliveryMonitoringDataInterface $deliveryMonitoring
     * @return boolean
     */
    public function delete(DeliveryMonitoringDataInterface $deliveryMonitoring)
    {
        $data = $deliveryMonitoring->get();

        $sql = 'DELETE FROM ' . self::TABLE_NAME . '
                WHERE ' . self::COLUMN_DELIVERY_EXECUTION_ID . '=?';

        return $this->getPersistence()->exec($sql, [$data[self::COLUMN_DELIVERY_EXECUTION_ID]]) === 1;
    }


    /**
     * Delete all related records from secondary table
     * @param DeliveryMonitoringDataInterface $deliveryMonitoring
     * @return boolean
     */
    private function deleteKvData(DeliveryMonitoringDataInterface $deliveryMonitoring)
    {
        $result = false;
        $data = $deliveryMonitoring->get();
        $isNewRecord = $this->isNewRecord($deliveryMonitoring);

        if (!$isNewRecord) {
            $sql = 'DELETE FROM ' . self::KV_TABLE_NAME . '
                    WHERE ' . self::KV_COLUMN_PARENT_ID . '=?';
            $this->getPersistence()->exec($sql, [$data['id']]);
            $result = true;
        }

        return $result;
    }

    /**
     * @return \common_persistence_SqlPersistence
     */
    private function getPersistence()
    {
        if ($this->persistence === null) {
            $this->persistence = \common_persistence_Manager::getPersistence('default');
        }
        return $this->persistence;
    }

    /**
     * Get list of table column names
     * @return array
     */
    private function getPrimaryColumns()
    {
        if ($this->primaryTableColumns === null) {
            $schemaManager = $this->getPersistence()->getDriver()->getSchemaManager();
            $schema = $schemaManager->createSchema();
            $this->primaryTableColumns = array_keys($schema->getTable(self::TABLE_NAME)->getColumns());
        }
        return $this->primaryTableColumns;
    }

    /**
     * @param array $data
     * @return array
     */
    private function extractPrimaryData(array $data)
    {
        $result = [];
        $primaryTableCols = $this->getPrimaryColumns();
        foreach ($primaryTableCols as $primaryTableCol) {
            if (isset($data[$primaryTableCol])) {
                $result[$primaryTableCol] = $data[$primaryTableCol];
            }
        }
        return $result;
    }

    /**
     * @param array $data
     * @return array
     */
    private function extractKvData(array $data)
    {
        $result = [];
        $primaryTableCols = $this->getPrimaryColumns();
        foreach ($data as $key => $val) {
            if (!in_array($key, $primaryTableCols)) {
                $result[$key] = $val;
            }
        }
        return $result;
    }

    /**
     * Get secondary data by parent data id
     * @param integer $id
     * @return array
     */
    private function getKvData($id)
    {
        $result = [];
        $sql = 'SELECT * FROM ' . self::KV_TABLE_NAME . '
                WHERE ' . self::KV_COLUMN_PARENT_ID . '=?';
        $secondaryData = $this->getPersistence()->query($sql, [$id])->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($secondaryData as $data) {
            $result[$data[self::KV_COLUMN_KEY]] = $data[self::KV_COLUMN_VALUE];
        }

        return $result;
    }

    /**
     * @param $condition
     * @param $parameters
     * @param $selectClause
     * @return string
     */
    private function prepareCondition($condition, &$parameters, &$selectClause)
    {
        $whereClause = '';

        if (is_array($condition) && count($condition) === 1 && is_array(current($condition))) {
            $condition = current($condition);
        }

        if (is_string($condition) && in_array(mb_strtoupper($condition), ['OR', 'AND'])) {
            $whereClause .= " $condition ";
        } else if (is_array($condition) && count($condition) > 1) {
            $whereClause .=  '(';
            $previousCondition = null;
            foreach ($condition as $subCondition) {
                if (is_array($subCondition) && is_array($previousCondition)) {
                    $whereClause .= 'AND';
                }
                $whereClause .=  $this->prepareCondition($subCondition, $parameters, $selectClause);
                $previousCondition = $subCondition;
            }
            $whereClause .=  ')';
        } else if (is_array($condition) && count($condition) === 1) {
            $primaryColumns = $this->getPrimaryColumns();
            $key = array_keys($condition)[0];
            $value = $condition[$key];

            if ($value === null) {
                $op = 'IS NULL';
            } else if (preg_match('/^(?:\s*(<>|<=|>=|<|>|=|LIKE|NOT\sLIKE))?(.*)$/', $value, $matches)) {
                $value = $matches[2];
                $op = $matches[1] ? $matches[1] : "=";
                $op .= ' ?';
            }

            if (in_array($key, $primaryColumns)) {
                $whereClause .= " t.$key $op ";
            } else {
                $whereClause .= " (kv_t.monitoring_key = ? AND kv_t.monitoring_value $op) ";
                $parameters[] = trim($key);
            }

            if ($value !== null) {
                $parameters[] = trim($value);
            }
        }
        return $whereClause;
    }

    /**
     * Check if record for delivery execution already exists in the storage.
     * @param DeliveryMonitoringDataInterface $deliveryMonitoring
     * @return boolean
     */
    private function isNewRecord(DeliveryMonitoringDataInterface $deliveryMonitoring)
    {
        $deliveryExecutionId = $deliveryMonitoring->get()[self::COLUMN_DELIVERY_EXECUTION_ID];
        $sql = "SELECT EXISTS( " . PHP_EOL .
                "SELECT " . self::COLUMN_DELIVERY_EXECUTION_ID . PHP_EOL .
                "FROM " . self::TABLE_NAME . PHP_EOL .
                "WHERE " . self::COLUMN_DELIVERY_EXECUTION_ID . "=?)";
        $isNewRecord = $this->getPersistence()->query($sql, [$deliveryExecutionId])->fetch(\PDO::FETCH_COLUMN);

        return $isNewRecord === 0;
    }
}