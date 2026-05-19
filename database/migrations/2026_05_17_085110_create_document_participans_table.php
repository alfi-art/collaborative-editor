<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('document_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained();
            $table->timestamp('last_active_at')->nullable();
            $table->string('cursor_color', 7)->default('#000000');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('document_participants');
    }
};