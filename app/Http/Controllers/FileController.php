<?php

namespace App\Http\Controllers;

use App\Http\Requests\AddToFavouritesRequest;
use App\Http\Requests\FilesActionRequest;
use App\Http\Requests\StoreFileRequest;
use App\Http\Requests\StoreFolderRequest;
use App\Http\Requests\TrashFilesRequest;
use App\Http\Resources\FileResource;
use App\Models\File;
use App\Models\StarredFile;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use ZipArchive;

class FileController extends Controller
{
    private bool $_skipEmptyFolders = true;

    public function myFiles(Request $request, string $folder = null)
    {
        if ($folder) {
            $folder = File::query()
                ->where('created_by', Auth::id())
                ->where('path', $folder)
                ->firstOrFail();
        }
        if (!$folder) {
            $folder = $this->getRoot();
        }

        $favourites = (int)$request->get('favourites');

        $query = File::query()
            ->select('files.*')
            ->with('starred')
            ->where('created_by', Auth::id())
            ->where('parent_id', $folder->id)
            ->where('_lft', '!=', 1)
            ->orderByDesc('is_folder')
            ->orderByRaw('LENGTH(name), name')
            ->orderByDesc('files.created_at')
            ->orderByDesc('files.id');

        if ($favourites === 1) {
            $query->join('starred_files', 'files.id', '=', 'starred_files.file_id')
                ->where('starred_files.user_id', Auth::id());
        }

        $files = $query->paginate(10);

        $files = FileResource::collection($files);
        if ($request->wantsJson()) {
            return $files;
        }

        $ancestors = FileResource::collection([... $folder->ancestors, $folder]);
        $folder = new FileResource($folder);

        return Inertia::render('MyFiles', compact('files', 'folder', 'ancestors'));
    }

    public function trash(Request $request)
    {
        $query = File::onlyTrashed()
            ->where('created_by', Auth::id())
            ->orderByDesc('is_folder')
            ->orderByDesc('deleted_at')
            ->orderByDesc('files.id');

        $files = $query->paginate(10);
        $files = FileResource::collection($files);

        if ($request->wantsJson()) {
            return $files;
        }

        return Inertia::render('Trash', compact('files'));
    }

    public function restore(TrashFilesRequest $request)
    {
        $data = $request->validated();
        if ($data['all']) {
            $children = File::onlyTrashed()->get();
        } else {
            $ids = $data['ids'] ?? [];
            $children = File::onlyTrashed()->whereIn('id', $ids)->get();
        }
        foreach ($children as $child) {
            /** @var File $child */
            $child->restore();
        }
        return to_route('trash');
    }

    public function deleteForever(TrashFilesRequest $request)
    {
        $data = $request->validated();
        if ($data['all']) {
            $children = File::onlyTrashed()->get();
        } else {
            $ids = $data['ids'] ?? [];
            $children = File::onlyTrashed()->whereIn('id', $ids)->get();
        }
        foreach ($children as $child) {
            /** @var File $child */
            $child->deleteForever();
        }
        return to_route('trash');
    }

    public function createFolder(StoreFolderRequest $request)
    {
        $data = $request->validated();
        $parent = $request->parent ?? $this->getRoot();

        $file = new File();
        $file->is_folder = 1;
        $file->name = $data['name'];

        $parent->appendNode($file);
    }

    public function store(StoreFileRequest $request)
    {
        $data = $request->validated(); // ['parent_id', 'folder_name', 'files',]
        $parent = $request->parent ?? $this->getRoot();
        $user = $request->user();
        $fileTree = $request->file_tree;  // request includes also 'file_paths', 'file_tree' keys

        if (!empty($fileTree)) {
            // folder(s) upload
            $this->saveFileTree($fileTree, $parent, $user);
        } else {
            // files upload
            foreach ($data['files'] as $file) {
                /** @var UploadedFile $file */
                $this->saveFile($file, $user, $parent);
            }
        }
    }

    public function destroy(FilesActionRequest $request)
    {
        $data = $request->validated();
        /** @var File $parent */
        $parent = $request->parent;

        if ($data['all']) {
            $children = $parent->children;
            foreach ($children as $child) {
                /** @var File $child */
                $child->moveToTrash();
            }
        } else {
            foreach ($data['ids'] ?? [] as $id) {
                $file = File::find($id);
                /** @var File $file */
                $file?->moveToTrash();
            }
        }
        return to_route('myFiles', ['folder' => $parent->path]);
    }

    public function download(FilesActionRequest $request): array
    {
        $data = $request->validated();
        /** @var File $parent */
        $parent = $request->parent;

        $all = $data['all'] ?? false;
        $ids = $data['ids'] ?? [];

        if (!$all && empty($ids)) {
            return [
                'message' => 'Please select files to download',
            ];
        }
        if ($all) {
            [$url, $filesAdded] = $this->createZip($parent->children);
            $filename = $parent->name . '.zip';
        } else {
            if (count($ids) === 1) {
                $file = File::find($ids[0]);
                if ($file->is_folder) {
                    if ($file->children->count() === 0) {
                        return [
                            'message' => "The folder '{$file->name}' is empty",
                        ];
                    }
                    [$url, $filesAdded] = $this->createZip($file->children);
                    $filename = $file->name . '.zip';
                } else {
                    $dest = 'public/' . pathinfo($file->storage_path, PATHINFO_BASENAME);
                    Storage::copy($file->storage_path, $dest);

                    $filesAdded = 1;
                    $url = asset(Storage::url($dest));
                    $filename = $file->name;
                }
            } else {
                $files = File::query()->whereIn('id', $ids)->get();
                [$url, $filesAdded] = $this->createZip($files);
                $filename = $parent->name . '.zip';
            }
        }
        if ($filesAdded === 0 && $this->_skipEmptyFolders) {
            return [
                'message' => "There are no files in selected folders",
            ];
        }
        return compact('url', 'filename', 'filesAdded');
    }

    public function addToFavourites(AddToFavouritesRequest $request)
    {
        $data = $request->validated();
        $id = $data['id'] ?? null;

        if (empty($id)) {
            return [
                'message' => 'Please select file to add to favourites',
            ];
        }

        $file = File::find($id);
        $userId = Auth::id();

        $starredFile = StarredFile::query()
            ->where('file_id', $file->id)
            ->where('user_id', $userId)
            ->first();
        if ($starredFile) {
            $starredFile->delete(); // undo favourite
        } else {
            StarredFile::create([
                'file_id' => $file->id,
                'user_id' => $userId,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);
        }

        return redirect()->back();
    }

    private function getRoot()
    {
        return File::query()->whereIsRoot()
            ->where('created_by', Auth::id())
            ->firstOrFail();
    }

    /**
     * In case of folder(s) upload
     * @param array $fileTree
     * @param File $parent
     * @param User $user
     * @return void
     * @see StoreFileRequest buildFileTree() private method
     */
    private function saveFileTree(array $fileTree, File $parent, User $user)
    {
        foreach ($fileTree as $name => $file) {
            if (is_array($file)) {
                $folder = new File();
                $folder->is_folder = 1;
                $folder->name = $name;

                $parent->appendNode($folder);
                $this->saveFileTree($file, $folder, $user);
            } else {
                $this->saveFile($file, $user, $parent);
            }
        }
    }

    /**
     * @param UploadedFile $file
     * @param User $user
     * @param File $parent
     * @return void
     */
    private function saveFile(UploadedFile $file, User $user, File $parent): void
    {
        $storage_path = $file->store('/files/' . $user->id);
        $model = new File();
        $model->storage_path = $storage_path;
        $model->is_folder = false;
        $model->name = $file->getClientOriginalName();
        $model->mime = $file->getMimeType();
        $model->size = $file->getSize();

        $parent->appendNode($model);
    }

    private function createZip(Collection $files): array
    {
        $zipPath = 'zip/' . Str::random() . '.zip';
        $publicPath = "public/$zipPath";
        if (!is_dir(dirname($publicPath))) {
            Storage::makeDirectory(dirname($publicPath));
        }

        $filesAdded = 0;
        $zipFile = Storage::path($publicPath); // absolute path to public storage
        $zip = new ZipArchive();

        if ($zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
            $filesAdded = $this->addFilesToZip($zip, $files);
        }
        $zip->close();
        return [asset(Storage::url($zipPath)), $filesAdded];
    }

    private function addFilesToZip(ZipArchive $zip, Collection $files, string $ancestors = ''): int
    {
        static $filesAdded = 0;
        foreach ($files as $file) {
            if ($file->is_folder) {
                if ($file->children->count() > 0) {
                    $this->addFilesToZip($zip, $file->children, $ancestors . $file->name . '/');
                } else if (!$this->_skipEmptyFolders) {
                    $zip->addEmptyDir($file->name);
                }
            } else {
                $zip->addFile(Storage::path($file->storage_path), $ancestors . $file->name);
                $filesAdded++;
            }
        }
        return $filesAdded;
    }
}
