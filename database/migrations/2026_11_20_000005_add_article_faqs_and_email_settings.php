<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('blog_revisions', fn (Blueprint $t) => $t->json('faqs')->nullable());
        Schema::create('email_delivery_settings', function (Blueprint $t) {
            $t->id();
            $t->string('host');
            $t->unsignedSmallInteger('port');
            $t->string('encryption', 12);
            $t->string('username');
            $t->text('smtp_password');
            $t->string('from_address');
            $t->string('from_name', 100);
            $t->timestamps();
        });
        DB::table('users')->where('role', 'super_admin')->where('email', 'admin@spacekind.example')->update(['is_active' => false, 'remember_token' => null]);
    }

    public function down(): void
    {
        Schema::dropIfExists('email_delivery_settings');
        Schema::table('blog_revisions', fn (Blueprint $t) => $t->dropColumn('faqs'));
        // Never re-enable a retired shared administrator on rollback.
    }
};
