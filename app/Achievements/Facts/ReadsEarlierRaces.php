<?php

namespace App\Achievements\Facts;

/**
 * A predicate or fact provider that reads races before the session's round
 * (team form over the last Grands Prix), so a late result change in one of
 * them can change its outcome. oof:achievements --stale re-runs such sessions.
 */
interface ReadsEarlierRaces {}
