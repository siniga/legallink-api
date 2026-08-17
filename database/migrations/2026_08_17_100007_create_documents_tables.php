<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('firm_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('documents')->nullOnDelete();
            $table->boolean('is_folder')->default(false);
            $table->string('name');
            $table->string('kind');
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('case_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('visibility')->default('private');
            $table->string('disk')->nullable();
            $table->string('path')->nullable();
            $table->string('original_name')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['firm_id', 'parent_id']);
            $table->index(['firm_id', 'client_id']);
            $table->index(['firm_id', 'case_id']);
            $table->index(['firm_id', 'owner_id']);
        });

        Schema::create('document_access', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('access')->default('viewer');
            $table->timestamps();

            $table->unique(['document_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_access');
        Schema::dropIfExists('documents');
    }
};
