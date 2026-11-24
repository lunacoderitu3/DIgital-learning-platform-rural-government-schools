<?php

namespace App\Rules;

use App\Services\UploadService;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

/**
 * Allow-list validation for uploaded files (CWE-434).
 *
 * Use it on any field that MAY contain an uploaded file, including mixed
 * fields such as the dynamic "extra_fields.*.data" inputs where the value is
 * a plain string most of the time. Non file values are passed through
 * untouched so the rule can safely be combined with 'nullable' / 'required'.
 *
 * Both the real (sniffed) mime type and the client extension must be in the
 * allow-list, so neither a renamed shell nor a spoofed Content-Type gets in.
 */
class SafeUpload implements ValidationRule
{
    public const IMAGE = 'image';
    public const DOCUMENT = 'document';
    public const MEDIA = 'media';

    /**
     * SVG is a script capable document format. Only opt in where the product
     * really needs it (logos), and keep the storage CSP header in place.
     */
    public const SVG = 'svg';

    /** mime type => allowed extensions */
    private const TYPES = [
        self::IMAGE => [
            'image/jpeg' => ['jpg', 'jpeg'],
            'image/png' => ['png'],
            'image/gif' => ['gif'],
            'image/webp' => ['webp'],
            'image/bmp' => ['bmp'],
            'image/x-icon' => ['ico'],
            'image/vnd.microsoft.icon' => ['ico'],
        ],
        self::SVG => [
            'image/svg+xml' => ['svg'],
            'image/svg' => ['svg'],
        ],
        self::DOCUMENT => [
            'application/pdf' => ['pdf'],
            'text/plain' => ['txt', 'csv'],
            'text/csv' => ['csv'],
            'application/csv' => ['csv'],
            'application/msword' => ['doc'],
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => ['docx'],
            'application/vnd.ms-excel' => ['xls', 'csv'],
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => ['xlsx'],
            'application/json' => ['json'],
            'text/json' => ['json'],
        ],
        self::MEDIA => [
            'video/mp4' => ['mp4'],
            'video/quicktime' => ['mov'],
            'video/x-msvideo' => ['avi'],
            'video/webm' => ['webm'],
            'video/x-flv' => ['flv'],
            'audio/mpeg' => ['mp3'],
            'audio/mp4' => ['m4a'],
            'audio/wav' => ['wav'],
            'audio/x-wav' => ['wav'],
        ],
    ];

    /** @var string[] */
    protected array $groups;

    protected ?int $maxSizeInMB;

    /**
     * @param string|string[] $groups one or more of the IMAGE / DOCUMENT / MEDIA constants
     * @param int|null $maxSizeInMB optional size cap in MB
     */
    public function __construct($groups = self::IMAGE, $maxSizeInMB = null)
    {
        $this->groups = (array) $groups;
        $this->maxSizeInMB = $maxSizeInMB !== null ? (int) $maxSizeInMB : null;
    }

    /**
     * The mime => extensions map for the configured groups.
     */
    private function allowed(): array
    {
        $allowed = [];
        foreach ($this->groups as $group) {
            $allowed += self::TYPES[$group] ?? [];
        }
        return $allowed;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Plain text values (or nothing at all) are not our concern.
        if (!$value instanceof UploadedFile) {
            return;
        }

        if (!$value->isValid()) {
            $fail(trans('The :attribute failed to upload.'));
            return;
        }

        $allowed = $this->allowed();
        $mime = strtolower((string) $value->getMimeType());
        $extension = strtolower((string) $value->getClientOriginalExtension());

        if (!isset($allowed[$mime])) {
            $fail(trans('The :attribute must be a file of type: :types.', [
                'types' => implode(', ', $this->allowedExtensions()),
            ]));
            return;
        }

        if (!in_array($extension, $allowed[$mime], true)) {
            $fail(trans('The :attribute has an extension that does not match its content.'));
            return;
        }

        // Never trust the two checks above alone.
        if (in_array($extension, UploadService::BLOCKED_EXTENSIONS, true)) {
            $fail(trans('The :attribute has a file type that is not allowed.'));
            return;
        }

        if ($this->maxSizeInMB !== null && $value->getSize() > $this->maxSizeInMB * 1024 * 1024) {
            $fail(trans('The file size must be less than :size MB.', ['size' => $this->maxSizeInMB]));
        }
    }

    /**
     * @return string[]
     */
    private function allowedExtensions(): array
    {
        $extensions = [];
        foreach ($this->allowed() as $list) {
            $extensions = array_merge($extensions, $list);
        }
        return array_values(array_unique($extensions));
    }
}
