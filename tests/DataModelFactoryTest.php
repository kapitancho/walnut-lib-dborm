<?php

use PHPUnit\Framework\TestCase;
use Walnut\Lib\DbDataModel\Attribute\Fields;
use Walnut\Lib\DbDataModel\Attribute\KeyField;
use Walnut\Lib\DbDataModel\Attribute\ModelRoot;
use Walnut\Lib\DbDataModel\Attribute\Table;
use Walnut\Lib\DbDataModel\DataModel;
use Walnut\Lib\DbDataModel\DataPart;
use Walnut\Lib\DbOrm\DataModelFactory;
use Walnut\Lib\DbQuery\QueryExecutor;
use Walnut\Lib\DbQuery\QueryResult;
use Walnut\Lib\DbQuery\ResultBag\ListResultBag;
use Walnut\Lib\DbQuery\ResultBag\TreeDataResultBag;
use Walnut\Lib\DbQueryBuilder\Expression\RawExpression;
use Walnut\Lib\DbQueryBuilder\QueryPart\QueryFilter;
use Walnut\Lib\DbQueryBuilder\Quoter\SqliteQuoter;

final class DataModelFactoryTest extends TestCase {

	public function testOk(): void {
		$dataModel = new DataModel(
			new ModelRoot('users'),
			['users' => new DataPart(
				new Table("users"),
				new Fields('username', 'password'),
				new KeyField('user_id'),
				null,
				null,
				null,
				null,
				[],
				[]
			)]
		);

		$queryExecutor = new class($this) implements QueryExecutor {
			public function __construct(private /*readonly*/ TestCase $testCase) {}

			public function execute(string $query, array $boundParams = null): QueryResult {
				$this->testCase->assertStringContainsString('users', $query);
				return new class implements QueryResult {
					public function all(): array {}
					public function first(): mixed {}
					public function singleValue(): mixed {}
					public function collectAsList(): ListResultBag {
						return new ListResultBag([['user_id' => 1, 'username' => 'u', 'password' => 'p']]);
					}
					public function collectAsTreeData(): TreeDataResultBag {}
					public function collectAsHash(): ListResultBag {}
				};
			}
			public function lastIdentity(): mixed {}
			public function foundRows(): ?int {}
		};
		$factory = new DataModelFactory(
			new SqliteQuoter,
			$queryExecutor
		);
		$this->assertEquals(
			[['user_id' => 1, 'username' => 'u', 'password' => 'p']],
			$factory->getFetcher($dataModel)->fetchData(
				new QueryFilter(new RawExpression("1"))
			)
		);
		$factory->getSynchronizer($dataModel)->synchronizeData([], [
			['user_id' => 1, 'username' => 'u', 'password' => 'p']
		]);
	}

}