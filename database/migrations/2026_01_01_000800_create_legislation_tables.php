<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ordinances', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('document_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('ordinance_number', 60)->unique();
            $table->unsignedSmallInteger('series_year')->index();
            $table->string('title');
            $table->text('purpose')->nullable();
            $table->string('status', 30)->default('enacted')->index();

            $table->date('enacted_on')->nullable();
            $table->string('approving_authority')->nullable();
            $table->date('approved_on')->nullable();
            $table->date('vetoed_on')->nullable();
            $table->date('veto_overridden_on')->nullable();
            $table->date('effectivity_date')->nullable();
            $table->date('publication_date')->nullable();
            $table->string('publication_medium')->nullable();

            $table->ulid('amends_ordinance_id')->nullable();
            $table->ulid('repealed_by_ordinance_id')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });

        // Self-referencing keys are added after the table exists so the
        // primary key they point at is already in place.
        Schema::table('ordinances', function (Blueprint $table) {
            $table->foreign('amends_ordinance_id')->references('id')->on('ordinances')->nullOnDelete();
            $table->foreign('repealed_by_ordinance_id')->references('id')->on('ordinances')->nullOnDelete();
        });

        Schema::create('resolutions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('document_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('resolution_number', 60)->unique();
            $table->unsignedSmallInteger('series_year')->index();
            $table->string('title');
            $table->text('purpose')->nullable();
            $table->string('category', 40)->nullable();
            $table->string('status', 30)->default('adopted')->index();

            $table->date('adopted_on')->nullable();
            $table->date('effectivity_date')->nullable();
            $table->date('transmitted_on')->nullable();
            $table->string('transmitted_to')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resolutions');
        Schema::dropIfExists('ordinances');
    }
};
