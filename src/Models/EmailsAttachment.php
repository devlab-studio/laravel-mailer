<?php

namespace Devlab\LaravelMailer\Models;

use App\Classes\Utils;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\UploadedFile;

class EmailsAttachment extends Model
{
    use HasFactory;
    use SoftDeletes;

}
