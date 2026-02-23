<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['receivable', 'payable']);
            $table->morphs('contact');      // contact_type (Customer/Supplier) + contact_id
            $table->morphs('reference');    // reference_type (Invoice/PurchaseOrder) + reference_id
            $table->decimal('total_amount', 18, 2);
            $table->decimal('paid_amount', 18, 2)->default(0);
            $table->decimal('balance', 18, 2);
            $table->date('due_date')->nullable();
            $table->enum('status', ['open', 'partial', 'paid', 'overdue'])->default('open');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
