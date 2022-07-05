<?php

declare(strict_types=1);

namespace Cwola\LaravelEloquentBulk;

use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Model;
use Cwola\Reflector\ReflectionClass;
use InvalidArgumentException;
use LogicException;

class Bulk {
    /**
     * @param \Illuminate\Support\Collection<Illuminate\Database\Eloquent\Model>|Illuminate\Database\Eloquent\Model[] $models
     * @param int $chunkSize
     * @param array $options [optional]
     *
     * @return bool
     *
     * @throws \InvalidArgumentException
     */
    public static function insert(Collection|array $models, int $chunkSize = 1000, array $options = []): bool {
        $models = Collection::make($models);
        if ($models->isEmpty()) {
            return true;
        }

        $targetInstance = get_class($models->first());
        foreach ($models->chunk($chunkSize) as $chunk) {
            // bulk-insert.
            $inserts = [];
            $reflectors = [];
            // before insert.
            foreach ($chunk as $model) {
                if (!is_object($model) || !($model instanceof Model) || $targetInstance !== get_class($model)) {
                    throw new InvalidArgumentException(sprintf('expected %s but found %s.', $targetInstance, get_class($model)));
                } else if ($model->exists) {
                    throw new LogicException('This eloquent-model has already been persisted.');
                }

                $reflector = ReflectionClass::make($model, useCache:true);
                if (!static::beforeSave($reflector)) {
                    return false;
                }
                if (!static::beforeCreate($reflector)) {
                    return false;
                }
                static::updateTimestamps($model);
                $inserts[] = static::getAttributes($reflector);
                $reflectors[] = $reflector;
            }
            // insert.
            // @TODO without Model->primaryKey if Model->incrementing is true.
            if (!(new $targetInstance)->newQueryWithoutScopes()->insert($inserts)) {
                return false;
            }
            // after insert.
            foreach ($reflectors as $reflector) {
                $model = $reflector->getObject();
                $model->exists = true;
                $model->wasRecentlyCreated = true;
                static::afterCreate($reflector);
                static::finishSave($reflector, $options);
            }
        }
        return true;
    }

    /**
     * @param \Cwola\Reflector\ReflectionClass $reflector
     * @param string $method
     * @param mixed ...$args
     *
     * @return mixed
     */
    protected static function callTo(ReflectionClass $reflector, string $method, mixed ...$args): mixed {
        return $reflector->method($method)->accessible(true)->call(...$args);
    }

    /**
     * @param \Cwola\Reflector\ReflectionClass $reflector
     *
     * @return bool
     */
    protected static function beforeSave(ReflectionClass $reflector): bool {
        return static::callTo($reflector, 'fireModelEvent', 'saving');
    }

    /**
     * @param \Cwola\Reflector\ReflectionClass $reflector
     *
     * @return bool
     */
    protected static function beforeCreate(ReflectionClass $reflector): bool {
        return static::callTo($reflector, 'fireModelEvent', 'creating');
    }

    /**
     * @param \Cwola\Reflector\ReflectionClass $reflector
     *
     * @return bool
     */
    protected static function afterCreate(ReflectionClass $reflector): bool {
        return static::callTo($reflector, 'fireModelEvent', 'created', false);
    }

    /**
     * @param \Cwola\Reflector\ReflectionClass $reflector
     * @param array $options
     *
     * @return bool
     */
    protected static function finishSave(ReflectionClass $reflector, array $options): void {
        static::callTo($reflector, 'finishSave', $options);
    }

    /**
     * @param \Cwola\Reflector\ReflectionClass $reflector
     *
     * @return array
     */
    protected static function getAttributes(ReflectionClass $reflector): array {
        return static::callTo($reflector, 'getAttributesForInsert');
    }

    /**
     * @param \Illuminate\Database\Eloquent\Model $model
     *
     * @return bool
     */
    protected static function updateTimestamps(Model $model): bool {
        if ($model->usesTimestamps()) {
            $model->updateTimestamps();
        }
        return true;
    }
}
