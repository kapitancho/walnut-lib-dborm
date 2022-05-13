<?php /** @noinspection DuplicatedCode */

namespace Walnut\Lib\DbOrm;

use Walnut\Lib\DbQuery\PreparedQuery;
use Walnut\Lib\DbQueryBuilder\Expression\FieldExpression;
use Walnut\Lib\DbQueryBuilder\Query\DeleteQuery;
use Walnut\Lib\DbQueryBuilder\Query\InsertQuery;
use Walnut\Lib\DbQueryBuilder\Query\UpdateQuery;
use Walnut\Lib\DbQueryBuilder\QueryPart\QueryFilter;
use Walnut\Lib\DbQueryBuilder\QueryValue\PreparedValue;
use Walnut\Lib\DbQueryBuilder\Quoter\SqlQuoter;
use Walnut\Lib\DbDataModel\DataModel;

/**
 * @package Walnut\Lib\DbOrm
 */
final class DataModelQueryBuilder {

	public function __construct(
		private readonly SqlQuoter $sqlQuoter,
		private readonly DataModel $model
	) { }

	/**
	 * @var array<string, PreparedQuery>
	 */
	private array $insertQueryCache = [];
	/**
	 * @var array<string, PreparedQuery>
	 */
	private array $updateQueryCache = [];
	/**
	 * @var array<string, PreparedQuery>
	 */
	private array $deleteQueryCache = [];

	public function getInsertQuery(string $modelName): PreparedQuery {
		return $this->insertQueryCache[$modelName] ??= $this->buildInsertQuery($modelName);
	}

	public function getUpdateQuery(string $modelName): PreparedQuery {
		return $this->updateQueryCache[$modelName] ??= $this->buildUpdateQuery($modelName);
	}

	public function getDeleteQuery(string $modelName): PreparedQuery {
		return $this->deleteQueryCache[$modelName] ??= $this->buildDeleteQuery($modelName);
	}

	private function buildInsertQuery(string $modelName): PreparedQuery {
		$model = $this->model->part($modelName);
		$fields = [];
		$pk = $model->keyField->name;
		$fields[$pk] = new PreparedValue($pk);
		if ($model->parentField) {
			$pf = $model->parentField->name;
			$fields[$pf] = new PreparedValue($pf);
		}
		if ($model->sortField) {
			$sf = $model->sortField->name;
			$fields[$sf] = new PreparedValue($sf);
		}
		foreach($model->fields->fieldNames as $fieldName) {
			$fields[$fieldName] = new PreparedValue($fieldName);
		}
		foreach($model->oneOfFields as $oneOfField) {
			if ($sf = $oneOfField->sourceField) {
				$fields[$sf] = new PreparedValue($sf);
			}
		}
		$boundParams = array_keys($fields);
		$query = new InsertQuery(
			$model->table->tableName, $fields
		);
		return new PreparedQuery($query->build($this->sqlQuoter), $boundParams);
	}

	private function buildUpdateQuery(string $modelName): PreparedQuery {
		$model = $this->model->part($modelName);
		$fields = [];
		$boundParams = [$kf = $model->keyField->name];
		if ($model->parentField) {
			$pf = $model->parentField->name;
			if ($pf !== $kf) {
				$fields[$pf] = new PreparedValue($pf);
			}
			$boundParams[] = $pf;
		}
		if ($model->sortField) {
			$sf = $model->sortField->name;
			$fields[$sf] = new PreparedValue($sf);
			$boundParams[] = $sf;
		}
		foreach($model->fields->fieldNames as $fieldName) {
			$fields[$fieldName] = new PreparedValue($fieldName);
			$boundParams[] = $fieldName;
		}
		foreach($model->oneOfFields as $oneOfField) {
			if ($sf = $oneOfField->sourceField) {
				$fields[$sf] = new PreparedValue($sf);
				$boundParams[] = $sf;
			}
		}
		if (!$fields) {
			$fields[$kf] = new PreparedValue($kf);
		}
		$query = new UpdateQuery(
			$model->table->tableName, $fields,
			new QueryFilter(new FieldExpression(
				$model->keyField->name, '=',
				new PreparedValue($kf))
			)
		);
		return new PreparedQuery($query->build($this->sqlQuoter), $boundParams);
	}

	private function buildDeleteQuery(string $modelName): PreparedQuery {
		$model = $this->model->part($modelName);
		$boundParams = [];
		$boundParams[] = $model->keyField->name;
		$query = new DeleteQuery(
			$model->table->tableName,
			new QueryFilter(new FieldExpression(
				$model->keyField->name, '=',
				new PreparedValue($model->keyField->name))
			)
		);
		return new PreparedQuery($query->build($this->sqlQuoter), $boundParams);
	}

}