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
        Schema::create('keikaku_access_rules', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->string('user_id', 9);
            $table->string('sheet_access', 3);
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
        Schema::dropIfExists('keikaku_access_rules');
    }
};
