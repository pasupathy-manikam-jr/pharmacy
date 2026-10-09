<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Money is stored as integer sen, quantities as integer base units (tablet, ml).
     */
    public function up(): void
    {
        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('licence_no')->nullable();
            $table->string('address')->nullable();
            $table->string('phone')->nullable();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('branch_id')->nullable()->after('id')->constrained();
        });

        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('tin')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('generic_name')->nullable()->index();
            $table->string('strength')->nullable();
            $table->string('form')->nullable();
            $table->string('poison_group')->default('none');
            $table->string('barcode')->nullable()->unique();
            $table->string('mal_reg_no')->nullable();
            $table->string('unit')->default('unit');
            $table->unsignedInteger('price_sen');
            $table->unsignedSmallInteger('tax_rate_bp')->default(0);
            $table->unsignedInteger('reorder_level')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained();
            $table->string('batch_no');
            $table->date('expiry_date')->index();
            $table->unsignedInteger('cost_sen');
            $table->foreignId('supplier_id')->nullable()->constrained();
            $table->timestamps();
            $table->unique(['product_id', 'batch_no']);
        });

        Schema::create('stock_levels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained();
            $table->foreignId('batch_id')->constrained();
            $table->integer('qty')->default(0);
            $table->timestamps();
            $table->unique(['branch_id', 'batch_id']);
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained();
            $table->foreignId('batch_id')->constrained();
            $table->integer('qty_delta');
            $table->integer('qty_after');
            $table->string('type');
            $table->nullableMorphs('reference');
            $table->string('note')->nullable();
            $table->foreignId('user_id')->nullable()->constrained();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('goods_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained();
            $table->foreignId('supplier_id')->constrained();
            $table->string('invoice_no')->nullable();
            $table->date('received_on');
            $table->unsignedBigInteger('total_sen');
            $table->string('payment_status')->default('pending');
            $table->foreignId('user_id')->constrained();
            $table->timestamps();
        });

        Schema::create('goods_receipt_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('goods_receipt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('batch_id')->constrained();
            $table->unsignedInteger('qty');
            $table->unsignedInteger('cost_sen');
        });

        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('ic_no')->nullable()->index();
            $table->date('dob')->nullable();
            $table->string('sex', 1)->nullable();
            $table->string('phone')->nullable();
            $table->string('address')->nullable();
            $table->string('citizenship')->nullable();
            $table->string('allergies')->nullable();
            $table->timestamps();
        });

        Schema::create('prescriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained();
            $table->string('prescriber_name');
            $table->string('prescriber_reg_no')->nullable();
            $table->string('clinic')->nullable();
            $table->string('diagnosis')->nullable();
            $table->date('issued_on');
            $table->unsignedSmallInteger('refills_allowed')->default(0);
            $table->timestamps();
        });

        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained();
            $table->string('number')->unique();
            $table->foreignId('customer_id')->nullable()->constrained();
            $table->foreignId('prescription_id')->nullable()->constrained();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('pharmacist_id')->nullable()->constrained('users');
            $table->unsignedBigInteger('subtotal_sen');
            $table->unsignedBigInteger('discount_sen')->default(0);
            $table->unsignedBigInteger('tax_sen')->default(0);
            $table->unsignedBigInteger('total_sen');
            $table->string('payment_method');
            $table->unsignedBigInteger('tendered_sen');
            $table->string('status')->default('completed');
            $table->timestamp('refunded_at')->nullable();
            $table->timestamps();
        });

        Schema::create('sale_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained();
            $table->foreignId('batch_id')->constrained();
            $table->unsignedInteger('qty');
            $table->unsignedInteger('price_sen');
            $table->unsignedInteger('tax_sen')->default(0);
            $table->string('dosage')->nullable();
        });

        Schema::create('poison_register_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained();
            $table->string('register')->index();
            $table->foreignId('sale_line_id')->constrained();
            $table->foreignId('product_id')->constrained();
            $table->foreignId('batch_id')->constrained();
            $table->integer('qty');
            $table->integer('balance_after');
            $table->string('customer_name');
            $table->string('customer_ic')->nullable();
            $table->string('customer_address')->nullable();
            $table->string('prescriber')->nullable();
            $table->string('dosage')->nullable();
            $table->foreignId('pharmacist_id')->constrained('users');
            $table->foreignId('reverses_id')->nullable()->constrained('poison_register_entries');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('poison_register_entries');
        Schema::dropIfExists('sale_lines');
        Schema::dropIfExists('sales');
        Schema::dropIfExists('prescriptions');
        Schema::dropIfExists('customers');
        Schema::dropIfExists('goods_receipt_lines');
        Schema::dropIfExists('goods_receipts');
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('stock_levels');
        Schema::dropIfExists('batches');
        Schema::dropIfExists('products');
        Schema::dropIfExists('suppliers');
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('branch_id');
        });
        Schema::dropIfExists('branches');
    }
};
