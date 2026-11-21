<?php

namespace App\Achievements\Facts;

/**
 * A fact provider whose facts keep the car a driver left for another one
 * in the race (rule D8 sets such a car aside everywhere else). The pole is
 * earned in qualifying, before any car swap, so the qualifying order keeps
 * it (decided by the owner on 21 November 2026). Plain withdrawals (D9)
 * are still left out.
 */
interface KeepsLeftCars {}
