<?php

namespace Walnut\Lib\DbOrm;

use Walnut\Lib\DbQuery\PreparedQuery;

/**
 * @package Walnut\Lib\DbOrm
 */
final class DataModelCachedQueryBuilder implements RelationalStorageQueryBuilder {

	public function __construct(
		private readonly RelationalStorageQueryBuilder $queryBuilder
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
		return $this->insertQueryCache[$modelName] ??= $this->queryBuilder->getInsertQuery($modelName);
	}

	public function getUpdateQuery(string $modelName): PreparedQuery {
		return $this->updateQueryCache[$modelName] ??= $this->queryBuilder->getUpdateQuery($modelName);
	}

	public function getDeleteQuery(string $modelName): PreparedQuery {
		return $this->deleteQueryCache[$modelName] ??= $this->queryBuilder->getDeleteQuery($modelName);
	}

}