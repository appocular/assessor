<?php

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

$factory->define(Appocular\Assessor\Batch::class, function (Faker\Generator $faker) {
    return [
        'snapshot_id' => $faker->sha256,
    ];
});

$factory->define(Appocular\Assessor\Snapshot::class, function (Faker\Generator $faker) {
    return [
        'id' => $faker->sha256,
        'status' => 'unknown',
        'repo_id' => 'none',
    ];
});

$factory->define(Appocular\Assessor\Checkpoint::class, function (Faker\Generator $faker) {
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

$factory->define(Appocular\Assessor\History::class, function (Faker\Generator $faker) {
    return [
        'history' => '',
    ];
});

$factory->define(Appocular\Assessor\Repo::class, function (Faker\Generator $faker) {
    return [
        'uri' => $faker->text(20),
        'api_token' => $faker->sha256,
    ];
});
