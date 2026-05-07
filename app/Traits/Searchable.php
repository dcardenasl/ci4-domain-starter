<?php

namespace App\Traits;

use App\Libraries\Query\SearchQueryApplier;

/**
 * Searchable Trait
 *
 * Adds full-text search capabilities to models. Models using this trait should
 * define a $searchableFields property listing fields to search.
 */
trait Searchable
{
    /**
     * Apply search to the query
     *
     * @param string $query Search query string
     * @return self
     */
    public function search(string $query): self
    {
        if (empty($this->searchableFields) || empty($query)) {
            return $this;
        }

        SearchQueryApplier::apply(
            $this,
            $query,
            $this->searchableFields,
            $this->useFulltextSearch()
        );

        return $this;
    }

    /**
     * Determine if FULLTEXT search should be used
     *
     * @return bool
     */
    protected function useFulltextSearch(): bool
    {
        $apiConfig = config('Api', false);

        // Check if FULLTEXT is explicitly disabled
        if (!$apiConfig->searchUseFulltext) {
            return false;
        }

        // Check if database driver is MySQL/MySQLi
        $dbDriver = $this->db->DBDriver ?? '';

        if (! in_array(strtolower($dbDriver), ['mysqli', 'mysql'], true)) {
            return false;
        }

        // Only use FULLTEXT if explicitly enabled and we have searchable fields
        return $apiConfig->searchEnabled && ! empty($this->searchableFields);
    }

    /**
     * Get searchable fields
     *
     * @return array
     */
    public function getSearchableFields(): array
    {
        return $this->searchableFields;
    }

    /**
     * Check if a field is searchable
     *
     * @param string $field Field name
     * @return bool
     */
    public function isSearchable(string $field): bool
    {
        return in_array($field, $this->searchableFields, true);
    }
}
