<?php

namespace App\Services;

class AiAssistantService
{
    /** @return array{enabled: bool, answer: string} */
    public function ask(string $prompt): array
    {
        if (! config('services.ai.enabled', false)) {
            return [
                'enabled' => false,
                'answer' => 'AI belum diaktifkan. Prompt tersimpan sebagai draft dan bisa diproses setelah provider dikonfigurasi.',
            ];
        }

        return [
            'enabled' => false,
            'answer' => 'Provider AI belum dikonfigurasi untuk environment ini.',
        ];
    }
}
