<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create($this->table(), function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('form_key')->index();
            // The schema slug/version the payload validated against (nullable — the
            // base store performs no validation and does not require one).
            $table->string('schema_ref')->nullable();
            $table->json('payload');
            // Arrival context: site/tenant/url/ip/user-agent — whatever the caller
            // chose to record. The store imposes no shape on it.
            $table->json('context')->nullable();
            $table->uuid('user_id')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists($this->table());
    }

    private function table(): string
    {
        return config('schema-forms.table_names.form_submissions', 'form_submissions');
    }
};
