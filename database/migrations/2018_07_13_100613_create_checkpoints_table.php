<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

// phpcs:ignore PSR1.Classes.ClassDeclaration.MissingNamespace
class CreateCheckpointsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('checkpoints', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name', 255);
            $table->string('snapshot_id');
            $table->string('image_url')->nullable();
            $table->string('baseline_url')->nullable();
            $table->string('diff_url')->nullable();
            $table->string('status')->default('unknown');
            $table->string('diff_status')->default('unknown');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('check');
    }
}
