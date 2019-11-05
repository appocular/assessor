<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// phpcs:ignore PSR1.Classes.ClassDeclaration.MissingNamespace
class AddProcessingStatus extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('snapshots', function (Blueprint $table) {
            $table->string('processing_status')->default('pending');
        });

        // Set all to done.
        DB::table('snapshots')->update(['processing_status' => 'done']);

        // Set waiting to done.
        DB::table('snapshots')->where('run_status', 'waiting')
            ->update(['processing_status' => 'pending']);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('snapshots', function (Blueprint $table) {
            $table->dropColumn('processing_status');
        });
    }
}
