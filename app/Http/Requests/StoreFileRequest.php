<?php

namespace App\Http\Requests;

use App\Models\File;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;

class StoreFileRequest extends ParentIdBaseRequest
{
    protected function prepareForValidation(): void
    {
        $paths = array_filter($this->relative_paths ?? [], fn($f) => $f != null);
        $this->merge([
            'file_paths' => $paths,
            'folder_name' => $this->detectFolderName($paths),
        ]);
    }

    protected function passedValidation()
    {
        $data = $this->validated();
        $this->replace([
            'file_tree' => $this->buildFileTree($this->file_paths, $data['files']),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return array_merge(
            parent::rules(),
            [
                'files.*' => [
                    'required',
                    'file',
                    function ($attribute, $value, $fail) {
                        if (!$this->folder_name) {
                            /** @var $value UploadedFile */
                            $file = File::query()
                                ->where('name', $value->getClientOriginalName())
                                ->where('created_by', Auth::id())
                                ->where('parent_id', $this->parent_id)
                                ->whereNull('deleted_at')
                                ->exists();
                            if ($file) {
                                $fail('File `' . $value->getClientOriginalName() . '` already exists.');
                            }
                        }
                    },
                ],
                'folder_name' => [
                    'nullable',
                    'string',
                    function ($attribute, $value, $fail) {
                        if ($value) {
                            /** @var $value string */
                            $fileOrFolder = File::query()
                                ->where('name', $value)
                                ->where('created_by', Auth::id())
                                ->where('parent_id', $this->parent_id)
                                ->whereNull('deleted_at')
                                ->exists();
                            if ($fileOrFolder) {
                                $fail('Folder `' . $value . '` already exists.');
                            }
                        }
                    },
                ]
            ]
        );
    }

    private function detectFolderName(array $paths): ?string
    {
        if (!$paths) {
            return null;
        }
        $parts = explode('/', $paths[0]);
        return $parts[0];
    }

    /**
     * Builds files tree like this:
     * [
     *      folder => [
     *          subfolder => [
     *              file.jpg
     *          ]
     *      ]
     * ]
     * @param array $filePaths - file path like 'folder/subfolder/file.jpg'
     * @param array $files
     * @return array
     */
    private function buildFileTree(array $filePaths, array $files): array
    {
        // if we have exceeded the file number upload limit, need to also equalize the file_paths to the number of files accepted by server:
        $filePaths = array_slice($filePaths, 0, count($files));
        $filePaths = array_filter($filePaths, fn($f) => $f != null);

        $tree = [];
        foreach ($filePaths as $ind => $filePath) {
            $parts = explode('/', $filePath);  // folder, subfolder, file.jpg
            $partsCount = count($parts);
            $currNode = &$tree;
            foreach ($parts as $i => $part) {
                if (!isset($currNode[$part])) {
                    $currNode[$part] = [];
                }
                if ($i === $partsCount - 1) {
                    $currNode[$part] = $files[$ind];
                } else {
                    $currNode = &$currNode[$part];
                }
            }
        }
        return $tree;
    }
}
