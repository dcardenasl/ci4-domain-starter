<?php

declare(strict_types=1);

namespace App\Libraries\Query;

use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Model;

/**
 * Filter Operator Applier
 *
 * Applies filter operators to query builders.
 * Centralizes operator logic to eliminate duplication.
 */
class FilterOperatorApplier
{
    /**
     * Apply a filter operator to a query builder
     *
     * @param Model|BaseBuilder $builder Query builder instance
     * @param string $field Field name to filter
     * @param string $operator Filter operator (=, !=, >, <, >=, <=, LIKE, IN, NOT IN, BETWEEN, IS NULL, IS NOT NULL)
     * @param mixed $value Filter value
     * @return void
     */
    public static function apply(Model|BaseBuilder $builder, string $field, string $operator, mixed $value): void
    {
        match ($operator) {
            '=' => $builder->where($field, $value),
            '!=' => $builder->where($field . ' !=', $value),
            '>' => $builder->where($field . ' >', $value),
            '<' => $builder->where($field . ' <', $value),
            '>=' => $builder->where($field . ' >=', $value),
            '<=' => $builder->where($field . ' <=', $value),
            'LIKE' => $builder->like($field, $value),
            'IN' => $builder->whereIn($field, $value),
            'NOT IN' => $builder->whereNotIn($field, $value),
            'BETWEEN' => self::applyBetween($builder, $field, $value),
            'IS NULL' => $builder->where($field, null),
            'IS NOT NULL' => $builder->where($field . ' !=', null),
            default => null,
        };
    }

    /**
     * Apply BETWEEN operator
     *
     * @param Model|BaseBuilder $builder
     * @param string $field
     * @param mixed $value
     * @return void
     */
    private static function applyBetween(Model|BaseBuilder $builder, string $field, mixed $value): void
    {
        if (is_array($value) && count($value) === 2) {
            $builder->where($field . ' >=', $value[0]);
            $builder->where($field . ' <=', $value[1]);
        }
    }
}
