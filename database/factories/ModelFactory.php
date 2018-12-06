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

$factory->define(Appocular\Assessor\Commit::class, function (Faker\Generator $faker) {
    return [
        'sha' => $faker->sha1,
    ];
});

$factory->define(Appocular\Assessor\Image::class, function (Faker\Generator $faker) {
    return [
        'id' => $faker->sha1,
        'name' => $faker->text(20),
        'image_sha' => $faker->sha1,
    ];
});
