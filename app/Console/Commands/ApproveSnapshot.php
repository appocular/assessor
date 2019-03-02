<?php

namespace Appocular\Assessor\Console\Commands;

use Appocular\Assessor\Snapshot;
use Appocular\Assessor\Checkpoint;
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
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $snapshot = Snapshot::findOrFail($this->argument('snapshot_id'));
        foreach ($snapshot->checkpoints as $checkpoint) {
            $checkpoint->status = Checkpoint::STATUS_APPROVED;
            $checkpoint->save();
        }
    }
}
