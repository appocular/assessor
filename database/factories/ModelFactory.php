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

$factory->define(Appocular\Assessor\Snapshot::class, function (Faker\Generator $faker) {
    return [
        'id' => $faker->sha256,
        'status' => 'unknown',
        'repo_id' => 'none',
    ];
});

$factory->define(Appocular\Assessor\Checkpoint::class, function (Faker\Generator $faker) {
    return [
        'id' => $faker->sha256,
        'name' => $faker->text(20),
        'image_url' => $faker->sha256,
        'baseline_url' => $faker->sha256,
        'diff_url' => $faker->sha256,
        'status' => 'unknown',
        'diff_status' => 'unknown',
    ];
});
