<?php

namespace App\Http\Requests;

class ShareFilesRequest extends FilesActionRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'email' => 'required|email',
        ]);
    }
}
