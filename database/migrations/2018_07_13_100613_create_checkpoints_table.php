<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// phpcs:ignore PSR1.Classes.ClassDeclaration.MissingNamespace
class CreateCheckpointsTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('checkpoints', static function (Blueprint $table): void {
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
     */
    public function down(): void
    {
        Schema::dropIfExists('check');
    }
}
