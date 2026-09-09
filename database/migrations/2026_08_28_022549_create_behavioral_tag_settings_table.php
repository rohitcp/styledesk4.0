<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which behavioural tags a business applies.
 *
 * The tags themselves are defined in config, not here: their keys are what
 * reporting and the future rule engine join on, and a business that renamed
 * one would break every rule that referred to it. All this table records is
 * the one thing a business does decide — whether a given tag is applied at
 * all.
 *
 * A row per tag rather than a JSON column, so "switch this one off" is one
 * update rather than a read-modify-write of the whole set, and two people in
 * App Settings cannot overwrite each other's choice.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('behavioral_tag_settings', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');

            // The catalogue key, not a label: config owns the wording.
            $table->string('tag_key', 60);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'tag_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('behavioral_tag_settings');
    }
};
