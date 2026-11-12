<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Cached AI-written explanation, so the same finding is never paid for twice.
 *
 * @property int $id
 * @property string $check_key
 * @property string $context_hash
 * @property string $body
 * @property string $model
 */
#[Fillable(['check_key', 'context_hash', 'body', 'model'])]
class AiExplanation extends Model
{
    //
}
