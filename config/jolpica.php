<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Jolpica-F1 API
    |--------------------------------------------------------------------------
    |
    | Ergast-compatible API used as the source of F1 history. Jolpica requires a
    | custom User-Agent and allows 4 requests per second and 500 per hour, so
    | the importer waits at least `min_request_interval_ms` between requests.
    |
    */

    'base_url' => env('JOLPICA_BASE_URL', 'https://api.jolpi.ca/ergast/f1'),

    'user_agent' => env('JOLPICA_USER_AGENT', 'OOF/0.1'),

    'min_request_interval_ms' => (int) env('JOLPICA_MIN_REQUEST_INTERVAL_MS', 7500),

    'connect_timeout' => 5,

    'timeout' => 30,

    'page_limit' => 100,

    /*
    | Waits in seconds before each retry of a throttled (429) or failed request.
    | A Retry-After header takes precedence.
    */
    'retry_backoff_seconds' => [15, 60, 120, 300],

    /*
    | A stored response counts as final once it was fetched this many days after
    | the race it covers. Until then it is fetched again on the next import.
    */
    'final_after_days' => 3,

    /*
    | First season each endpoint has data for.
    */
    'first_season' => 1950,
    'constructor_standings_from' => 1958,
    'sprint_from' => 2021,
    'pit_stops_from' => 2011,
    'laps_from' => 1996,

    /*
    | Credit line required by the CC BY-NC-SA 4.0 data licence, for the website
    | and posts.
    */
    'attribution' => 'Data: Jolpica-F1',

];
