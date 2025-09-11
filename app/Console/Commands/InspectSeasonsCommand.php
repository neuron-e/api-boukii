<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class InspectSeasonsCommand extends Command
{
    protected $signature = 'boukii:inspect-seasons {--db=} {--school=}';
    protected $description = 'Lista temporadas (start_date, end_date, is_active) de una escuela en la conexión indicada.';

    public function handle(): int
    {
        $db = (string) $this->option('db');
        $school = (int) $this->option('school');
        if (!$db || !$school) { $this->error('Uso: --db=conexion --school=ID'); return 1; }
        $rows = DB::connection($db)->table('seasons')->where('school_id',$school)->orderBy('start_date')->get(['id','name','start_date','end_date','is_active','hour_start','hour_end']);
        $this->table(['id','name','start','end','active','h_start','h_end'], $rows->map(fn($r)=>[(string)$r->id,$r->name,$r->start_date,$r->end_date,$r->is_active,$r->hour_start,$r->hour_end])->toArray());
        return 0;
    }
}

