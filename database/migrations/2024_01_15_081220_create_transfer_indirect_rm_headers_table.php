<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('transfer_indirect_rm_headers', function (Blueprint $table) {
            $table->id();
            $table->string('doc_code', 45); # contoh INDT-23-1
            $table->bigInteger('doc_order');
            $table->date('issue_date');
            $table->string('location_from', 45);
            $table->string('location_to', 45)->nullable();
            $table->timestamps();
            $table->dateTime('deleted_at')->nullable();
            $table->string('created_by', 9);
            $table->string('updated_by', 9)->nullable();
            $table->string('deleted_by', 9)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('transfer_indirect_rm_headers');
    }
};
