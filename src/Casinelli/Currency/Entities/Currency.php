<?php

namespace Casinelli\Currency\Entities;

use Config;
use Illuminate\Database\Eloquent\Model;

class Currency extends Model
{
    const STATUS_ENABLED = 1;
    const STATUS_DISABLED = 0;
    
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['title', 'code', 'value', 'boc_value', 'status'];


    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->table = Config::get('currency.table_name');
        $this->connection = Config::get('currency.connection');
    }
}
