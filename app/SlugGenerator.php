<?php

declare(strict_types=1);

namespace Appocular\Assessor;

class SlugGenerator
{
    /**
     * @param array<string, string> $meta
     */
    public static function toSlug(string $string, ?array $meta = null): string
    {
        $string = \mb_strtolower($string);

        if ($meta) {
            \ksort($meta);
            $parts = [];

            foreach ($meta as $name => $val) {
                $parts[] = $name . ':' . $val;
            }

            $string .= ',' . \implode(',', $parts);
        }

        $string = \strtr($string, [' ' => '-', '-' => '--']);

        $string = \preg_replace_callback(
            '/([^\x21\x22\x24\x27-\x2e\x30-\x3e\x40-\x7E])/u',
            static function ($char): string {
                // mb_ord() is undocumented, but apparently it's a multi-byte
                // version of ord() which only handles one byte.
                return '--' . \dechex(\mb_ord($char[1]));
            },
            $string,
        );

        return $string;
    }
}
