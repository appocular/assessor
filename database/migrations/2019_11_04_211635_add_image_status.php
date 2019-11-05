<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// phpcs:ignore PSR1.Classes.ClassDeclaration.MissingNamespace
class AddImageStatus extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Using two Schema::table calls, as doing both columns in one looses
        // image_status. Apparently the renaming causes a full table copy
        // which forgets the added column. Maybe just an issue on SOLite.
        Schema::table('checkpoints', function (Blueprint $table) {
            $table->renameColumn('status', 'approval_status');
        });

        Schema::table('checkpoints', function (Blueprint $table) {
            $table->string('image_status')->default('pending');
        });

        // Set images to available...
        DB::table('checkpoints')->update(['image_status' => 'available']);

        // ...unless they're pending or expected.
        DB::table('checkpoints')->where('approval_status', 'pending')
            ->update(['image_status' => 'pending', 'approval_status' => 'unknown']);
        DB::table('checkpoints')->where('approval_status', 'expected')
            ->update(['image_status' => 'expected', 'approval_status' => 'unknown']);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Try to reverse.
        DB::table('checkpoints')->where('approval_status', 'unknown')
            ->where('image_status', 'pending')
            ->update(['approval_status' => 'pending']);
        DB::table('checkpoints')->where('approval_status', 'unknown')
            ->where('image_status', 'expceted')
            ->update(['approval_status' => 'expceted']);

        // Staying safe and using two Schema::tables.
        Schema::table('checkpoints', function (Blueprint $table) {
            $table->dropColumn('image_status');
        });
        Schema::table('checkpoints', function (Blueprint $table) {
            $table->renameColumn('approval_status', 'status');
        });
    }
}
