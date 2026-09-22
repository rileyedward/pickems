<?php

use App\Models\Game;
use App\Models\Week;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
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
    ->use(RefreshDatabase::class)
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

/**
 * A trimmed, real ESPN response from tests/Fixtures/espn.
 *
 * @return array<string, mixed>
 */
function espnFixture(string $name): array
{
    return json_decode(file_get_contents(__DIR__."/Fixtures/espn/{$name}.json"), true);
}

/**
 * Fake ESPN: the teams endpoint, a scoreboard per week (weeks without a
 * fixture return no games), and logo downloads.
 *
 * @param  array<int, string>  $weeks  week number => fixture name
 */
function fakeEspn(array $weeks = []): void
{
    Http::fake(function (Request $request) use ($weeks) {
        $url = $request->url();

        if (str_contains($url, 'espncdn.com')) {
            return Http::response('png-bytes', 200, ['Content-Type' => 'image/png']);
        }

        if (str_ends_with(parse_url($url, PHP_URL_PATH), '/teams')) {
            return Http::response(espnFixture('teams'));
        }

        if (str_contains($url, '/scoreboard')) {
            $week = (int) $request->data()['week'];

            return Http::response(isset($weeks[$week]) ? espnFixture($weeks[$week]) : ['events' => []]);
        }

        return Http::response([], 404);
    });
}

/**
 * An open week with `$count` upcoming games between fresh teams; the last
 * game is the tie-breaker.
 */
function openWeekWithGames(int $count = 3, array $weekAttributes = []): Week
{
    $week = Week::factory()->open()->create($weekAttributes);

    foreach (range(1, $count) as $i) {
        Game::factory()->for($week)->create([
            'kickoff_at' => now()->addDays(2)->addHours($i),
            'is_tiebreaker' => $i === $count,
        ]);
    }

    return $week->fresh();
}
