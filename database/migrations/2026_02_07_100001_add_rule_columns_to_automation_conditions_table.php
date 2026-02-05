<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('automation_conditions', function (Blueprint $table) {
            $table->unsignedSmallInteger('position')->default(0)->after('automation_id');
            $table->string('field_type', 20)->nullable()->after('position'); // 'attribute' | 'custom'
            $table->string('field_key', 50)->nullable()->after('field_type'); // 'name' | 'email' | 'phone' (when attribute)
            $table->unsignedBigInteger('contact_field_id')->nullable()->after('field_key');
            $table->string('operator', 30)->nullable()->after('contact_field_id'); // equals, not_equals, contains, is_empty, is_not_empty
            $table->string('value', 500)->nullable()->after('operator');

            $table->foreign('contact_field_id')->references('id')->on('contact_fields')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('automation_conditions', function (Blueprint $table) {
            $table->dropForeign(['contact_field_id']);
            $table->dropColumn(['position', 'field_type', 'field_key', 'contact_field_id', 'operator', 'value']);
        });
    }
};
