<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('masters', function (Blueprint $table) {
            $table->id();

            // mirrors from `files`
            $table->string('path')->unique();          // absolute-to-watch root
            // slave reference
            $table->string('slave_path');              // the .pdf (or future) document
            $table->string('slave_revision')->nullable();

            $table->string('part_name')->nullable();
            $table->string('extension')->nullable();
            $table->string('master_revision')->nullable();
            $table->string('parent_path')->nullable(); // full folder path
            $table->string('content_hash')->nullable();
            $table->timestamp('modified_at')->nullable();

            $table->timestamps();

            // quick look-ups
            $table->index('parent_path');
            $table->index('content_hash');
            $table->index('part_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('masters');
    }
};
