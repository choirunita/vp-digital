<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pcs_accounts', function (Blueprint $table) {
            $table->id(); $table->string('bank', 100); $table->string('account_no', 100);
            $table->string('account_name'); $table->boolean('active')->default(true);
        });
        Schema::create('vendors', function (Blueprint $table) {
            $table->id(); $table->string('name'); $table->string('category', 100)->default('Vendor');
            $table->string('bank', 100); $table->string('account_no', 100); $table->string('account_name');
            $table->enum('status', ['PENDING','ACTIVE','REJECTED','INACTIVE']); $table->string('attachment_path')->nullable();
            $table->foreignId('created_by')->constrained('users'); $table->timestamp('created_at')->useCurrent();
            $table->foreignId('reviewed_by')->nullable()->constrained('users'); $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable(); $table->index(['bank','account_no']);
        });
        Schema::create('vendor_changes', function (Blueprint $table) {
            $table->id(); $table->foreignId('vendor_id')->constrained('vendors'); $table->string('name');
            $table->string('bank', 100); $table->string('account_no', 100); $table->string('account_name');
            $table->text('reason')->default(''); $table->enum('status', ['PENDING','APPROVED','REJECTED']);
            $table->foreignId('requested_by')->constrained('users'); $table->timestamp('requested_at')->useCurrent();
            $table->foreignId('reviewed_by')->nullable()->constrained('users'); $table->timestamp('reviewed_at')->nullable(); $table->text('review_note')->nullable();
        });
        Schema::create('vp_seq', function (Blueprint $table) {
            $table->unsignedSmallInteger('year'); $table->unsignedTinyInteger('month'); $table->unsignedInteger('last')->default(0); $table->primary(['year','month']);
        });
        Schema::create('vp', function (Blueprint $table) {
            $table->id(); $table->string('reg_no')->nullable()->unique();
            $table->enum('status', ['DRAFT','DIAJUKAN','DIKEMBALIKAN','DIPERIKSA','DISETUJUI','DICETAK','SELESAI','DITOLAK']); $table->enum('kind', ['SINGLE','MASS']);
            $table->foreignId('pcs_account_id')->nullable()->constrained('pcs_accounts'); $table->string('ref_no')->default(''); $table->date('pay_date')->nullable();
            $table->text('description')->default(''); $table->unsignedBigInteger('total')->default(0); $table->unsignedInteger('revision')->default(0); $table->text('return_note')->nullable();
            $table->foreignId('maker_id')->constrained('users'); $table->timestamp('created_at')->useCurrent(); $table->timestamp('submitted_at')->nullable();
            $table->foreignId('checker_id')->nullable()->constrained('users'); $table->timestamp('checked_at')->nullable();
            $table->foreignId('approver1_id')->nullable()->constrained('users'); $table->timestamp('approved_at')->nullable();
            $table->foreignId('printed_by')->nullable()->constrained('users'); $table->timestamp('printed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users'); $table->timestamp('completed_at')->nullable();
            $table->string('doc_hash', 128)->nullable(); $table->string('verify_code', 100)->nullable()->unique(); $table->index(['status','submitted_at']);
        });
        Schema::create('vp_lines', function (Blueprint $table) {
            $table->id(); $table->foreignId('vp_id')->constrained('vp')->cascadeOnDelete(); $table->foreignId('vendor_id')->constrained('vendors');
            $table->string('name'); $table->string('bank', 100); $table->string('account_no', 100); $table->string('account_name'); $table->unsignedBigInteger('amount'); $table->text('note')->default('');
        });
        Schema::create('vp_attachments', function (Blueprint $table) {
            $table->id(); $table->foreignId('vp_id')->constrained('vp')->cascadeOnDelete(); $table->string('filename'); $table->string('stored_name');
            $table->unsignedBigInteger('size'); $table->string('sha256', 128); $table->foreignId('uploaded_by')->constrained('users'); $table->timestamp('uploaded_at')->useCurrent();
        });
        Schema::create('vp_history', function (Blueprint $table) {
            $table->id(); $table->foreignId('vp_id')->constrained('vp'); $table->foreignId('user_id')->constrained('users');
            $table->string('action'); $table->string('from_status', 30)->nullable(); $table->string('to_status', 30)->nullable(); $table->text('note')->default(''); $table->timestamp('at')->useCurrent();
        });
        Schema::create('audit_log', function (Blueprint $table) {
            $table->id(); $table->timestamp('at')->useCurrent(); $table->foreignId('user_id')->nullable()->constrained('users');
            $table->string('username', 100)->nullable(); $table->string('action'); $table->string('entity', 100)->nullable();
            $table->unsignedBigInteger('entity_id')->nullable(); $table->text('detail')->default(''); $table->string('ip', 64)->nullable(); $table->index(['entity','entity_id']);
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('audit_log'); Schema::dropIfExists('vp_history'); Schema::dropIfExists('vp_attachments'); Schema::dropIfExists('vp_lines');
        Schema::dropIfExists('vp'); Schema::dropIfExists('vp_seq'); Schema::dropIfExists('vendor_changes'); Schema::dropIfExists('vendors'); Schema::dropIfExists('pcs_accounts');
    }
};
