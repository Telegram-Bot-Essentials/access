<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Essence 0.19 names what a handler needs an ability (a Laravel Gate
 * ability), so a role holds abilities. Safe to run again.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('access_roles', 'permissions')) {
            Schema::table('access_roles', function (Blueprint $table) {
                $table->renameColumn('permissions', 'abilities');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('access_roles', 'abilities')) {
            Schema::table('access_roles', function (Blueprint $table) {
                $table->renameColumn('abilities', 'permissions');
            });
        }
    }
};
