<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Model Factories
|--------------------------------------------------------------------------
|
| Here you may define all of your model factories. Model factories give
| you a convenient way to create models for testing and seeding your
| database. Just tell the factory how a default model should look.
|
*/

$factory->define(Appocular\Assessor\Models\Batch::class, static function (Faker\Generator $faker) {
    return [
        'snapshot_id' => $faker->sha256,
    ];
});

$factory->define(Appocular\Assessor\Models\Snapshot::class, static function (Faker\Generator $faker) {
    return [
        'id' => $faker->sha256,
        'status' => 'unknown',
        'processing_status' => 'pending',
        'run_status' => 'pending',
        'repo_id' => 'none',
    ];
});

$factory->define(Appocular\Assessor\Models\Checkpoint::class, static function (Faker\Generator $faker) {
    return [
        // ID is most likely a sha1, as used by Git.
        'id' => $faker->sha1,
        'name' => $faker->text(20),
        'image_url' => $faker->sha256,
        'baseline_url' => $faker->sha256,
        'diff_url' => $faker->sha256,
        'image_status' => 'available',
        'diff_status' => 'unknown',
        'approval_status' => 'unknown',
        'meta' => null,
    ];
});

// phpcs:ignore SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
$factory->define(Appocular\Assessor\Models\History::class, static function (Faker\Generator $faker) {
    return [
        'history' => '',
    ];
});

$factory->define(Appocular\Assessor\Models\Repo::class, static function (Faker\Generator $faker) {
    return [
        'uri' => $faker->text(20),
        'api_token' => $faker->sha256,
    ];
});
