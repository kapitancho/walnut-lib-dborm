<?php

namespace Walnut\Lib\DbOrm;

use Walnut\Lib\DbQueryBuilder\QueryPart\QueryFilter;

/**
 * @template T
 * @package Walnut\Lib\DbOrm
 */
interface RelationalStorageFetcher {
	/**
	 * @param QueryFilter $filter
	 * @return T[]
	 */
	public function fetchData(QueryFilter $filter): array;
}