<?php

namespace Walnut\Lib\DbOrm;

use Walnut\Lib\DbQuery\PreparedQuery;

/**
 * @package Walnut\Lib\DbOrm
 */
interface RelationalStorageQueryBuilder {
	public function getInsertQuery(string $modelName): PreparedQuery;
	public function getUpdateQuery(string $modelName): PreparedQuery;
	public function getDeleteQuery(string $modelName): PreparedQuery;
}