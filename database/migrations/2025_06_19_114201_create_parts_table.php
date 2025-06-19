<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parts', function (Blueprint $table) {
            $table->id();

            /* ── mirror of columns coming from FileSystemProjector ── */
            $table->string('path')->unique();           // full relative path
            $table->string('part_name');                // file-name minus revision/ext
            $table->string('parent_path')->nullable();  // folder where the file lives
            $table->string('extension', 10);            // par, asm, doc …

            $table->string('master_revision')->nullable(); // revision of the master (if any)
            $table->string('slave_path')->nullable();      // full path to the .pdf (or NULL)
            $table->string('slave_revision')->nullable();  // revision of the pdf (if any)

            $table->string('content_hash', 64)->nullable(); // sha256 or md5 (same as files table)
            $table->boolean('content_as_master')->default(false);

            $table->dateTime('modified_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parts');
    }
};
