<?php

namespace Devlab\LaravelMailer\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmailSender extends Model
{
    use HasFactory;
    use SoftDeletes;
}
