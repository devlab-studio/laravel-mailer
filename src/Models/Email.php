<?php

namespace Devlab\LaravelMailer\Models;

use App\Jobs\SendEmail;
use Devlab\LaravelMailer\Traits\WithExtensions;
use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Email extends Model
{
    use HasFactory;
    use SoftDeletes;
    use WithExtensions;

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'send_at' => 'datetime',
        'sent_at' => 'datetime',
        'queued_at' => 'datetime',
    ];

    /**
     * Get emails.
     *
     * @param int $iModels_id
     * @param int $iRecordsInPage
     * @param array $aSort (attribute => 'asc'/'desc')
     * @param array $aFilters
     * @return mixed Colletion
     *
     */
    public static function dlGet(
        int $iModels_id = 0,
        int $iRecordsInPage = 0,
        array $aSort = [],
        array $aFilters = [],
        array $aWith = []
    ) {

        $oQuery = Email::
        when($iModels_id>0, function($query) use ($iModels_id) {
            return $query->where('id', $iModels_id);
        });

        $oQuery = static::dlApplyFilters($oQuery, $aFilters);

        // Order by
        foreach ($aSort as $key => $value) {
            $oQuery->orderBy($key, $value);
        }

        return static::getModelData($oQuery, $iModels_id, $iRecordsInPage, $aWith);
    }

    /**
     * Get summary.
     *
     * @param array $filters
     * @return mixed Colletion
     *
     */
    public static function dlGetSummary(
        ?array $filters = [],
        int $records_in_page = 0,
        ?array $group_by = [],
        ?array $sort = []
    ) {
        $query = static::selectRaw('
            count(*) as emails_count,
            sum(if(state=1, 1, 0)) as sents_count,
            sum(if(state=0, 1, 0)) as pendings_count,
            sum(if(state=-1, 1, 0)) as errors_count
        ');

        $query = static::dlApplyFilters($query, $filters);

        if (!empty($group_by)) {
            foreach ($group_by as $group) {
                if ($group == 'from') {
                    $query->addSelect('from');
                }
                if ($group == 'date') {
                    $query->addSelect(DB::raw('date(send_at) as date'));
                }
                $query->groupBy($group);
            }
            // $oQuery->dd();

            foreach ($sort as $key => $value) {
                $query->orderBy($key, $value);
            }
            return static::getModelData($query, 0, $records_in_page, [], null);
        } else {
            return $query->get()->first();
        }
    }

    /**
     * Apply filters.
     *
     * @param $oQuery
     * @param array $aFilters
     * @return mixed Query
     *
     */
    public static function dlApplyFilters(
        $oQuery,
        ?array $aFilters = []
    ) {
        $oQuery->when(isset($aFilters['mails_ids']) && !empty($aFilters['mails_ids']), function($query) use ($aFilters) {
            return $query->whereIn("id", $aFilters['mails_ids']);
            })
            ->when(isset($aFilters['insert_user']) && !empty($aFilters['insert_user']), function($query) use ($aFilters) {
                return $query->where("insert_user", $aFilters['insert_user']);
            })
            ->when(isset($aFilters['from']) && !empty($aFilters['from']), function($query) use ($aFilters) {
                return $query->where("from", 'like', '%'.$aFilters['from'].'%');
            })
            ->when(isset($aFilters['to']) && !empty($aFilters['to']), function($query) use ($aFilters) {
                return $query->whereFullText("to", $aFilters['to']);
            })
            ->when(isset($aFilters['subject']) && !empty($aFilters['subject']), function($query) use ($aFilters) {
                return $query->where("subject", 'like', '%'.$aFilters['subject'].'%');
            })
            ->when(isset($aFilters['date']) && !empty($aFilters['date']), function ($query) use ($aFilters) {
                return $query->whereBetween($aFilters['datetype'], $aFilters['date']);
            })
            ->when(isset($aFilters['search']) && !empty($aFilters['search']), function($query) use ($aFilters) {
                return $query->where(function ($query) use ($aFilters){
                    $query->whereFullText("to", $aFilters['search'])
                    ->orWhere('subject', 'like', '%'.$aFilters['search'].'%');
                });
            });
        return $oQuery;
    }

    /**
     * The email's attachments.
     */
    public function attachments()
    {
        return $this->hasMany(EmailsAttachment::class, 'email_id', 'id');
    }
}
