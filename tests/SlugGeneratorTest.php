<?php

declare(strict_types=1);

use Appocular\Assessor\SlugGenerator;
use PHPUnit\Framework\TestCase;

// phpcs:ignore PSR1.Classes.ClassDeclaration.MissingNamespace
class SlugGeneratorTest extends TestCase
{
    public function testGeneration(): void
    {
        $this->assertEquals('frontpage', SlugGenerator::toSlug('Frontpage'));
        // Escape troublesome characters.
        $this->assertEquals('a-checkpoint--3f', SlugGenerator::toSlug('A checkpoint?'));
        $this->assertEquals('a-checkpoint--26', SlugGenerator::toSlug('A checkpoint&'));
        // Escape the dash so our encoding doesn't clash with a name that
        // include something that looks like our encoding.
        $this->assertEquals('a-checkpoint----3f', SlugGenerator::toSlug('A checkpoint--3f'));
        // Should handle UTF-8 characters too.
        $this->assertEquals('a-checkpoint--203d', SlugGenerator::toSlug('A checkpoint‽'));
    }

    public function testGenerationWithMeta(): void
    {
        $this->assertEquals('frontpage,a:b,c:d', SlugGenerator::toSlug('Frontpage', ['c' => 'd', 'a' => 'b']));
    }
}
