<?php /** @noinspection DuplicatedCode */

namespace Walnut\Lib\DbOrm;

use Walnut\Lib\DbQuery\QueryExecutor;
use Walnut\Lib\DbQueryBuilder\Quoter\SqlQuoter;
use Walnut\Lib\DbDataModel\DataModel;

/**
 * @package Walnut\Lib\DbOrm
 */
final class DataModelFactory implements RelationalStorageFactory {

	public function __construct(
		private readonly SqlQuoter $sqlQuoter,
		private readonly QueryExecutor $queryExecutor
	) { }

	public function getFetcher(DataModel $model): RelationalStorageFetcher {
		return new DataModelFetcher(
			$this->sqlQuoter,
			$this->queryExecutor,
			$model
		);
	}

	public function getSynchronizer(DataModel $model): RelationalStorageSynchronizer {
		return new DataModelSynchronizer(
			$this->queryExecutor,
			new DataModelQueryBuilder(
				$this->sqlQuoter,
				$model
			),
			$model
		);
	}

}