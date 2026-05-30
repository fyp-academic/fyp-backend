<?php

namespace App\Console\Commands;

use App\Jobs\ChunkContentJob;
use App\Models\LessonPage;
use Illuminate\Console\Command;

class ChunkExistingContent extends Command
{
    protected $signature = 'adaptation:chunk-existing
                            {--page-id= : Chunk a single lesson page by UUID}
                            {--all : Chunk all existing lesson pages}';

    protected $description = 'Dispatch ChunkContentJob for existing lesson pages.';

    public function handle(): int
    {
        if ($this->option('page-id')) {
            $page = LessonPage::find($this->option('page-id'));

            if (! $page) {
                $this->error('Lesson page not found.');
                return self::FAILURE;
            }

            ChunkContentJob::dispatch($page->id, $page->content, 'lecture', 'lesson_page');
            $this->info("Dispatched ChunkContentJob for page {$page->id}.");
            return self::SUCCESS;
        }

        if (! $this->option('all')) {
            $this->error('Use --all to chunk every page, or --page-id=UUID for a single page.');
            return self::FAILURE;
        }

        $pages = LessonPage::whereNotNull('content')->get();
        $bar = $this->output->createProgressBar($pages->count());

        foreach ($pages as $page) {
            ChunkContentJob::dispatch($page->id, $page->content, 'lecture', 'lesson_page');
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Dispatched ChunkContentJob for {$pages->count()} lesson pages.");
        $this->info('Run `php artisan queue:work` to process them.');

        return self::SUCCESS;
    }
}
