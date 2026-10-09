<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Supplier party details for e-invoices.
        Schema::table('branches', function (Blueprint $table) {
            $table->string('company_name')->nullable()->after('name');
            $table->string('tin')->nullable();
            $table->string('brn')->nullable();
            $table->string('sst_no')->nullable();
            $table->string('msic_code')->default('47731');
            $table->string('email')->nullable();
            $table->string('postcode')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
        });

        // Buyer details for individual e-invoices.
        Schema::table('customers', function (Blueprint $table) {
            $table->string('tin')->nullable();
            $table->string('brn')->nullable();
            $table->string('email')->nullable();
        });

        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained();
            $table->foreignId('user_id')->constrained();
            $table->unsignedBigInteger('opening_float_sen');
            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();
            $table->bigInteger('expected_cash_sen')->nullable();
            $table->bigInteger('counted_cash_sen')->nullable();
            $table->string('note')->nullable();
            $table->index(['user_id', 'closed_at']);
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->foreignId('shift_id')->nullable()->after('branch_id')->constrained();
        });

        Schema::table('sale_lines', function (Blueprint $table) {
            $table->unsignedInteger('refunded_qty')->default(0);
        });

        Schema::table('prescriptions', function (Blueprint $table) {
            $table->foreignId('branch_id')->nullable()->after('id')->constrained();
        });

        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained();
            $table->foreignId('branch_id')->constrained();
            $table->foreignId('shift_id')->nullable()->constrained();
            $table->foreignId('user_id')->constrained();
            $table->string('number')->unique();
            $table->unsignedBigInteger('amount_sen');
            $table->string('reason');
            $table->timestamps();
        });

        Schema::create('refund_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('refund_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sale_line_id')->constrained();
            $table->unsignedInteger('qty');
            $table->unsignedBigInteger('amount_sen');
        });

        Schema::create('customer_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained();
            $table->foreignId('branch_id')->constrained();
            $table->foreignId('shift_id')->nullable()->constrained();
            $table->foreignId('user_id')->constrained();
            $table->unsignedBigInteger('amount_sen');
            $table->string('method');
            $table->string('reference')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained();
            $table->foreignId('supplier_id')->constrained();
            $table->string('number')->unique();
            $table->string('status')->default('draft');
            $table->date('expected_on')->nullable();
            $table->string('note')->nullable();
            $table->foreignId('user_id')->constrained();
            $table->timestamps();
        });

        Schema::create('purchase_order_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained();
            $table->unsignedInteger('qty');
            $table->unsignedInteger('cost_sen');
        });

        Schema::table('goods_receipts', function (Blueprint $table) {
            $table->foreignId('purchase_order_id')->nullable()->after('supplier_id')->constrained();
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->string('reason')->nullable()->after('type');
        });

        Schema::create('consolidated_einvoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained();
            $table->string('period', 7);
            $table->unsignedInteger('sale_count');
            $table->unsignedBigInteger('subtotal_sen');
            $table->unsignedBigInteger('tax_sen');
            $table->unsignedBigInteger('total_sen');
            $table->timestamps();
            $table->unique(['branch_id', 'period']);
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained();
            $table->foreignId('user_id')->nullable()->constrained();
            $table->string('action')->index();
            $table->nullableMorphs('subject');
            $table->json('data')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('consolidated_einvoices');
        Schema::table('stock_movements', fn (Blueprint $t) => $t->dropColumn('reason'));
        Schema::table('goods_receipts', fn (Blueprint $t) => $t->dropConstrainedForeignId('purchase_order_id'));
        Schema::dropIfExists('purchase_order_lines');
        Schema::dropIfExists('purchase_orders');
        Schema::dropIfExists('customer_payments');
        Schema::dropIfExists('refund_lines');
        Schema::dropIfExists('refunds');
        Schema::table('prescriptions', fn (Blueprint $t) => $t->dropConstrainedForeignId('branch_id'));
        Schema::table('sale_lines', fn (Blueprint $t) => $t->dropColumn('refunded_qty'));
        Schema::table('sales', fn (Blueprint $t) => $t->dropConstrainedForeignId('shift_id'));
        Schema::dropIfExists('shifts');
        Schema::table('customers', fn (Blueprint $t) => $t->dropColumn(['tin', 'brn', 'email']));
        Schema::table('branches', fn (Blueprint $t) => $t->dropColumn(['company_name', 'tin', 'brn', 'sst_no', 'msic_code', 'email', 'postcode', 'city', 'state']));
    }
};
