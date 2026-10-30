<?php

namespace App\Models;

use App\Enums\ClientDocumentCategory;
use App\Models\Scopes\ActiveParentScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A document attached to a client (brief, contract, details...): either a file stored on the private disk and served
 * only through the authorized routes, or a shared cloud link (`url`, http/https only). For links `original_name` holds the title.
 */
class ClientDocument extends Model
{
    use SoftDeletes;

    /** Types a browser can display safely inline (no HTML/SVG). Everything else is downloaded. */
    public const PREVIEWABLE = ['application/pdf', 'image/png', 'image/jpeg', 'image/gif', 'image/webp', 'text/plain'];

    protected $fillable = ['category', 'description'];

    protected $hidden = ['stored_path'];

    protected function casts(): array
    {
        return ['category' => ClientDocumentCategory::class];
    }

    protected static function booted(): void
    {
        // Hidden while the client is deleted; back when it is restored.
        static::addGlobalScope('active-client', new ActiveParentScope('client_id', 'clients'));
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class)->withTrashed();
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by')->withTrashed();
    }

    public function isLink(): bool
    {
        return $this->url !== null && $this->url !== '';
    }

    public function isPreviewable(): bool
    {
        return ! $this->isLink() && in_array($this->mime_type, self::PREVIEWABLE, true);
    }

    /** Host of a link document without "www.", e.g. drive.google.com. */
    public function host(): ?string
    {
        if (! $this->isLink()) {
            return null;
        }
        $host = parse_url($this->url, PHP_URL_HOST);

        return is_string($host) ? preg_replace('/^www\./i', '', strtolower($host)) : null;
    }

    /** Only http(s) URLs are ever stored; this re-checks before the value reaches an href. */
    public function safeUrl(): ?string
    {
        return $this->isLink() && preg_match('#^https?://#i', $this->url) ? $this->url : null;
    }

    public function humanSize(): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $size = (float) $this->size;
        $i = 0;
        while ($size >= 1024 && $i < 3) {
            $size /= 1024;
            $i++;
        }

        return round($size, 1).' '.$units[$i];
    }

    public function extension(): string
    {
        return strtoupper(pathinfo($this->original_name, PATHINFO_EXTENSION) ?: 'file');
    }
}
