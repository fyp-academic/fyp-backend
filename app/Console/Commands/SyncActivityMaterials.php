<?php

namespace App\Console\Commands;

use App\Models\Activity;
use App\Services\ActivityMaterialService;
use Illuminate\Console\Command;

class SyncActivityMaterials extends Command
{
    protected $signature = 'adaptation:sync-materials
                            {--activity-id= : Sync a single activity}
                            {--type= : Limit to activity type (file,video,url,h5p,scorm)}';

    protected $description = 'Sync file/video/url activities into course_materials and queue text extraction.';

    public function handle(ActivityMaterialService $materialService): int
    {
        $query = Activity::query()->whereIn('type', ['file', 'video', 'url', 'h5p', 'scorm']);

        if ($this->option('activity-id')) {
            $query->where('id', $this->option('activity-id'));
        }

        if ($this->option('type')) {
            $query->where('type', $this->option('type'));
        }

        $activities = $query->get();
        $bar = $this->output->createProgressBar($activities->count());
        $synced = 0;

        foreach ($activities as $activity) {
            $material = $materialService->ensureForActivity($activity);
            if ($material) {
                $synced++;
                $this->line(" Synced {$activity->type} activity {$activity->id} → material {$material->id}");
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("Queued {$synced} materials. Run `php artisan queue:work` to extract and chunk.");

        return self::SUCCESS;
    }
}
