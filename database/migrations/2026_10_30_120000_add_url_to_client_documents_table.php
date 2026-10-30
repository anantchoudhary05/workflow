<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Client documents can now be a shared cloud link (Google Drive, OneDrive, Dropbox...) instead of an uploaded file.
 * A link document has `url` set and no stored file, so the file columns become nullable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_documents', function (Blueprint $table) {
            $table->string('url', 2048)->nullable()->after('stored_path');
            $table->string('stored_path')->nullable()->change();
            $table->string('mime_type', 120)->nullable()->change();
            $table->unsignedBigInteger('size')->nullable()->change();
        });
    }

    /**
     * Rolling back keeps link documents as rows (title, category, description) but drops their URL,
     * because the earlier schema has no place for it. File documents are unchanged.
     */
    public function down(): void
    {
        DB::table('client_documents')->whereNotNull('url')
            ->update(['stored_path' => '', 'mime_type' => 'text/uri-list', 'size' => 0]);

        Schema::table('client_documents', function (Blueprint $table) {
            $table->dropColumn('url');
            $table->string('stored_path')->nullable(false)->change();
            $table->string('mime_type', 120)->nullable(false)->change();
            $table->unsignedBigInteger('size')->nullable(false)->change();
        });
    }
};
