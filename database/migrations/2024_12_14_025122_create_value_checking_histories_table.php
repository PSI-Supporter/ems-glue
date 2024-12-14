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
        Schema::create('value_checking_histories', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->string('code', 21)->nullable();
            $table->string('item_code', 50);
            $table->string('doc_code', 50);
            $table->float('quantity', 12, 3);
            $table->string('lot_code', 50);
            $table->string('item_value', 15);
            $table->string('checking_status', 2);
            $table->string('created_by', 9);
            $table->string('client_ip', 50)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('value_checking_histories');
    }
};
