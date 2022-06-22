<?php

namespace Walnut\Lib\DbOrm;

use Walnut\Lib\DbQuery\QueryExecutor;
use Walnut\Lib\DbDataModel\DataModel;

/**
 * @package Walnut\Lib\DbOrm
 */
final class DataModelSynchronizer implements RelationalStorageSynchronizer {

	public function __construct(
		private readonly QueryExecutor $queryExecutor,
		private readonly RelationalStorageQueryBuilder $queryBuilder,
		private readonly DataModel $model
	) { }

	/**
	 * @param array $oldData
	 * @param array $newData
	 */
	public function synchronizeData(array $oldData, array $newData): void {
		$modelRoot = $this->model->modelRoot->modelRoot;
		$this->synchronizeCollection($modelRoot,$oldData, $newData);
	}

	/**
	 * @param string $modelName
	 * @param array[] $oldData
	 * @param array[] $newData
	 */
	private function synchronizeCollection(string $modelName, array $oldData, array $newData): void {
		$model = $this->model->part($modelName);

		$existingOld = [];
		$existingNew = [];
		$incoming = [];

		foreach ($oldData as $entity) {
			$id = $entity[$model->keyField->name] ?? null;
			if ($id) {
				$existingOld[$id] = $entity;
				$existingNew[$id] = null;
			}
		}
		foreach ($newData as $entity) {
			$id = $entity[$model->keyField->name] ?? null;
			if ($id && array_key_exists($id, $existingOld)) {
				$existingNew[$id] = $entity;
			} else {
				$incoming[] = $entity;
			}
		}
		foreach($existingOld as $id => $oldEntity) {
			$newEntity = $existingNew[$id];
			$this->synchronizeEntity($modelName, $oldEntity, $newEntity);
		}
		foreach($incoming as $newEntity) {
			$this->synchronizeEntity($modelName, null, $newEntity);
		}
	}

	private function synchronizeEntity(string $modelName, ?array $oldEntity, ?array $newEntity): void {
		if ($oldEntity === null && $newEntity === null) {
			return;
		}
		$model = $this->model->part($modelName);
		$pk = $model->keyField->name;
		if ($newEntity !== null) {
			foreach($model->oneOfFields as $oneOfField) {
				$targetPk = $this->model->part($oneOfField->targetName)->keyField->name;
				if ($oneOfField->sourceField) {
					$oldRelatedEntity = $oldEntity[$oneOfField->fieldName] ?? null;
					$newRelatedEntity = $newEntity[$oneOfField->fieldName] ?? null;
					$this->synchronizeEntity(
						$oneOfField->targetName,
						$oldRelatedEntity !== null &&
							($oldEntity[$oneOfField->sourceField] ?? null) ===
							($newEntity[$oneOfField->sourceField] ?? null) ?
							$oldRelatedEntity : null,
						$newRelatedEntity
					);
					if ($oldEntity !== null) {
						$oldEntity[$oneOfField->sourceField] = $oldRelatedEntity[$targetPk] ?? null;
					}
					if ($newEntity !== null) {
						$newEntity[$oneOfField->sourceField] = $newRelatedEntity[$targetPk] ?? null;
					}
				}
			}

			if ($oldEntity !== null && ($newEntity[$pk] ?? null) === ($oldEntity[$pk] ?? null)) {
				//if ($newEntity != $oldEntity) {
					$this->queryBuilder->getUpdateQuery($modelName)->execute($this->queryExecutor, $newEntity);
				//}
			} else {
				$this->queryBuilder->getInsertQuery($modelName)->execute($this->queryExecutor, $newEntity);
				//$newState[$pk] ??= $this->queryExecutor->lastInsertId();
			}
		}

		foreach($model->oneOfFields as $oneOfField) {
			if (!$oneOfField->sourceField) {
				$targetField = $this->model->part($oneOfField->targetName)->parentField->name ?? null;

				$oldRelatedEntity = $oldEntity[$oneOfField->fieldName] ?? null;
				if ($oldRelatedEntity) {
					$oldRelatedEntity[$targetField] = $oldEntity[$pk] ?? null;
				}
				$newRelatedEntity = $newEntity[$oneOfField->fieldName] ?? null;
				if ($newRelatedEntity) {
					$newRelatedEntity[$targetField] = $newEntity[$pk] ?? null;
				}
				$this->synchronizeEntity(
					$oneOfField->targetName,
					$oldRelatedEntity,
					$newRelatedEntity
				);
			}
		}
		foreach($model->listOfFields as $listOfField) {
			$p = $this->model->part($listOfField->targetName);
			$targetField = $p->parentField->name;
			$sortField = $p->sortField->name ?? null;
			$oldRelatedEntities = $oldEntity[$listOfField->fieldName] ?? [];
			$newRelatedEntities = $newEntity[$listOfField->fieldName] ?? [];
			if ($newEntity) {
				$pkValue = $newEntity[$pk];
				foreach($newRelatedEntities as &$newRelatedEntity) {
					$newRelatedEntity[$targetField] = $pkValue;
				}
				unset($newRelatedEntity);
				if ($sortField) {
					$sortSequence = 0;
					foreach($newRelatedEntities as &$newRelatedEntity) {
						$newRelatedEntity[$sortField] = $sortSequence++;
					}
				}
				unset($newRelatedEntity);
			}
			$this->synchronizeCollection(
				$listOfField->targetName,
				$oldRelatedEntities,
				$newRelatedEntities
			);
		}

		if ($oldEntity !== null) {
			if ($newEntity === null || ($newEntity[$pk] ?? null) !== ($oldEntity[$pk] ?? null)) {
				$this->queryBuilder->getDeleteQuery($modelName)->execute($this->queryExecutor, $oldEntity);
			}
			foreach($model->oneOfFields as $oneOfField) {
				if ($oneOfField->sourceField) {
					$targetPk = $this->model->part($oneOfField->targetName)->keyField->name;

					$oldRelatedEntity = $oldEntity[$oneOfField->fieldName] ?? null;
					$newRelatedEntity = $newEntity[$oneOfField->fieldName] ?? null;

					$this->synchronizeEntity(
						$oneOfField->targetName,
						$oldRelatedEntity &&
							($oldRelatedEntity[$targetPk] ?? null) !== ($newRelatedEntity[$targetPk] ?? null) ?
							$oldRelatedEntity : null,
						null
					);
				}
			}
		}
	}

}