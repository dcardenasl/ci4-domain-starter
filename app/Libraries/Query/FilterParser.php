<?php

namespace App\Libraries\Query;

/**
 * FilterParser
 *
 * Parses filter arrays from request parameters into database query conditions.
 * Supports various operators and nested conditions.
 */
class FilterParser
{
    /**
     * Parse filters array into query conditions
     *
     * Converts input like:
     * ['role' => 'admin'] to ['role' => ['=', 'admin']]
     * ['age' => ['gt' => 18]] to ['age' => ['>', 18]]
     * ['status' => ['in' => ['active', 'pending']]] to ['status' => ['IN', ['active', 'pending']]]
     *
     * @param array $filters Raw filter array from request
     * @return array Normalized filter array with [field => [operator, value]]
     */
    public static function parse(array $filters): array
    {
        $parsed = [];

        foreach ($filters as $field => $value) {
            // If value is not an array, treat as equals
            if (! is_array($value)) {
                $parsed[$field] = ['=', $value];
                continue;
            }

            // If value is array, check for operators
            $operator = self::detectOperator($value);

            if ($operator) {
                $parsed[$field] = $operator;
            } else {
                // No operator found, treat as IN
                $parsed[$field] = ['IN', $value];
            }
        }

        return $parsed;
    }

    /**
     * Detect operator from value array
     *
     * @param array $value Value array that may contain operator
     * @return array|null [operator, value] or null if no operator found
     */
    protected static function detectOperator(array $value): ?array
    {
        $operatorMap = [
            'eq' => '=',
            'ne' => '!=',
            'neq' => '!=',
            'gt' => '>',
            'gte' => '>=',
            'ge' => '>=',
            'lt' => '<',
            'lte' => '<=',
            'le' => '<=',
            'like' => 'LIKE',
            'in' => 'IN',
            'not_in' => 'NOT IN',
            'notin' => 'NOT IN',
            'between' => 'BETWEEN',
            'null' => 'IS NULL',
            'not_null' => 'IS NOT NULL',
            'notnull' => 'IS NOT NULL',
        ];

        foreach ($operatorMap as $key => $operator) {
            if (isset($value[$key])) {
                // Special handling for null checks (no value needed)
                if ($operator === 'IS NULL' || $operator === 'IS NOT NULL') {
                    return [$operator, null];
                }

                return [$operator, $value[$key]];
            }
        }

        return null;
    }

    /**
     * Validate field against allowed fields
     *
     * @param string $field Field name to validate
     * @param array $allowedFields List of allowed field names
     * @return bool
     */
    public static function isValidField(string $field, array $allowedFields): bool
    {
        return in_array($field, $allowedFields, true);
    }

    /**
     * Filter out invalid fields from filters array
     *
     * @param array $filters Filters to validate
     * @param array $allowedFields List of allowed field names
     * @return array Filtered array containing only allowed fields
     */
    public static function filterAllowedFields(array $filters, array $allowedFields): array
    {
        return array_filter(
            $filters,
            fn ($field) => self::isValidField($field, $allowedFields),
            ARRAY_FILTER_USE_KEY
        );
    }

    /**
     * Parse sort parameter
     *
     * @param string $sort Sort string (e.g., "-created_at,email")
     * @param array $allowedFields List of allowed field names for sorting
     * @return array Array of [field, direction] pairs
     */
    public static function parseSort(string $sort, array $allowedFields = []): array
    {
        $sortFields = explode(',', $sort);
        $parsed = [];

        foreach ($sortFields as $field) {
            $field = trim($field);
            $direction = 'ASC';

            if (str_starts_with($field, '-')) {
                $direction = 'DESC';
                $field = substr($field, 1);
            }

            // Validate field if allowed fields specified
            if (! empty($allowedFields) && ! in_array($field, $allowedFields, true)) {
                continue;
            }

            $parsed[] = [$field, $direction];
        }

        return $parsed;
    }
}
