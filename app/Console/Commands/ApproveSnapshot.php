<?php

declare(strict_types=1);

namespace Appocular\Assessor\Console\Commands;

use Appocular\Assessor\Checkpoint;
use Appocular\Assessor\Snapshot;
use Illuminate\Console\Command;

class ApproveSnapshot extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'assessor:approve-snapshot {snapshot_id}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Approve all checkpoints in snapshot.';

    /**
     * Create a new command instance.
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): ?int
    {
        $snapshot = Snapshot::findOrFail($this->argument('snapshot_id'));

        foreach ($snapshot->checkpoints as $checkpoint) {
            $checkpoint->approval_status = Checkpoint::APPROVAL_STATUS_APPROVED;
            $checkpoint->save();
        }

        return 0;
    }
}
