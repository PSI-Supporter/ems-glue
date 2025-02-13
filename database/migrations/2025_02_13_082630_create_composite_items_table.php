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
        Schema::create('composite_items', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->string('item_code_main', 50);
            $table->string('item_code_sub', 50);
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
        Schema::dropIfExists('composite_items');
    }
};
