<?php

use PHPUnit\Framework\TestCase;
use Walnut\Lib\DbDataModel\Attribute\CrossTable;
use Walnut\Lib\DbDataModel\Attribute\Fields;
use Walnut\Lib\DbDataModel\Attribute\GroupField;
use Walnut\Lib\DbDataModel\Attribute\KeyField;
use Walnut\Lib\DbDataModel\Attribute\ListOf;
use Walnut\Lib\DbDataModel\Attribute\ModelRoot;
use Walnut\Lib\DbDataModel\Attribute\OneOf;
use Walnut\Lib\DbDataModel\Attribute\ParentField;
use Walnut\Lib\DbDataModel\Attribute\SortField;
use Walnut\Lib\DbDataModel\Attribute\Table;
use Walnut\Lib\DbDataModel\DataModelBuilder;
use Walnut\Lib\DbOrm\DataModelFactory;
use Walnut\Lib\DbQuery\Pdo\PdoConnector;
use Walnut\Lib\DbQuery\Pdo\PdoQueryExecutor;
use Walnut\Lib\DbQueryBuilder\Expression\FieldExpression;
use Walnut\Lib\DbQueryBuilder\Expression\FieldExpressionOperation;
use Walnut\Lib\DbQueryBuilder\QueryPart\QueryFilter;
use Walnut\Lib\DbQueryBuilder\QueryValue\SqlValue;
use Walnut\Lib\DbQueryBuilder\Quoter\SqliteQuoter;


#[ModelRoot('users')]
final class MockUserQueryModel {

	public function __construct(

		#[Table("org_users")]
		#[KeyField('id'), Fields('name', 'org_id')]
		#[OneOf(fieldName: 'org', targetName: 'orgs', sourceField: 'org_id')]
		#[ListOf(fieldName: 'credentials', targetName: 'userCredentials')]
		#[ListOf(fieldName: 'roles', targetName: 'roles')]
		#[ListOf(fieldName: 'tags', targetName: 'tags')]
		public array $users,

			#[CrossTable('org_user_roles', parentField: 'user_id', sourceField: 'role_id', targetField: 'id')]
			#[Table('org_roles'), KeyField('id'), Fields('name', 'code'), GroupField('code')]
			public array $roles,

			#[Table('orgs')]
			#[KeyField('id'), Fields('name', 'code'), ParentField('id')]
			public array $orgs,

			#[Table('org_user_credentials')]
			#[KeyField('id'), ParentField('user_id'), Fields('username', 'password')]
			public array $userCredentials,

			#[CrossTable('org_user_tags', parentField: 'user_id', sourceField: 'tag_id', targetField: 'id')]
			#[Table("org_tag_user_group_values"), KeyField('id'), Fields('value', 'group_id'), GroupField('id'), SortField('sequence')]
			#[OneOf(fieldName: 'group', targetName: 'tagGroups', sourceField: 'group_id')]
			public array $tags,

				#[Table("org_tag_user_groups"), KeyField('id'), Fields('name'), ParentField('id')]
				public array $tagGroups
	) {}
}

#[ModelRoot('users')]
class MockUserDomainModel {
	public function __construct(

		#[Table("org_users")]
		#[KeyField('id'), Fields('name', 'org_id')]
		#[ListOf(fieldName: 'credentials', targetName: 'userCredentials')]
		#[ListOf(fieldName: 'roles', targetName: 'roles')]
		#[ListOf(fieldName: 'tags', targetName: 'tags')]
		public array $users,

			#[Table('org_user_roles'), KeyField('id'), ParentField('user_id'), Fields('role_id')]
			public array $roles,

			#[Table('org_user_credentials')]
			#[KeyField('id'), ParentField('user_id'), Fields('username', 'password')]
			public array $userCredentials,

			#[Table("org_user_tags"), KeyField('id'), ParentField('user_id'), Fields('tag_id')]
			public array $tags
	) {}
}

final class IntegrationTest extends TestCase {

	private function getFactory(): DataModelFactory {
		$queryExecutor = new PdoQueryExecutor(
			$connector = new PdoConnector('sqlite::memory:', '', '')
		);
		$c = $connector->getConnection();
		foreach(explode(';', file_get_contents(__DIR__ . '/integration.sql')) as $sql) {
			$c->exec($sql);
		}
		return new DataModelFactory(new SqliteQuoter, $queryExecutor);
	}

	public function testFetcher(): void {
		$model = (new DataModelBuilder)->build(MockUserQueryModel::class);
		$dataModelFetcher = $this->getFactory()->getFetcher($model);
		$data = $dataModelFetcher->fetchData(new QueryFilter(
			new FieldExpression('id', FieldExpressionOperation::lessThan, new SqlValue(2))
		));
		$row = $data[0];
		$this->assertEquals('1', $row['id']);
		$this->assertEquals('User 1', $row['name']);
		$this->assertEquals(['id' => 1, 'name' => 'Org 1', 'code' => 'org1'], $row['org']);
		$this->assertEquals(['id' => 1, 'username' => 'user', 'password' => 'pass hash'], $row['credentials'][0]);
		$this->assertEquals(['id' => 1, 'value' => 'Val11', 'group' => ['id' => 1, 'name' => 'Grp1']], $row['tags'][1]);
		$this->assertEquals(['id' => 1, 'name' => 'Admin', 'code' => 'ADM'], $row['roles']['ADM']);
	}

	public function testSynchronizer(): void {
		$model = (new DataModelBuilder)->build(MockUserDomainModel::class);
		$factory = $this->getFactory();
		$dataModelFetcher = $factory->getFetcher($model);
		$data = $dataModelFetcher->fetchData(new QueryFilter(
			FieldExpression::equals('id', new SqlValue(1))
		))[0];
		$oldData = $data;
		$data['name'] .= '*';
		unset($data['roles'][1]);
		$data['tags'][] = ['id' => '3', 'tag_id' => 2];
		$dataModelSynchronizer = $factory->getSynchronizer($model);
		$dataModelSynchronizer->synchronizeData([$oldData], [$data]);

		$data = $dataModelFetcher->fetchData(new QueryFilter(
			FieldExpression::equals('id', new SqlValue(1))
		))[0];
		$this->assertEquals('User 1*', $data['name']);

	}

}