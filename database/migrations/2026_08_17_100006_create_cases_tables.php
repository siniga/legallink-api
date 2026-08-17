<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('case_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('firm_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['firm_id', 'name']);
        });

        Schema::create('case_statuses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('firm_id')->constrained()->cascadeOnDelete();
            $table->string('slug');
            $table->string('name');
            $table->string('color')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_closed')->default(false);
            $table->boolean('is_archived')->default(false);
            $table->timestamps();

            $table->unique(['firm_id', 'slug']);
        });

        Schema::create('cases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('firm_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->string('case_number');
            $table->string('title');
            $table->text('description')->nullable();
            $table->foreignId('case_type_id')->nullable()->constrained('case_types')->nullOnDelete();
            $table->foreignId('case_status_id')->nullable()->constrained('case_statuses')->nullOnDelete();
            $table->string('claim_status')->nullable();
            $table->string('court')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['firm_id', 'case_number']);
            $table->index(['firm_id', 'case_status_id']);
            $table->index(['firm_id', 'court']);
        });

        Schema::create('case_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_lead')->default(false);
            $table->timestamps();

            $table->unique(['case_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('case_user');
        Schema::dropIfExists('cases');
        Schema::dropIfExists('case_statuses');
        Schema::dropIfExists('case_types');
    }
};
