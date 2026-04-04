<?php

declare(strict_types=1);

use Coagus\PhpApiBuilder\Attributes\Table;
use Coagus\PhpApiBuilder\Attributes\PrimaryKey;
use Coagus\PhpApiBuilder\Attributes\SoftDelete;
use Coagus\PhpApiBuilder\Attributes\Route;
use Coagus\PhpApiBuilder\Attributes\PublicResource;
use Coagus\PhpApiBuilder\Attributes\Middleware;
use Coagus\PhpApiBuilder\Attributes\BelongsTo;
use Coagus\PhpApiBuilder\Attributes\HasMany;
use Coagus\PhpApiBuilder\Attributes\BelongsToMany;
use Coagus\PhpApiBuilder\Attributes\Description;
use Coagus\PhpApiBuilder\Attributes\Example;
use Coagus\PhpApiBuilder\Validation\Attributes\Required;
use Coagus\PhpApiBuilder\Validation\Attributes\Email;
use Coagus\PhpApiBuilder\Validation\Attributes\Unique;
use Coagus\PhpApiBuilder\Validation\Attributes\MaxLength;
use Coagus\PhpApiBuilder\Validation\Attributes\MinLength;
use Coagus\PhpApiBuilder\Validation\Attributes\Min;
use Coagus\PhpApiBuilder\Validation\Attributes\Max;
use Coagus\PhpApiBuilder\Validation\Attributes\Pattern;
use Coagus\PhpApiBuilder\Validation\Attributes\In;
use Coagus\PhpApiBuilder\Validation\Attributes\Hidden;
use Coagus\PhpApiBuilder\Validation\Attributes\IsReadOnly;
use Coagus\PhpApiBuilder\Validation\Attributes\DefaultValue;

#[Table('test_users')]
#[SoftDelete]
#[PublicResource]
#[Route('custom-users')]
#[Middleware('SomeMiddleware')]
#[Description('A test user entity')]
class AttributeTestEntity
{
    #[PrimaryKey]
    public int $id;

    #[Required]
    #[MaxLength(100)]
    #[MinLength(2)]
    #[Description('User name')]
    #[Example('Carlos')]
    public string $name;

    #[Required]
    #[Email]
    #[Unique]
    public string $email;

    #[Hidden]
    public string $password;

    #[Min(0)]
    #[Max(150)]
    public int $age;

    #[Pattern('/^[A-Z]{2,3}$/')]
    public string $code;

    #[In('active', 'inactive', 'banned')]
    public string $status;

    #[IsReadOnly]
    public string $createdAt;

    #[DefaultValue('guest')]
    public string $role;

    #[BelongsTo('RoleEntity', 'role_id')]
    public ?object $roleRelation;

    #[HasMany('OrderEntity', 'user_id')]
    public array $orders;

    #[BelongsToMany('TagEntity', 'user_tags', 'user_id', 'tag_id')]
    public array $tags;
}

test('Table attribute is read correctly', function () {
    $ref = new ReflectionClass(AttributeTestEntity::class);
    $attrs = $ref->getAttributes(Table::class);

    expect($attrs)->toHaveCount(1);
    $table = $attrs[0]->newInstance();
    expect($table->name)->toBe('test_users');
});

test('PrimaryKey attribute is read on property', function () {
    $ref = new ReflectionProperty(AttributeTestEntity::class, 'id');
    $attrs = $ref->getAttributes(PrimaryKey::class);

    expect($attrs)->toHaveCount(1);
});

test('SoftDelete attribute is read on class', function () {
    $ref = new ReflectionClass(AttributeTestEntity::class);
    $attrs = $ref->getAttributes(SoftDelete::class);

    expect($attrs)->toHaveCount(1);
});

test('MaxLength stores its parameter', function () {
    $ref = new ReflectionProperty(AttributeTestEntity::class, 'name');
    $attrs = $ref->getAttributes(MaxLength::class);

    expect($attrs)->toHaveCount(1);
    $maxLength = $attrs[0]->newInstance();
    expect($maxLength->max)->toBe(100);
});

test('MinLength stores its parameter', function () {
    $ref = new ReflectionProperty(AttributeTestEntity::class, 'name');
    $attrs = $ref->getAttributes(MinLength::class);

    $minLength = $attrs[0]->newInstance();
    expect($minLength->min)->toBe(2);
});

test('Min and Max store their parameters', function () {
    $ref = new ReflectionProperty(AttributeTestEntity::class, 'age');

    $min = $ref->getAttributes(Min::class)[0]->newInstance();
    $max = $ref->getAttributes(Max::class)[0]->newInstance();

    expect($min->min)->toBe(0)
        ->and($max->max)->toBe(150);
});

test('Pattern stores regex', function () {
    $ref = new ReflectionProperty(AttributeTestEntity::class, 'code');
    $pattern = $ref->getAttributes(Pattern::class)[0]->newInstance();

    expect($pattern->regex)->toBe('/^[A-Z]{2,3}$/');
});

test('In stores values', function () {
    $ref = new ReflectionProperty(AttributeTestEntity::class, 'status');
    $in = $ref->getAttributes(In::class)[0]->newInstance();

    expect($in->values)->toBe(['active', 'inactive', 'banned']);
});

test('Hidden attribute is readable', function () {
    $ref = new ReflectionProperty(AttributeTestEntity::class, 'password');
    expect($ref->getAttributes(Hidden::class))->toHaveCount(1);
});

test('IsReadOnly attribute is readable', function () {
    $ref = new ReflectionProperty(AttributeTestEntity::class, 'createdAt');
    expect($ref->getAttributes(IsReadOnly::class))->toHaveCount(1);
});

test('DefaultValue stores its value', function () {
    $ref = new ReflectionProperty(AttributeTestEntity::class, 'role');
    $default = $ref->getAttributes(DefaultValue::class)[0]->newInstance();

    expect($default->value)->toBe('guest');
});

test('BelongsTo stores entity and foreign key', function () {
    $ref = new ReflectionProperty(AttributeTestEntity::class, 'roleRelation');
    $attr = $ref->getAttributes(BelongsTo::class)[0]->newInstance();

    expect($attr->entity)->toBe('RoleEntity')
        ->and($attr->foreignKey)->toBe('role_id');
});

test('HasMany stores entity and foreign key', function () {
    $ref = new ReflectionProperty(AttributeTestEntity::class, 'orders');
    $attr = $ref->getAttributes(HasMany::class)[0]->newInstance();

    expect($attr->entity)->toBe('OrderEntity')
        ->and($attr->foreignKey)->toBe('user_id');
});

test('BelongsToMany stores all parameters', function () {
    $ref = new ReflectionProperty(AttributeTestEntity::class, 'tags');
    $attr = $ref->getAttributes(BelongsToMany::class)[0]->newInstance();

    expect($attr->entity)->toBe('TagEntity')
        ->and($attr->pivotTable)->toBe('user_tags')
        ->and($attr->foreignPivotKey)->toBe('user_id')
        ->and($attr->relatedPivotKey)->toBe('tag_id');
});

test('Route attribute stores path', function () {
    $ref = new ReflectionClass(AttributeTestEntity::class);
    $attr = $ref->getAttributes(Route::class)[0]->newInstance();

    expect($attr->path)->toBe('custom-users');
});

test('PublicResource attribute is readable on class', function () {
    $ref = new ReflectionClass(AttributeTestEntity::class);
    expect($ref->getAttributes(PublicResource::class))->toHaveCount(1);
});

test('Middleware attribute stores class name', function () {
    $ref = new ReflectionClass(AttributeTestEntity::class);
    $attr = $ref->getAttributes(Middleware::class)[0]->newInstance();

    expect($attr->class)->toBe('SomeMiddleware');
});

test('Description attribute stores text', function () {
    $ref = new ReflectionClass(AttributeTestEntity::class);
    $attr = $ref->getAttributes(Description::class)[0]->newInstance();

    expect($attr->text)->toBe('A test user entity');
});

test('Example attribute stores value', function () {
    $ref = new ReflectionProperty(AttributeTestEntity::class, 'name');
    $attr = $ref->getAttributes(Example::class)[0]->newInstance();

    expect($attr->value)->toBe('Carlos');
});

test('Required and Email can coexist on same property', function () {
    $ref = new ReflectionProperty(AttributeTestEntity::class, 'email');

    expect($ref->getAttributes(Required::class))->toHaveCount(1)
        ->and($ref->getAttributes(Email::class))->toHaveCount(1)
        ->and($ref->getAttributes(Unique::class))->toHaveCount(1);
});
