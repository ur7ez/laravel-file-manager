<?php

namespace App\Http\Controllers;

use App\Http\Requests\AddToFavouritesRequest;
use App\Http\Requests\FilesActionRequest;
use App\Http\Requests\ShareFilesRequest;
use App\Http\Requests\StoreFileRequest;
use App\Http\Requests\StoreFolderRequest;
use App\Http\Requests\TrashFilesRequest;
use App\Http\Resources\FileResource;
use App\Jobs\UploadFileToCloudJob;
use App\Mail\ShareFilesMail;
use App\Models\File;
use App\Models\FileShare;
use App\Models\StarredFile;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use ZipArchive;

class FileController extends Controller
{
    private bool $_skipEmptyFolders = true;

    public function myFiles(Request $request, string $folder = null)
    {
        $search = $request->get('search');
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
            ->orderByDesc('is_folder')
            ->orderByRaw('LENGTH(name), name')
            ->orderByDesc('files.created_at')
            ->orderByDesc('files.id');

        // search and filter for favourites work globally on all files (regardless of folder)
        if ($search) {
            $query
                ->where('parent_id', 'IS NOT', null) // exclude root folder
                ->where('files.name', 'like', "%$search%");
        }
        if ($favourites === 1) {
            $query->join('starred_files', 'files.id', '=', 'starred_files.file_id')
                ->where('starred_files.user_id', Auth::id());
        }
        if ($favourites !== 1 && !$search) {
            $query->where('parent_id', $folder->id);
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
        $search = $request->get('search');

        $query = File::onlyTrashed()
            ->where('created_by', Auth::id())
            ->orderByDesc('is_folder')
            ->orderByDesc('deleted_at')
            ->orderByDesc('files.id');

        if ($search) {
            $query->where('files.name', 'like', "%$search%");
        }
        $files = $query->paginate(10);
        $files = FileResource::collection($files);

        if ($request->wantsJson()) {
            return $files;
        }

        return Inertia::render('Trash', compact('files'));
    }

    public function sharedWithMe(Request $request)
    {
        $search = $request->get('search');
        $query = File::getSharedWithMe();
        if ($search) {
            $query->where('files.name', 'like', "%$search%");
        }
        $files = $query->paginate(10);
        $files = FileResource::collection($files);

        if ($request->wantsJson()) {
            return $files;
        }
        $sharedWithMe = true;
        return Inertia::render('SharedWithOrByMe', compact('files', 'sharedWithMe'));
    }

    public function sharedByMe(Request $request)
    {
        $search = $request->get('search');
        $query = File::getSharedByMe();
        if ($search) {
            $query->where('files.name', 'like', "%$search%");
        }
        $files = $query->paginate(10);
        $files = FileResource::collection($files);

        if ($request->wantsJson()) {
            return $files;
        }
        $sharedByMe = true;
        return Inertia::render('SharedWithOrByMe', compact('files', 'sharedByMe'));
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
            [$url, $filesAdded, $filename, $msg]  = $this->getDownloadUrl($ids, $parent->name);
            if ($msg) {
                return ['message' => $msg,];
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

    public function share(ShareFilesRequest $request)
    {
        $data = $request->validated();
        /** @var File $parent */
        $parent = $request->parent;

        $all = $data['all'] ?? false;
        $email = $data['email'] ?? '';
        $ids = $data['ids'] ?? [];

        if (!$all && empty($ids)) {
            return [
                'message' => 'Please select at least one file to share',
            ];
        }
        $user = User::query()->where('email', $email)->first();

        if (!$user) {
            return redirect()->back();
        }
        if ($user->id === Auth::id()) {
            throw ValidationException::withMessages([
                'email' => "You can't share files with yourself. Please specify other users' email"
            ]);
        }

        if ($all) {
            $files = $parent->children;
        } else {
            $files = File::find($ids);
        }

        $data = [];
        $ids = Arr::pluck($files, 'id');
        $sharedBefore = FileShare::query()
            ->whereIn('file_id', $ids)
            ->where('user_id', $user->id)
            ->get()
            ->keyBy('file_id');
        $sharedFiles = new Collection();
        foreach ($files as $file) {
            if ($sharedBefore->has($file->id)) {
                continue;
            }
            $data[] = [
                'file_id' => $file->id,
                'user_id' => $user->id,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ];
            $sharedFiles->add($file);
        }
        if ($data) {
            FileShare::insert($data);
            // send email to user:
            Mail::to($user)->send(new ShareFilesMail($user, Auth::user(), $sharedFiles));
        }
        return redirect()->back();
    }

    public function downloadSharedWithMe(FilesActionRequest $request): array
    {
        $data = $request->validated();

        $all = $data['all'] ?? false;
        $ids = $data['ids'] ?? [];

        if (!$all && empty($ids)) {
            return [
                'message' => 'Please select files to download',
            ];
        }
        $zipName = 'shared_with_me';
        if ($all) {
            $files = File::getSharedWithMe()->get();
            [$url, $filesAdded] = $this->createZip($files);
            $filename = $zipName . '.zip';
        } else {
            [$url, $filesAdded, $filename, $msg] = $this->getDownloadUrl($ids, $zipName);
            if ($msg) {
                return ['message' => $msg,];
            }
        }
        if ($filesAdded === 0 && $this->_skipEmptyFolders) {
            return [
                'message' => "There are no files in selected folders",
            ];
        }
        return compact('url', 'filename', 'filesAdded');
    }

    public function downloadSharedByMe(FilesActionRequest $request): array
    {
        $data = $request->validated();

        $all = $data['all'] ?? false;
        $ids = $data['ids'] ?? [];

        if (!$all && empty($ids)) {
            return [
                'message' => 'Please select files to download',
            ];
        }
        $zipName = 'shared_by_me';
        if ($all) {
            $files = File::getSharedByMe()->get();
            [$url, $filesAdded] = $this->createZip($files);
            $filename = $zipName . '.zip';
        } else {
            [$url, $filesAdded, $filename, $msg] = $this->getDownloadUrl($ids, $zipName);
            if ($msg) {
                return ['message' => $msg,];
            }
        }
        if ($filesAdded === 0 && $this->_skipEmptyFolders) {
            return [
                'message' => "There are no files in selected folders",
            ];
        }
        return compact('url', 'filename', 'filesAdded');
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
        // force save file on local storage first (in case we have set default storage on some cloud)
        $storage_path = $file->store('/files/' . $user->id, 'local');
        $model = new File();
        $model->storage_path = $storage_path;
        $model->is_folder = false;
        $model->name = $file->getClientOriginalName();
        $model->mime = $file->getMimeType();
        $model->size = $file->getSize();
        $model->uploaded_on_cloud = 0;

        $parent->appendNode($model);

        if (env('FILESYSTEM_DISK') !== 'local') {
            // Start b/g job to upload file to Cloud
            UploadFileToCloudJob::dispatch($model);
        }
    }

    private function createZip(Collection $files): array
    {
        $zipPath = 'zip/' . Str::random() . '.zip';
        if (!is_dir(dirname($zipPath))) {
            Storage::disk('public')->makeDirectory(dirname($zipPath));
        }

        $filesAdded = 0;
        $zipFile = Storage::disk('public')->path($zipPath); // absolute path to default public storage
        $zip = new ZipArchive();

        if ($zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
            $filesAdded = $this->addFilesToZip($zip, $files);
        }
        $zip->close();
        return [asset(Storage::disk('local')->url($zipPath)), $filesAdded];
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
                if ($file->uploaded_on_cloud) {
                    $dest = pathinfo($file->storage_path, PATHINFO_BASENAME);
                    $content = Storage::get($file->storage_path); // get file content from Cloud storage
                    Storage::disk('public')->put($dest, $content);  // and save file locally

                    $localPath = Storage::disk('public')->path($dest);
                } else {
                    $localPath = Storage::disk('local')->path($file->storage_path);
                }
                $zip->addFile($localPath, $ancestors . $file->name);
                $filesAdded++;
            }
        }
        return $filesAdded;
    }

    private function getDownloadUrl(array $ids, string $zipName): array
    {
        if (count($ids) === 1) {
            $file = File::find($ids[0]);
            if ($file->is_folder) {
                if ($file->children->count() === 0) {
                    return [
                        '', '', '', "The folder '{$file->name}' is empty",
                    ];
                }
                [$url, $filesAdded] = $this->createZip($file->children);
                $filename = $file->name . '.zip';
            } else {
                if ($file->uploaded_on_cloud) {
                    $content = Storage::get($file->storage_path); // get file content from Cloud storage
                } else {
                    $content = Storage::disk('local')->get($file->storage_path);
                }
                $dest = pathinfo($file->storage_path, PATHINFO_BASENAME);
                Storage::disk('public')->put($dest, $content);  // and save file locally

                $filesAdded = 1;
                $url = asset(Storage::disk('public')->url($dest));
                $filename = $file->name;
            }
        } else {
            $files = File::query()->whereIn('id', $ids)->get();
            [$url, $filesAdded] = $this->createZip($files);
            $filename = $zipName . '.zip';
        }
        return [$url, $filesAdded, $filename, null];
    }
}
