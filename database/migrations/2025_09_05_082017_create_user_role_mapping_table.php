<?php

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
        Schema::create('user_role_mapping', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('role_id');
            $table->timestamps();
            
            $table->unique(['user_id', 'role_id'], 'user_role_mapping_user_id_role_id_unique');
            $table->index('user_id', 'idx_user_role_user');
            $table->index('role_id', 'idx_user_role_role');
            
            $table->foreign('user_id', 'user_role_mapping_user_id_foreign')
                  ->references('id')
                  ->on('users')
                  ->onUpdate('restrict')
                  ->onDelete('cascade');
                  
            $table->foreign('role_id', 'user_role_mapping_role_id_foreign')
                  ->references('id')
                  ->on('roles')
                  ->onUpdate('restrict')
                  ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_role_mapping');
    }
};
