<?php

namespace App\Exceptions;

use RuntimeException;

class OutstandingStudentBills extends RuntimeException
{
    public function __construct(public readonly array $studentNames)
    {
        parent::__construct('Siswa masih memiliki tunggakan.');
    }
}
