<?php

namespace Uccello\ModuleDesignerCore\Models;

use Uccello\Core\Database\Eloquent\Model;

class DesignedModule extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'designed_modules';

    /**
     * The attributes that should be casted to native types.
     *
     * @var array
     */
    protected $casts = [
        'data' => 'object',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'data',
    ];

    protected function initTablePrefix()
    {
        $this->tablePrefix = env('UCCELLO_TABLE_PREFIX', 'uccello_');
    }
}
