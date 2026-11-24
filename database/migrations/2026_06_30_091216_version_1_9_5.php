<?php

use App\Models\Subscription;
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
        // In subscriptons table i want to add status column after end_date, with enum like pending, active, expired, inactive, cancelled
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->enum('status', ['pending', 'active', 'expired', 'inactive', 'cancelled'])->default('pending')->after('end_date');
        });

        // Now i want update status column value based on start_date and end_date
        $today = now()->format('Y-m-d');

        // Set all existing subscriptions to expired first
        Subscription::query()->update(['status' => 'expired']);

        // Set future subscriptions to pending
        Subscription::whereDate('start_date', '>', $today)
            ->update(['status' => 'pending']);

        // Get the latest subscription ID for each school that falls within the active date range
        $activeSubscriptionIds = Subscription::selectRaw('MAX(id) as id')
            ->whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today)
            ->groupBy('school_id')
            ->pluck('id');

        if ($activeSubscriptionIds->isNotEmpty()) {
            // Set only the latest subscription for each school to active
            Subscription::whereIn('id', $activeSubscriptionIds)
                ->update(['status' => 'active']);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
