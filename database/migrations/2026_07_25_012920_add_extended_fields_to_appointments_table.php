<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasColumn('appointments', 'reported_by')) {
            return;
        }

        Schema::table('appointments', function (Blueprint $table) {
            $table->foreignId('reported_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
            $table->enum('notification_level', ['low', 'medium', 'high', 'urgent'])->default('low')->after('reported_by');

            // appointment_time (existing) is "Begin Time" — add its counterpart.
            $table->time('end_time')->nullable()->after('appointment_time');

            // healthcare_provider_id (existing) is "Consultant" — add its counterpart.
            $table->foreignId('hospital_provider_id')->nullable()->after('healthcare_provider_id')->constrained('healthcare_providers')->nullOnDelete();
            $table->string('other_provider')->nullable()->after('hospital_provider_id');
            $table->string('specialty')->nullable()->after('other_provider');

            $table->enum('location_type', ['home', 'hospital', 'office', 'telemedicine', 'other'])->nullable()->after('specialty');
            $table->string('address_street1')->nullable()->after('location_type');
            $table->string('address_street2')->nullable()->after('address_street1');
            $table->string('address_city')->nullable()->after('address_street2');
            $table->string('address_state')->nullable()->after('address_city');
            $table->string('address_zip')->nullable()->after('address_state');
            $table->string('address_country')->nullable()->after('address_zip');
            $table->decimal('latitude', 10, 7)->nullable()->after('address_country');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');

            $table->string('phone')->nullable()->after('longitude');
            $table->string('phone_extension')->nullable()->after('phone');
            $table->string('driver')->nullable()->after('phone_extension');
            $table->string('pick_up_at')->nullable()->after('driver');
            $table->time('depart_time')->nullable()->after('pick_up_at');

            $table->enum('reason_for_appointment', ['routine_checkup', 'follow_up', 'specialist_consult', 'emergency', 'other'])
                ->nullable()->after('depart_time');

            // description (existing) = "Description", notes (existing) = "Comments".
            $table->text('confidential_note')->nullable()->after('reason_for_appointment');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reported_by');
            $table->dropConstrainedForeignId('hospital_provider_id');
            $table->dropColumn([
                'notification_level', 'end_time', 'other_provider', 'specialty',
                'location_type', 'address_street1', 'address_street2', 'address_city',
                'address_state', 'address_zip', 'address_country', 'latitude', 'longitude',
                'phone', 'phone_extension', 'driver', 'pick_up_at', 'depart_time',
                'reason_for_appointment', 'confidential_note',
            ]);
        });
    }
};
