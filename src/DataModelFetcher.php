<?php

namespace Walnut\Lib\DbOrm;

use Walnut\Lib\DbQuery\PlaceholderQuery;
use Walnut\Lib\DbQuery\QueryExecutor;
use Walnut\Lib\DbQuery\ResultBag\ResultBag;
use Walnut\Lib\DbQueryBuilder\Expression\FieldExpression;
use Walnut\Lib\DbQueryBuilder\Expression\RawExpression;
use Walnut\Lib\DbQueryBuilder\Query\SelectQuery;
use Walnut\Lib\DbQueryBuilder\QueryPart\OrderByField;
use Walnut\Lib\DbQueryBuilder\QueryPart\QueryFilter;
use Walnut\Lib\DbQueryBuilder\QueryPart\TableField;
use Walnut\Lib\DbQueryBuilder\QueryPart\TableJoin;
use Walnut\Lib\DbQueryBuilder\QueryValue\PlaceholderValue;
use Walnut\Lib\DbQueryBuilder\Quoter\SqlQuoter;
use Walnut\Lib\DbDataModel\DataModel;
use Walnut\Lib\DbDataModel\DataPart;

/**
 * @psalm-suppress MixedAssignment
 * @psalm-suppress MixedArgument
 * @psalm-suppress MixedArrayAccess
 * @psalm-suppress MixedArrayAssignment
 * @package Walnut\Lib\DbOrm
 */
final class DataModelFetcher implements RelationalStorageFetcher {

	public function __construct(
		private readonly SqlQuoter $quoter,
		private readonly QueryExecutor $queryExecutor,
		private readonly DataModel $model
	) { }

	public function fetchData(QueryFilter $filter): array {
		$modelRoot = $this->model->modelRoot->modelRoot;
		return $this->fetchListData($modelRoot, $filter)->all();
	}

	private function fetchListData(string $modelName, QueryFilter $filter): ResultBag {
		return $this->completeData(
			$modelName,
			($this->prepareQuery($this->model->part($modelName)))->execute(
				$this->queryExecutor, null, ['FILTER' =>
					$filter->build($this->quoter)]
			)->collectAsList()
		);
	}

	/**
	 * @param array $filterData
	 * @return string[]
	 */
	private function getValuesPlaceholders(array $filterData): array {
		return ['VALUES' => "(" .
			implode(", ", array_map(fn(string $str): string
				=> $this->quoter->quoteValue($str), $filterData ?: [0])) .
		")"];
	}

	private function fetchTreeData(string $modelName, array $filterData): ResultBag {
		return $this->completeData(
			$modelName,
			($this->prepareQuery($this->model->part($modelName)))->execute(
				$this->queryExecutor, null,
				$this->getValuesPlaceholders($filterData)

			)->collectAsTreeData()
		);
	}

	private function fetchHashData(string $modelName, array $filterData): ResultBag {
		return $this->completeData(
			$modelName,
			($this->prepareQuery($this->model->part($modelName)))->execute(
				$this->queryExecutor, null,
				$this->getValuesPlaceholders($filterData)
			)->collectAsHash()
		);
	}

	private function completeData(string $modelName, ResultBag $sourceResult): ResultBag {
		$part = $this->model->part($modelName);
		$relatedValues = $this->fetchRelatedData($part, $sourceResult);
		return $sourceResult->modify((static function() use ($part, $relatedValues) {
			$q = yield;
			while ($q) {
				foreach($part->oneOfFields as $oneOfField) {
					$v = $q[$oneOfField->sourceField ?? $part->keyField->name] ?? null;
					$q[$oneOfField->fieldName] = $v ?
						$relatedValues[$oneOfField->targetName]->withKey($v) : null;
					if ($oneOfField->sourceField && $part->keyField->name !== $oneOfField->sourceField) {
						unset($q[$oneOfField->sourceField]);
					}
				}
				foreach($part->listOfFields as $listOfField) {
					$v = $q[$listOfField->sourceField ?? $part->keyField->name] ?? null;
					$q[$listOfField->fieldName] = $v ?
						$relatedValues[$listOfField->targetName]->withKey($v) : null;
				}
				$key = $part->groupField ? $q[$part->groupField->name] : null;
				$q = yield $key => $q;
			}
		})());

	}

	/**
	 * @param DataPart $part
	 * @param ResultBag $sourceResult
	 * @return array<string, ResultBag>
	 */
	private function fetchRelatedData(DataPart $part, ResultBag $sourceResult): array {
		$relatedValues = [];
		foreach($part->oneOfFields as $oneOfField) {
			$sourceField = $oneOfField->sourceField ?? $part->keyField->name;
			$relatedValues[$oneOfField->targetName] = $this->fetchHashData(
				$oneOfField->targetName,
				$sourceResult->collect($sourceField)
			);
		}
		foreach($part->listOfFields as $listOfField) {
			$sourceField = $listOfField->sourceField ?? $part->keyField->name;
			$relatedValues[$listOfField->targetName] = $this->fetchTreeData(
				$listOfField->targetName,
				$sourceResult->collect($sourceField)
			);
		}
		return $relatedValues;
	}

	/**
	 * @param DataPart $part
	 * @return PlaceholderQuery
	 */
	private function prepareQuery(DataPart $part): PlaceholderQuery {
		$table = $part->table->tableName;// new Table($part->table->tableName, '_');
		$fieldList = [];
		$parentField = null;
		$joins = [];

		if ($part->crossTable) {
			$crossTable = $part->crossTable->tableName;
			$joins[] = new TableJoin(
				"__", $crossTable, new QueryFilter(
					FieldExpression::equals(
						new TableField("__", $part->crossTable->sourceField),
						new TableField("_", $part->crossTable->targetField)
					)
				)
			);
			/*$table->getJoinList()->addJoin(
				new Join($crossTable, false, new Pair(
					new TermField($crossTable, $part->crossTable->sourceField),
					new TermField($table, $part->crossTable->targetField)

				))
			);*/
			$fieldList['__PARENT_FIELD__'] = $parentField = new TableField('__', $part->crossTable->parentField);
		} elseif ($part->parentField) {
			$fieldList['__PARENT_FIELD__'] = $parentField = $part->parentField->name;
		}
		$fieldList[$part->keyField->name] = $part->keyField->name;
		foreach($part->fields->fieldNames as $fieldName) {
			$fieldList[$fieldName] = $fieldName;
		}
		foreach($part->oneOfFields as $oneOfField) {
			if ($sf = $oneOfField->sourceField) {
				$fieldList[$sf] = $sf;
			}
		}
		if ($parentField) {
			$filterPlaceholder = 'VALUES';
			$filter = new RawExpression((is_string($parentField) ?
				$this->quoter->quoteIdentifier($parentField) :
				$parentField->build($this->quoter)).
				' IN ' . (new PlaceholderValue($filterPlaceholder))->build($this->quoter)
			);
		} else {
			$filterPlaceholder = 'FILTER';
			$filter = new RawExpression(
				(new PlaceholderValue($filterPlaceholder))->build($this->quoter)
			);
		}
		$orderBy = [];
		if ($part->sortField) {
			$orderBy = [OrderByField::ascending($part->sortField->name)];
		}

		$query = new SelectQuery(
			$table,
			$fieldList,
			$joins,
			new QueryFilter($filter),
			$orderBy
		);

		//echo $query->build($this->quoter), PHP_EOL;

		return new PlaceholderQuery(
			$query->build($this->quoter),
			[$filterPlaceholder]
		);
	}

}