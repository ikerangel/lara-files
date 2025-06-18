<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('files', function (Blueprint $table) {
            $table->id();

            // core identity
            $table->string('path')->unique();           // always stored *relative* to /PLANOS
            $table->string('name');                     // filename or dirname (no extension)
            $table->enum('file_type', ['file','directory']);
            $table->string('extension')->nullable();    // null for directories

            // quick-search helpers
            $table->string('revision')->nullable();     // extracted “…_revX”, “_A”, …
            $table->string('part_name')->nullable();    // name without revision / extension
            $table->string('product_main_type')->nullable(); // first-level folder
            $table->string('product_sub_type')->nullable();  // next level (comma-joined if >1)

            // hierarchy / metadata
            $table->string('parent')->nullable();       // parent *path* (faster than FK)
            $table->string('parent_path')->nullable();
            $table->unsignedTinyInteger('depth');       // 0 = root, 1 = MAIN-TYPE, …
            $table->string('origin');                   // initial | real-time | reconciled
            $table->string('content_hash')->nullable(); // sha256 | md5 (big files)
            $table->unsignedBigInteger('size')->nullable();
            $table->timestamp('modified_at')->nullable();

            $table->timestamps();

            // indices for the obvious look-ups
            $table->index('product_main_type');
            $table->index('part_name');
            $table->index('content_hash');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('files');
    }
};
