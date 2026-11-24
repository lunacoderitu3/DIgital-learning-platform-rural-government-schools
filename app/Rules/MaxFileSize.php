<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

class MaxFileSize implements ValidationRule
{
    protected $maxSize;

    public function __construct($maxSizeInMB)
    {
        $this->maxSize = $maxSizeInMB * 1024 * 1024; // Convert MB to bytes
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Not an uploaded file, other rules are responsible for that case.
        if (!$value instanceof UploadedFile) {
            return;
        }

        if ($value->getSize() > $this->maxSize) {
            $fail('The file size must be less than ' . ($this->maxSize / (1024 * 1024)) . 'MB.');
        }
    }
}
