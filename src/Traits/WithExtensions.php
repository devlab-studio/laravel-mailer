<?php

namespace Devlab\LaravelMailer\Traits;

use Devlab\LaravelMailer\Classes\dlSign;
use Illuminate\Database\Eloquent\Builder;
use Devlab\LaravelCore\Classes\dlURL;
use Illuminate\Support\Pluralizer;

trait WithExtensions
{
    public function scopeWhereInto(Builder $query, $field, $value)
    {
        if (is_array($value)) {
            return $query->whereIn($field, $value);
        } else {
            $value = explode(",", $value);
            return $query->whereIn($field, $value);
        }
    }
    public static function getModelData($oQuery, $iModel_id, $iRecordsInPage = 0, $aWithDerived = [], $keyBy = 'id') {
        if (!empty($aWithDerived)) {
            $oQuery->with($aWithDerived);
        }
        if ($iModel_id == 0) {
            $iRecordsInPage = ($iRecordsInPage == 0 ) ? config('constants.pagination.DEFAULT_PAGE_RECORDS') : $iRecordsInPage;
            if ($iRecordsInPage>0) {
                $oRecords = $oQuery->paginate($iRecordsInPage);
                $oRecordsC = $oRecords->getCollection()->keyBy($keyBy);
                $oRecords->setCollection($oRecordsC);
            } else {
                $oRecords = $oQuery->get()->keyBy($keyBy);
            }
        } else {
            $oRecords = $oQuery->get()->first();
        }
        return $oRecords;
    }
}
