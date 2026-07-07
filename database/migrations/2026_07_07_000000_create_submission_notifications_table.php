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
            // The submission this notification is about. Nullable so a deleted
            // submission leaves its send-history intact.
            $table->uuid('submission_id')->nullable()->index();
            $table->string('form_key')->index();
            // A snapshot of the resolved notify intent — enough to redeliver
            // without re-reading the form schema.
            $table->string('channel')->default('mail');
            $table->string('recipient')->nullable();
            $table->string('subject')->nullable();
            $table->json('intent')->nullable();
            // Which delivery notifier attempted the send (observability).
            $table->string('notifier')->nullable();
            $table->string('status')->default('pending')->index();
            $table->unsignedInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists($this->table());
    }

    private function table(): string
    {
        return config('schema-forms.table_names.submission_notifications', 'submission_notifications');
    }
};
