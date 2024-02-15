<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Number;

class FileResource extends JsonResource
{
    public static $wrap = false;

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $sharedWith = null;
        if ($this->shared_with) {
            $sharedWith = User::find($this->shared_with);
        }
        return [
            'id' => $this->id,
            'name' => $this->name,
            'path' => $this->path,
            'folder' => $this->folder,
            'parent_id' => $this->parent_id,
            'is_folder' => $this->is_folder,
            'mime' => $this->mime,
            'size' => $this->size ? Number::fileSize($this->size, 2) : $this->size,
            'owner' => $this->owner,
            'owner_email' => User::find($this->created_by)->email,
            'shared_with' => $sharedWith?->name,
            'shared_with_email' => $sharedWith?->email,
            'is_favourite' => (bool) $this->starred,
            'created_at' => $this->created_at->diffForHumans(),
            'updated_at' => $this->updated_at->diffForHumans(),
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
