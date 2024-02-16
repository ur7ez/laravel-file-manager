<?php

namespace App\Jobs;

use App\Models\File;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class UploadFileToCloudJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(protected File $file)
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $model = $this->file;
        if (!$model->uploaded_on_cloud) {
            $localPath = Storage::disk('local')->path($model->storage_path);
            Log::debug("Uploading file on S3. $localPath");

            try {
                // may cause error for large files upload to S3 (see AWS PHP SDK for HashingStream::seek())
                $success = Storage::put(
                    $model->storage_path,
                    Storage::disk('local')->get($model->storage_path)
                );
                if ($success) {
                    Log::debug('Uploaded. Updating the database');
                    $model->uploaded_on_cloud = 1;
                    $model->saveQuietly();  // we can't update updated_by column in queue
                    // TODO: maybe need to delete file from local storage after uploading to Cloud
                } else {
                    Log::error('Unable to upload file to S3');
                }
            } catch (\Exception $e) {
                Log::error($e->getMessage());
            }
        }
    }
}
