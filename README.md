# Lightweight ORM

## Examples

### User Domain Model
```php
#[ModelRoot('users')]
class UserDomainModel {
    public function __construct(
        #[Table("org_users")]
        #[KeyField('id'), Fields('first_name', 'org_id')]
        #[ListOf(fieldName: 'credentials', targetName: 'userCredentials')]
        #[ListOf(fieldName: 'roles', targetName: 'roles')]
        #[ListOf(fieldName: 'tags', targetName: 'tags')]
        public array $users,

            #[Table('org_user_roles'), KeyField('id'), ParentField('user_id'), Fields('role_id'), GroupField('code')]
            public array $roles,

            #[Table('org_user_credentials')]
            #[KeyField('id'), ParentField('user_id'), Fields('username', 'password')]
            public array $userCredentials,

            #[Table("org_user_tags"), KeyField('id'), ParentField('user_id'), Fields('tag_id')]
            public array $tags
    ) {}
}
```

### User Query Model
```php
#[ModelRoot('users')]
class UserQueryModel {
    public function __construct(
        #[Table("org_users")]
        #[KeyField('id'), Fields('first_name', 'org_id')]
        #[OneOf(fieldName: 'org', targetName: 'orgs', sourceField: 'org_id')]
        #[ListOf(fieldName: 'credentials', targetName: 'userCredentials')]
        #[ListOf(fieldName: 'roles', targetName: 'roles')]
        #[ListOf(fieldName: 'tags', targetName: 'tags')]
        public array $users,

            #[CrossTable('org_user_roles', parentField: 'user_id', sourceField: 'role_id', targetField: 'id')]
            #[Table('org_roles'), KeyField('id'), Fields('name', 'code'), GroupField('code')]
            public array $roles,

            #[Table('orgs')]
            #[KeyField('id'), Fields('name'), ParentField('id')]
            public array $orgs,

            #[Table('org_user_credentials')]
            #[KeyField('id'), ParentField('user_id'), Fields('username', 'password')]
            public array $userCredentials,

            #[CrossTable('org_user_tags', parentField: 'user_id', sourceField: 'tag_id', targetField: 'id')]
            #[Table("org_tag_user_group_values"), KeyField('id'), Fields('value', 'group_id'), GroupField('id')]
            #[OneOf(fieldName: 'group', targetName: 'tagGroups', sourceField: 'group_id')]
            public array $tags,

                #[Table("org_tag_user_groups"), KeyField('id'), Fields('name'), ParentField('id')]
                public array $tagGroups
    ) {}
}
```

## Working with data

### Preparation steps
```php
$queryExecutor = new PdoQueryExecutor($connector);
$factory = new DataModelFactory(new SqliteQuoter, $queryExecutor);
```

### Fetching data
```php
$model = (new DataModelBuilder)->build(UserQueryModel::class);
$dataModelFetcher = $factory->getFetcher($model);
$data = $dataModelFetcher->fetchData(new QueryFilter(
    new FieldExpression('id', '<', new SqlValue(2))
)); //contains the full object as defined in the model above
```

### Synchronizing data
```php
$model = (new DataModelBuilder)->build(UserDomainModel::class);
$dataModelSynchronizer = $factory->getSynchronizer($model);
$data = $dataModelSynchronizer->syncData(
    [$recordToBeRemoved, $oldData],
    [$newData, $recordToBeAdded]
); 
//1. deletes $recordToBeRemoved,
//2. updates $oldData to $newData
//3. inserts $recordToBeAdded
```

