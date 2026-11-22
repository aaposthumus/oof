<?php

namespace App\Http\Controllers;

use App\Achievements\Stamps\StampArt;
use App\Models\Achievement;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * An achievement's art framed for the stamp window, for the CMS (Phase 12f).
 * Only for people who may use the admin panel.
 */
class StampArtController extends Controller
{
    public function show(Request $request, Achievement $achievement, StampArt $art): BinaryFileResponse
    {
        abort_unless($request->user()?->canAccessPanel(Filament::getPanel('nimda')) === true, 403);

        $source = $art->pathFor($achievement, $request->query('variant'));
        abort_if($source === null, 404);

        return response()->file($art->framed($source), ['Content-Type' => 'image/png', 'Cache-Control' => 'private, max-age=3600']);
    }
}
