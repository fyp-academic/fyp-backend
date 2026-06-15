<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Canonical H5P schema (mirrors the WordPress/Drupal/Moodle H5P data model).
 * Libraries and contents use auto-increment integer ids because the H5P core
 * libraries reference them numerically; they link to APES activities via the
 * `h5pContentId` stored in activity.settings.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('h5p_libraries', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('machine_name', 127)->index();
            $table->string('title', 255);
            $table->unsignedSmallInteger('major_version');
            $table->unsignedSmallInteger('minor_version');
            $table->unsignedSmallInteger('patch_version');
            $table->unsignedTinyInteger('runnable')->default(0);
            $table->unsignedTinyInteger('restricted')->default(0);
            $table->unsignedTinyInteger('fullscreen')->default(0);
            $table->string('embed_types', 255)->default('');
            $table->text('preloaded_js')->nullable();
            $table->text('preloaded_css')->nullable();
            $table->text('drop_library_css')->nullable();
            $table->longText('semantics')->nullable();
            $table->string('tutorial_url', 1023)->default('');
            $table->unsignedTinyInteger('has_icon')->default(0);
            $table->text('metadata_settings')->nullable();
            $table->text('add_to')->nullable();
            $table->timestamps();
        });

        Schema::create('h5p_libraries_libraries', function (Blueprint $table) {
            $table->unsignedBigInteger('library_id');
            $table->unsignedBigInteger('required_library_id');
            $table->string('dependency_type', 31);
            $table->primary(['library_id', 'required_library_id']);
        });

        Schema::create('h5p_libraries_languages', function (Blueprint $table) {
            $table->unsignedBigInteger('library_id');
            $table->string('language_code', 31);
            $table->longText('translation');
            $table->primary(['library_id', 'language_code']);
        });

        Schema::create('h5p_contents', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->timestamps();
            $table->uuid('user_id')->nullable();
            $table->string('title', 255)->default('');
            $table->unsignedBigInteger('library_id')->nullable();
            $table->longText('parameters')->nullable();
            $table->longText('filtered')->nullable();
            $table->string('slug', 127)->default('');
            $table->string('embed_type', 127)->default('div');
            $table->unsignedInteger('disable')->default(0);
            // Metadata fields used by the editor.
            $table->string('content_type', 127)->nullable();
            $table->string('authors', 2047)->nullable();
            $table->string('source', 2047)->nullable();
            $table->string('year_from', 31)->nullable();
            $table->string('year_to', 31)->nullable();
            $table->string('license', 31)->nullable();
            $table->string('license_version', 31)->nullable();
            $table->string('license_extras', 2047)->nullable();
            $table->string('author_comments', 2047)->nullable();
            $table->text('changes')->nullable();
            $table->string('keywords', 2047)->nullable();
            $table->text('description')->nullable();
            $table->string('default_language', 31)->nullable();
            $table->string('a11y_title', 255)->nullable();
        });

        Schema::create('h5p_contents_libraries', function (Blueprint $table) {
            $table->unsignedBigInteger('content_id');
            $table->unsignedBigInteger('library_id');
            $table->string('dependency_type', 31);
            $table->unsignedTinyInteger('drop_css');
            $table->unsignedInteger('weight');
            $table->primary(['content_id', 'library_id', 'dependency_type']);
        });

        Schema::create('h5p_libraries_cachedassets', function (Blueprint $table) {
            $table->unsignedBigInteger('library_id');
            $table->string('hash', 64);
            $table->primary(['library_id', 'hash']);
        });

        Schema::create('h5p_libraries_hub_cache', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('machine_name', 127)->index();
            $table->unsignedSmallInteger('major_version');
            $table->unsignedSmallInteger('minor_version');
            $table->unsignedSmallInteger('patch_version');
            $table->unsignedSmallInteger('h5p_major_version')->nullable();
            $table->unsignedSmallInteger('h5p_minor_version')->nullable();
            $table->string('title', 255);
            $table->text('summary')->nullable();
            $table->text('description')->nullable();
            $table->string('icon', 511)->default('');
            $table->unsignedInteger('created_at');
            $table->unsignedInteger('updated_at');
            $table->unsignedTinyInteger('is_recommended')->default(0);
            $table->unsignedInteger('popularity')->default(0);
            $table->text('screenshots')->nullable();
            $table->string('license', 2047)->nullable();
            $table->string('example', 511)->default('');
            $table->string('tutorial', 511)->nullable();
            $table->text('keywords')->nullable();
            $table->text('categories')->nullable();
            $table->string('owner', 511)->nullable();
        });

        Schema::create('h5p_options', function (Blueprint $table) {
            $table->string('name', 191)->primary();
            $table->longText('value')->nullable();
        });

        Schema::create('h5p_tmpfiles', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('path', 1023);
            $table->unsignedInteger('created_at');
            $table->index('created_at');
        });

        Schema::create('h5p_results', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('content_id')->index();
            $table->uuid('user_id')->index();
            $table->unsignedInteger('score');
            $table->unsignedInteger('max_score');
            $table->unsignedInteger('opened');
            $table->unsignedInteger('finished');
            $table->unsignedInteger('time')->nullable();
            $table->unique(['content_id', 'user_id']);
        });
    }

    public function down(): void
    {
        foreach ([
            'h5p_results', 'h5p_tmpfiles', 'h5p_options', 'h5p_libraries_hub_cache',
            'h5p_libraries_cachedassets', 'h5p_contents_libraries', 'h5p_contents',
            'h5p_libraries_languages', 'h5p_libraries_libraries', 'h5p_libraries',
        ] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
