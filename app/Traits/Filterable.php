<?php

namespace App\Traits;

use App\Libraries\Query\FilterOperatorApplier;
use App\Libraries\Query\FilterParser;

/**
 * Filterable Trait
 *
 * Adds filtering capabilities to models. Models using this trait should
 * define a $filterableFields property listing allowed filter fields.
 */
trait Filterable
{
    /**
     * Apply filters to the query
     *
     * @param array $filters Raw filter array from request
     * @return self
     */
    public function applyFilters(array $filters): self
    {
        // Validate fields against filterable fields
        if (! empty($this->filterableFields)) {
            $filters = FilterParser::filterAllowedFields($filters, $this->filterableFields);
        }

        // Parse filters
        $parsedFilters = FilterParser::parse($filters);

        // Apply each filter using centralized operator applier
        foreach ($parsedFilters as $field => $condition) {
            [$operator, $value] = $condition;
            FilterOperatorApplier::apply($this, $field, $operator, $value);
        }

        return $this;
    }

    /**
     * Get filterable fields
     *
     * @return array
     */
    public function getFilterableFields(): array
    {
        return $this->filterableFields;
    }

    /**
     * Check if a field is filterable
     *
     * @param string $field Field name
     * @return bool
     */
    public function isFilterable(string $field): bool
    {
        return in_array($field, $this->filterableFields, true);
    }
}
