<?php

namespace App\Jolpica\Dumps;

use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Fetches Jolpica's free CSV database dump (the delayed one, 14 days behind)
 * into storage, checked against its SHA-256. A dump already stored with the
 * same hash is reused, so the raw data stays on disk and is fetched once.
 */
class DumpDownloader
{
    /**
     * @return array{path: string, uploaded_at: CarbonImmutable, downloaded: bool}
     *
     * @throws RequestException|ConnectionException
     */
    public function latest(): array
    {
        $listing = $this->http()->get(config('jolpica.dumps.listing_url'))->throw()->json();
        $dump = $listing['delayed_dumps']['csv'] ?? throw new RuntimeException('Jolpica lists no free CSV dump.');

        $uploadedAt = CarbonImmutable::parse($dump['uploaded_at']);
        $path = config('jolpica.dumps.directory').'/jolpica-csv-'.$uploadedAt->format('Y-m-d').'.zip';

        if (is_file($path) && hash_file('sha256', $path) === $dump['file_hash']) {
            return ['path' => $path, 'uploaded_at' => $uploadedAt, 'downloaded' => false];
        }

        File::ensureDirectoryExists(dirname($path));
        $partial = $path.'.part';

        $this->http()->timeout(600)->sink($partial)->get($dump['download_url'])->throw();

        if (hash_file('sha256', $partial) !== $dump['file_hash']) {
            File::delete($partial);

            throw new RuntimeException('The downloaded Jolpica dump does not match its published SHA-256.');
        }

        File::move($partial, $path);

        return ['path' => $path, 'uploaded_at' => $uploadedAt, 'downloaded' => true];
    }

    private function http(): PendingRequest
    {
        return Http::withUserAgent(config('jolpica.user_agent'))
            ->connectTimeout(config('jolpica.connect_timeout'))
            ->timeout(config('jolpica.timeout'));
    }
}
