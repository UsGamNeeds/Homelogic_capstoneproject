<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MedicationSynonym extends Model
{
    protected $fillable = [
        'canonical_name',
        'rxnorm_cui',
        'synonym',
    ];
}
