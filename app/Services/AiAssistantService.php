<?php

namespace App\Services;

class AiAssistantService
{
    public function ask(string $prompt): string
    {
        if (! config('services.ai.enabled', false)) {
            return 'AI belum diaktifkan. Prompt tersimpan sebagai draft dan bisa diproses setelah provider dikonfigurasi.';
        }

        return 'Provider AI belum dikonfigurasi untuk environment ini.';
    }
}
