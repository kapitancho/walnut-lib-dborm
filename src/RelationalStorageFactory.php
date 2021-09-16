<?php

namespace Walnut\Lib\DbOrm;

use Walnut\Lib\DbDataModel\DataModel;

/**
 * @package Walnut\Lib\DbOrm
 */
interface RelationalStorageFactory {
	public function getFetcher(DataModel $model): RelationalStorageFetcher;
	public function getSynchronizer(DataModel $model): RelationalStorageSynchronizer;
}