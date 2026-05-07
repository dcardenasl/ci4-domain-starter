<?php

declare(strict_types=1);

namespace Tests\Unit\Libraries;

use App\Libraries\Query\FilterParser;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * FilterParser Tests
 */
class FilterParserTest extends CIUnitTestCase
{
    public function testParseConvertsSimpleValueToEquals(): void
    {
        $filters = ['role' => 'admin'];

        $result = FilterParser::parse($filters);

        $this->assertEquals(['role' => ['=', 'admin']], $result);
    }

    public function testParseConvertsArrayWithoutOperatorToIn(): void
    {
        $filters = ['status' => ['active', 'pending']];

        $result = FilterParser::parse($filters);

        $this->assertEquals(['status' => ['IN', ['active', 'pending']]], $result);
    }

    public function testParseHandlesGreaterThanOperator(): void
    {
        $filters = ['age' => ['gt' => 18]];

        $result = FilterParser::parse($filters);

        $this->assertEquals(['age' => ['>', 18]], $result);
    }

    public function testParseHandlesGreaterThanOrEqualOperator(): void
    {
        $filters = ['age' => ['gte' => 21]];

        $result = FilterParser::parse($filters);

        $this->assertEquals(['age' => ['>=', 21]], $result);
    }

    public function testParseHandlesLessThanOperator(): void
    {
        $filters = ['age' => ['lt' => 65]];

        $result = FilterParser::parse($filters);

        $this->assertEquals(['age' => ['<', 65]], $result);
    }

    public function testParseHandlesLessThanOrEqualOperator(): void
    {
        $filters = ['age' => ['lte' => 100]];

        $result = FilterParser::parse($filters);

        $this->assertEquals(['age' => ['<=', 100]], $result);
    }

    public function testParseHandlesNotEqualOperator(): void
    {
        $filters = ['status' => ['ne' => 'banned']];

        $result = FilterParser::parse($filters);

        $this->assertEquals(['status' => ['!=', 'banned']], $result);
    }

    public function testParseHandlesLikeOperator(): void
    {
        $filters = ['email' => ['like' => '%@gmail.com']];

        $result = FilterParser::parse($filters);

        $this->assertEquals(['email' => ['LIKE', '%@gmail.com']], $result);
    }

    public function testParseHandlesInOperator(): void
    {
        $filters = ['role' => ['in' => ['admin', 'moderator']]];

        $result = FilterParser::parse($filters);

        $this->assertEquals(['role' => ['IN', ['admin', 'moderator']]], $result);
    }

    public function testParseHandlesNotInOperator(): void
    {
        $filters = ['status' => ['not_in' => ['deleted', 'banned']]];

        $result = FilterParser::parse($filters);

        $this->assertEquals(['status' => ['NOT IN', ['deleted', 'banned']]], $result);
    }

    public function testParseHandlesBetweenOperator(): void
    {
        $filters = ['age' => ['between' => [18, 65]]];

        $result = FilterParser::parse($filters);

        $this->assertEquals(['age' => ['BETWEEN', [18, 65]]], $result);
    }

    public function testParseHandlesIsNullOperator(): void
    {
        $filters = ['deleted_at' => ['null' => true]];

        $result = FilterParser::parse($filters);

        $this->assertEquals(['deleted_at' => ['IS NULL', null]], $result);
    }

    public function testParseHandlesIsNotNullOperator(): void
    {
        $filters = ['email_verified_at' => ['not_null' => true]];

        $result = FilterParser::parse($filters);

        $this->assertEquals(['email_verified_at' => ['IS NOT NULL', null]], $result);
    }

    public function testParseHandlesMultipleFilters(): void
    {
        $filters = [
            'department' => 'sales',
            'age' => ['gt' => 18],
            'status' => ['in' => ['active', 'pending']],
        ];

        $result = FilterParser::parse($filters);

        $expected = [
            'department' => ['=', 'sales'],
            'age' => ['>', 18],
            'status' => ['IN', ['active', 'pending']],
        ];

        $this->assertEquals($expected, $result);
    }

    public function testIsValidFieldReturnsTrueForAllowedField(): void
    {
        $result = FilterParser::isValidField('email', ['email', 'name', 'status']);

        $this->assertTrue($result);
    }

    public function testIsValidFieldReturnsFalseForDisallowedField(): void
    {
        $result = FilterParser::isValidField('password', ['email', 'name', 'status']);

        $this->assertFalse($result);
    }

    public function testFilterAllowedFieldsRemovesDisallowedFields(): void
    {
        $filters = [
            'email' => 'test@example.com',
            'password' => 'secret',
        ];

        $result = FilterParser::filterAllowedFields($filters, ['email', 'role']);

        $expected = [
            'email' => 'test@example.com',
        ];

        $this->assertEquals($expected, $result);
    }

    public function testParseSortHandlesSingleFieldAscending(): void
    {
        $result = FilterParser::parseSort('created_at');

        $this->assertEquals([['created_at', 'ASC']], $result);
    }

    public function testParseSortHandlesSingleFieldDescending(): void
    {
        $result = FilterParser::parseSort('-created_at');

        $this->assertEquals([['created_at', 'DESC']], $result);
    }

    public function testParseSortHandlesMultipleFields(): void
    {
        $result = FilterParser::parseSort('-created_at,email');

        $expected = [
            ['created_at', 'DESC'],
            ['email', 'ASC'],
        ];

        $this->assertEquals($expected, $result);
    }

    public function testParseSortFiltersDisallowedFields(): void
    {
        $result = FilterParser::parseSort('email,password,-created_at', ['email', 'created_at']);

        $expected = [
            ['email', 'ASC'],
            ['created_at', 'DESC'],
        ];

        $this->assertEquals($expected, $result);
    }

    public function testParseSortAllowsAllFieldsWhenNoAllowedFieldsSpecified(): void
    {
        $result = FilterParser::parseSort('field1,field2,-field3');

        $expected = [
            ['field1', 'ASC'],
            ['field2', 'ASC'],
            ['field3', 'DESC'],
        ];

        $this->assertEquals($expected, $result);
    }
}
