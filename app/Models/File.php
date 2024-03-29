<?php

namespace App\Models;

use App\Traits\HasCreatorAndUpdater;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Kalnoy\Nestedset\Collection;
use Kalnoy\Nestedset\NodeTrait;

class File extends Model
{
    use HasFactory, HasCreatorAndUpdater, NodeTrait, SoftDeletes;

    protected $appends = ['folder'];

    public function getFolderAttribute()
    {
        return $this->is_folder ? $this->name : pathinfo($this->path, PATHINFO_DIRNAME);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(__CLASS__, 'parent_id');
    }

    public function starred()
    {
        return $this->hasOne(StarredFile::class, 'file_id', 'id')
            ->where('user_id', Auth::id());
    }

    public function owner(): Attribute
    {
        return Attribute::make(
            get: function(mixed $value, array $attributes) {
                return $attributes['created_by'] == Auth::id() ? 'me' : $this->user->name;
            }
        );
    }

    public function isOwnedBy($userId): bool
    {
        return $this->created_by == $userId;
    }

    public function isRoot()
    {
        return $this->parent_id === null;
    }

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (!$model->parent) {
                return;
            }
            $model->path = (!$model->parent->isRoot() ? $model->parent->path . '/' : '') . Str::slug($model->name);
        });
    }

    public function moveToTrash(): bool
    {
        $this->deleted_at = Carbon::now();
        return $this->save();
    }

    public function deleteForever(): bool
    {
        $this->deleteFilesFromStorage([$this]);
        // if item is shared with some user(s), or item is starred - its reference will be deleted by FK onDelete trigger
        return $this->forceDelete();
    }

    private function deleteFilesFromStorage(array|Collection $files): void
    {
        foreach ($files as $file) {
            if ($file->is_folder) {
                $this->deleteFilesFromStorage($file->children);
            } else {
                Storage::delete($file->storage_path);
            }
        }
    }

    public static function getSharedWithMe(): Builder
    {
        return self::query()
            ->select('files.*')
            ->join('file_shares', 'files.id', '=', 'file_shares.file_id')
            ->where('file_shares.user_id', Auth::id())
            ->orderByDesc('file_shares.created_at')
            ->orderByDesc('files.id');
    }

    public static function getSharedByMe(): Builder
    {
        return self::query()
            ->select(['files.*', 'file_shares.user_id as shared_with'])
            ->join('file_shares', 'files.id', '=', 'file_shares.file_id')
            ->where('files.created_by', Auth::id())
            ->orderByDesc('file_shares.created_at')
            ->orderByDesc('files.id');
    }
}
