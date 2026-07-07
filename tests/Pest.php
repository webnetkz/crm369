<?php

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(LazilyRefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * @return array{
 *     name: string,
 *     area_sqm: float,
 *     rows: array<int, array{
 *         name: string,
 *         columns: array<int, array{
 *             name: string,
 *             floors: array<int, array{
 *                 name: string,
 *                 places: array<int, array{name: string}>
 *             }>
 *         }>
 *     }>
 * }
 */
function warehouseHierarchyPayload(string $name = 'Центральный склад', float $area = 1250.5): array
{
    return [
        'name' => $name,
        'area_sqm' => $area,
        'rows' => [
            [
                'name' => 'Ряд A',
                'columns' => [
                    [
                        'name' => 'Колонка 01',
                        'floors' => [
                            [
                                'name' => 'Этаж 1',
                                'places' => [
                                    ['name' => 'A-01-1-001'],
                                    ['name' => 'A-01-1-002'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Ряд B',
                'columns' => [
                    [
                        'name' => 'Колонка 02',
                        'floors' => [
                            [
                                'name' => 'Этаж 1',
                                'places' => [
                                    ['name' => 'B-02-1-001'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ];
}
