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
use Walnut\Lib\DbDataModel\DataModel;
use Walnut\Lib\DbDataModel\DataPart;
use Walnut\Lib\DbOrm\DataModelQueryBuilder;
use Walnut\Lib\DbQueryBuilder\Quoter\MysqlQuoter;

final class DataModelQueryBuilderTest extends TestCase {

	private function getDataModel(): DataModel {
		return new DataModel(
			new ModelRoot('users'),
			['users' => new DataPart(
				new Table("users"),
				new Fields("first_name", "last_name"),
				new KeyField("user_id"),
				new ParentField("customer_id"),
				new CrossTable("customer_users", 'customer_id', 'user_id', 'parent'),
				new SortField("sequence"),
				new GroupField("group_id"),
				[
					new OneOf("credentials", 'user_credentials'),
					new OneOf("address", 'user_addresses', 'address_id')
				],
				[new ListOf("roles", 'user_roles')],
			)]
		);

	}

	public function testOk(): void {
		$dataModel = $this->getDataModel();

		$builder = new DataModelQueryBuilder(
			new MysqlQuoter(),
			$dataModel
		);
		$query = $builder->getInsertQuery('users');
		$this->assertEquals(
			"INSERT INTO `users` (`user_id`, `customer_id`, `sequence`, `first_name`, `last_name`, `address_id`) VALUES (:user_id, :customer_id, :sequence, :first_name, :last_name, :address_id)",
			$query->query
		);
		$this->assertEquals([
			'user_id', 'customer_id', 'sequence', 'first_name', 'last_name', 'address_id'
		], $query->boundParams);

		$builder = new DataModelQueryBuilder(
			new MysqlQuoter(),
			$dataModel
		);
		$query = $builder->getUpdateQuery('users');
		$this->assertEquals(
			"UPDATE `users` SET `customer_id` = :customer_id, `sequence` = :sequence, `first_name` = :first_name, `last_name` = :last_name, `address_id` = :address_id WHERE `user_id` = :user_id",
			$query->query
		);
		$this->assertEquals([
			'user_id', 'customer_id', 'sequence', 'first_name', 'last_name', 'address_id'
		], $query->boundParams);

		$query = $builder->getDeleteQuery('users');
		$this->assertEquals(
			"DELETE FROM `users` WHERE `user_id` = :user_id",
			$query->query
		);
		$this->assertEquals(['user_id'], $query->boundParams);
	}

	public function testBrokenUpdate(): void {
		$builder = new DataModelQueryBuilder(
			new MysqlQuoter(),
			new DataModel(
				new ModelRoot('users'),
				['users' => new DataPart(
					new Table("users"),
					new Fields,
					new KeyField('user_id'),
					null,
					null,
					null,
					null,
					[],
					[]
				)]
			)
		);
		$query = $builder->getUpdateQuery('users');
		$this->assertEquals(
			"UPDATE `users` SET `user_id` = :user_id WHERE `user_id` = :user_id",
			$query->query
		);
		$this->assertEquals([
			'user_id'
		], $query->boundParams);
	}

}