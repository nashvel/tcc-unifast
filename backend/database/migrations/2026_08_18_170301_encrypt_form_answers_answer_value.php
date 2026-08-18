<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Crypt;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Change to longText to accommodate encryption overhead
        Schema::table('form_answers', function (Blueprint $table) {
            $table->longText('answer_value')->nullable()->change();
        });

        // 2. Encrypt existing records
        DB::table('form_answers')->orderBy('id')->chunk(100, function ($answers) {
            foreach ($answers as $answer) {
                if ($answer->answer_value !== null) {
                    try {
                        // Skip if it's already an encrypted payload (in case of re-run)
                        $payload = json_decode(base64_decode($answer->answer_value), true);
                        if (is_array($payload) && isset($payload['iv'], $payload['value'], $payload['mac'])) {
                            continue;
                        }

                        $encrypted = Crypt::encryptString($answer->answer_value);
                        DB::table('form_answers')
                            ->where('id', $answer->id)
                            ->update(['answer_value' => $encrypted]);
                    } catch (\Exception $e) {
                        // Skip unencryptable
                    }
                }
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Decrypt records back to plain text
        DB::table('form_answers')->orderBy('id')->chunk(100, function ($answers) {
            foreach ($answers as $answer) {
                if ($answer->answer_value !== null) {
                    try {
                        $decrypted = Crypt::decryptString($answer->answer_value);
                        DB::table('form_answers')
                            ->where('id', $answer->id)
                            ->update(['answer_value' => $decrypted]);
                    } catch (\Exception $e) {
                        // Skip if not encrypted
                    }
                }
            }
        });

        // Optional: revert column back to text (though longText is safe to keep)
        Schema::table('form_answers', function (Blueprint $table) {
            $table->text('answer_value')->nullable()->change();
        });
    }
};
