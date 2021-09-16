<?php

namespace Walnut\Lib\DbOrm;

/**
 * @package Walnut\Lib\DbOrm
 */
interface RelationalStorageSynchronizer {
	public function synchronizeData(array $oldData, array $newData): void;
}