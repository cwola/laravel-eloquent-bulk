# laravel-eloquent-bulk

Providing bulk-insert for the Eloquent models(Cwola library).

## Overview

A simple drop-in solution for providing bulk-insert for the Eloquent models.

## Requirement
- PHP8.0+

## Installation
```
composer require cwola/laravel-eloquent-bulk
```

## Usage
```
<?php

use Illuminate\Support\Collection;
use Cwola\LaravelEloquentBulk\Bulk;

$models = Collection::make([
    ...eloquent-models
]);

Bulk::insert($models);
```

## Licence

[MIT](https://github.com/cwola/laravel-eloquent-bulk/blob/main/LICENSE)
