<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
    /**
     * Run the migrations.
     */
{
    public function up()
    {
        Schema::table('stored_events', function (Blueprint $table) {
            // Add custom columns to the existing stored_events table
            $table->enum('origin', ['initial', 'real-time', 'reconciled'])->after('event_class');
            $table->string('file_path')->nullable()->after('origin');
            $table->string('file_hash')->nullable()->after('file_path');
            $table->timestamp('file_modified_at')->nullable()->after('file_hash');
            $table->bigInteger('file_size')->nullable()->after('file_modified_at');
            $table->string('file_type')->nullable()->after('file_size'); // 'file' or 'directory'

            // Add indexes for better performance
            $table->index(['origin', 'created_at']);
            $table->index('file_path');
            $table->index('file_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::table('stored_events', function (Blueprint $table) {
            $table->dropIndex(['origin', 'created_at']);
            $table->dropIndex(['file_path']);
            $table->dropIndex(['file_type']);

            $table->dropColumn([
                'origin',
                'file_path',
                'file_hash',
                'file_modified_at',
                'file_size',
                'file_type'
            ]);
        });
    }
};
