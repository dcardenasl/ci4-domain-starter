<?php

declare(strict_types=1);

namespace App\Libraries\Query;

use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Model;

/**
 * Search Query Applier
 *
 * Applies FULLTEXT or LIKE search queries to query builders.
 * Centralizes search logic to eliminate duplication.
 */
class SearchQueryApplier
{
    /**
     * Apply search to a query builder
     *
     * @param Model|BaseBuilder $builder Query builder instance
     * @param string $query Search query string
     * @param array $searchableFields Fields to search in
     * @param bool $useFulltext Whether to use FULLTEXT search (MySQL only)
     * @return void
     */
    public static function apply(
        Model|BaseBuilder $builder,
        string $query,
        array $searchableFields,
        bool $useFulltext = true
    ): void {
        $apiConfig = config('Api', false);
        if (empty($searchableFields) || empty($query)) {
            return;
        }

        // Check minimum search length
        $minLength = $apiConfig->searchMinLength;
        if (strlen($query) < $minLength) {
            return;
        }

        // Check if search is enabled
        if (!$apiConfig->searchEnabled) {
            return;
        }
        if ($useFulltext) {
            self::applyFulltext($builder, $query, $searchableFields);
        } else {
            self::applyLike($builder, $query, $searchableFields);
        }
    }

    /**
     * Apply FULLTEXT search (MySQL only)
     *
     * @param Model|BaseBuilder $builder
     * @param string $query
     * @param array $searchableFields
     * @return void
     */
    public static function applyFulltext(
        Model|BaseBuilder $builder,
        string $query,
        array $searchableFields
    ): void {
        // Get the actual builder instance
        $actualBuilder = $builder instanceof Model ? $builder->builder() : $builder;

        $fields = implode(', ', $searchableFields);
        // Get database connection
        $db = $builder instanceof Model ? $builder->db : $builder->db();
        $escapedQuery = $db->escape($query);
        $actualBuilder->where("MATCH($fields) AGAINST($escapedQuery IN BOOLEAN MODE)", null, false);
    }

    /**
     * Apply LIKE search fallback
     *
     * @param Model|BaseBuilder $builder
     * @param string $query
     * @param array $searchableFields
     * @return void
     */
    public static function applyLike(
        Model|BaseBuilder $builder,
        string $query,
        array $searchableFields
    ): void {
        // Get the actual builder instance
        $actualBuilder = $builder instanceof Model ? $builder->builder() : $builder;

        $actualBuilder->groupStart();

        foreach ($searchableFields as $index => $field) {
            if ($index === 0) {
                $actualBuilder->like($field, $query);
            } else {
                $actualBuilder->orLike($field, $query);
            }
        }

        $actualBuilder->groupEnd();
    }
}
