<?php

namespace App\Achievements\Facts;

/**
 * A catalogue template such as "P{grid} → P{position}": each {name} is a
 * fact attribute or a value the trigger computed ({count}, {total}). An
 * empty value renders as nothing; the result is trimmed, and an empty
 * result is no text at all.
 */
final class Template
{
    /**
     * @param  array<string, mixed>  $values
     */
    public static function render(?string $template, Fact $fact, array $values = []): ?string
    {
        if ($template === null) {
            return null;
        }

        $text = trim(preg_replace_callback(
            '/\{([a-z_][a-z0-9_.]*)\}/',
            fn (array $match) => (string) (array_key_exists($match[1], $values) ? $values[$match[1]] : $fact->get($match[1])),
            $template,
        ));

        return $text === '' ? null : $text;
    }
}
