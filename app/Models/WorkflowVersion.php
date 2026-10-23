<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkflowVersion extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['definition' => 'array', 'version' => 'integer'];
    }
}
